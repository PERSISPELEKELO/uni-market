<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\AuditLoggerService;
use Illuminate\Console\Command;

class AutoCompleteInspections extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transactions:auto-complete';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-complete transactions whose inspection period has ended without dispute';

    /**
     * Execute the console command.
     */
    public function handle(AuditLoggerService $auditLogger): int
    {
        $this->info('Scanning for handed over / inspection active transactions with expired inspection windows...');

        $count = 0;

        Transaction::query()
            ->whereIn('status', ['ITEM_INSPECTION', 'HANDED_OVER'])
            ->where(function ($query) {
                $query->where('inspection_expires_at', '<=', now())
                    ->orWhere('inspection_ends_at', '<=', now());
            })
            ->chunk(100, function ($transactions) use ($auditLogger, &$count) {
                foreach ($transactions as $transaction) {
                    $completedAt = now();
                    $transaction->update([
                        'status' => 'COMPLETED',
                        'completed_at' => $completedAt,
                    ]);

                    if ($transaction->listing) {
                        $transaction->listing->update(['status' => 'sold']);
                    }

                    $auditLogger->recordAction(
                        actor: null,
                        action: 'TRANSACTION_AUTO_COMPLETED_POST_INSPECTION',
                        targetType: 'Transaction',
                        targetId: (string) $transaction->id,
                        payload: [
                            'inspection_ended_at' => ($transaction->inspection_expires_at ?? $transaction->inspection_ends_at)?->toIso8601String(),
                            'completed_at' => $completedAt->toIso8601String(),
                        ]
                    );

                    $count++;
                }
            });

        $this->info("✓ Successfully auto-completed {$count} transaction(s).");

        return self::SUCCESS;
    }
}
