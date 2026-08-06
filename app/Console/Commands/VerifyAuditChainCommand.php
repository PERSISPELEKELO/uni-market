<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AuditLoggerService;
use Illuminate\Console\Command;

class VerifyAuditChainCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:verify';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify the cryptographic SHA-256 hash chain integrity of the audit log ledger';

    /**
     * Execute the console command.
     */
    public function handle(AuditLoggerService $auditLogger): int
    {
        $this->info('Starting audit log chain verification...');

        $result = $auditLogger->verifyIntegrity();

        if ($result['is_valid']) {
            $this->info('✓ SUCCESS: Audit log chain is intact and valid.');
            return self::SUCCESS;
        }

        $this->error("✗ FAILURE: Audit chain CORRUPTED at ID {$result['failed_at_id']}.");
        return self::FAILURE;
    }
}
