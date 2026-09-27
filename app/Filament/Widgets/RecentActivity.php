<?php

namespace App\Filament\Widgets;

use App\Models\AuditLog;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * A human-readable feed over the real, already-hash-chained AuditLog table -
 * no separate "activity" table invented for this.
 */
class RecentActivity extends BaseWidget
{
    protected static ?string $heading = 'Recent Marketplace Activity';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    /**
     * @var array<string, string>
     */
    private const LABELS = [
        'USER_REGISTERED' => 'registered a new account',
        'USER_LOGIN' => 'logged in',
        'USER_SUSPENDED' => 'suspended an account',
        'USER_REINSTATED' => 'reinstated an account',
        'USER_DELETED' => 'deleted an account',
        'PASSWORD_CHANGED' => 'changed their password',
        'PASSWORD_RESET' => 'reset their password',
        'LISTING_CREATED' => 'created a new listing',
        'LISTING_UPDATED' => 'updated a listing',
        'LISTING_DELETED' => 'removed a listing',
        'LISTING_RESERVED' => 'reserved a listing',
        'LISTING_SUSPENDED' => 'suspended a listing',
        'LISTING_RESTORED' => 'restored a listing',
        'TRANSACTION_INITIATED' => 'started a transaction',
        'TRANSACTION_HANDOVER_CONFIRMED' => 'confirmed a handover',
        'TRANSACTION_BUYER_ACCEPTED' => 'accepted an item',
        'DISPUTE_RAISED' => 'opened a dispute',
        'POST_PURCHASE_DISPUTE_RAISED' => 'opened a post-purchase dispute',
        'DISPUTE_RESOLVED_BUYER' => 'resolved a dispute in favour of the buyer',
        'DISPUTE_RESOLVED_SELLER' => 'resolved a dispute in favour of the seller',
        'APPEAL_SUBMITTED' => 'submitted an appeal',
        'APPEAL_REVIEW_STARTED' => 'started reviewing an appeal',
        'APPEAL_DECIDED' => 'decided an appeal',
        'APPEAL_RESOLVED' => 'resolved an appeal',
        'STUDENT_VERIFICATION_SUBMITTED' => 'submitted a student verification document',
        'STUDENT_VERIFICATION_APPROVED' => 'approved a student verification',
        'STUDENT_VERIFICATION_REJECTED' => 'rejected a student verification',
        'RATING_SUBMITTED' => 'submitted a rating',
    ];

    public function table(Table $table): Table
    {
        return $table
            ->query(AuditLog::query()->latest('timestamp'))
            ->paginated([10, 25, 50])
            ->columns([
                Tables\Columns\TextColumn::make('actor_name_snapshot')
                    ->label('Who')
                    ->state(fn (AuditLog $record): string => $record->actorDisplayName())
                    ->weight('semibold'),

                Tables\Columns\TextColumn::make('action')
                    ->label('Activity')
                    ->formatStateUsing(fn (string $state): string => self::LABELS[$state] ?? strtolower(str_replace('_', ' ', $state))),

                Tables\Columns\TextColumn::make('target_type')
                    ->label('Concerning')
                    ->formatStateUsing(fn (?string $state, AuditLog $record): string => $state ? "{$state} #{$record->target_id}" : '-'),

                Tables\Columns\TextColumn::make('timestamp')
                    ->label('When')
                    ->since()
                    ->sortable(),
            ]);
    }
}
