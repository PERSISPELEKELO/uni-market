<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DisputeResource\Pages;
use App\Models\Dispute;
use App\Models\Message;
use App\Services\AuditLoggerService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class DisputeResource extends Resource
{
    protected static ?string $model = Dispute::class;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationGroup = 'AI Dispute Assistance';

    protected static ?string $navigationLabel = 'Review Disputes';

    public static function canViewAny(): bool
    {
        return Gate::allows('access-governance');
    }

    /**
     * The escrow rules every buyer and seller agree to when they use the
     * handover flow - shown verbatim so the admin can judge a dispute
     * against the actual policy, not a rule they have to remember.
     */
    private const ESCROW_POLICY = 'The buyer reserves the item, the two parties meet on campus, and the buyer shares '
        .'their handover code only once they have the item in hand. From that point, the buyer has a 48-hour '
        .'inspection window to confirm the item as described or raise a dispute. Sellers never see the handover '
        .'code in advance.';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('1. Dispute')
                    ->description('What was reported, by whom, about which transaction.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('transaction_id')
                            ->relationship('transaction', 'id')
                            ->disabled()
                            ->required(),

                        Forms\Components\Select::make('raised_by')
                            ->label('Raised by')
                            ->relationship('reporter', 'name')
                            ->disabled()
                            ->required(),

                        Forms\Components\Textarea::make('reason')
                            ->label("Buyer's complaint")
                            ->disabled()
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('2. Evidence')
                    ->description('What this system actually has on record for this case - nothing else is assumed.')
                    ->schema([
                        Forms\Components\Placeholder::make('case_context')
                            ->label('Listing & transaction')
                            ->content(function (?Dispute $record) {
                                if (! $record?->transaction) {
                                    return 'Not available.';
                                }

                                $tx = $record->transaction;
                                $listing = $tx->listing;

                                return sprintf(
                                    '"%s" — K%s. Buyer: %s. Seller: %s. Transaction status: %s. Started %s.',
                                    $listing?->title ?? 'Listing removed',
                                    number_format((float) $tx->amount, 2),
                                    $tx->buyer?->name ?? 'Unknown',
                                    $tx->seller?->name ?? 'Unknown',
                                    ucfirst(strtolower(str_replace('_', ' ', (string) $tx->status))),
                                    $tx->created_at?->format('M j, Y g:i A') ?? 'Unknown'
                                );
                            })
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('messages')
                            ->label('Messages between buyer and seller about this transaction')
                            ->content(function (?Dispute $record) {
                                if (! $record?->transaction_id) {
                                    return 'Not available.';
                                }

                                $messages = Message::where('transaction_id', $record->transaction_id)
                                    ->with('sender')
                                    ->orderBy('created_at')
                                    ->get();

                                if ($messages->isEmpty()) {
                                    return 'No messages were exchanged about this transaction.';
                                }

                                return $messages
                                    ->map(fn (Message $message): string => sprintf(
                                        '[%s] %s: %s',
                                        $message->created_at->format('M j, g:i A'),
                                        $message->sender?->name ?? 'Unknown',
                                        $message->message
                                    ))
                                    ->implode("\n");
                            })
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('missing_evidence')
                            ->label('Not captured by this system')
                            ->content('A separate written seller response and photo evidence are not collected as distinct fields today - review the message thread above for the seller\'s side of the conversation.')
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('policy')
                            ->label('Relevant policy')
                            ->content(self::ESCROW_POLICY)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('3. AI Analysis')
                    ->description('Advisory only, from the sentiment-analysis microservice. Empty if the service was unavailable when this dispute was raised - the case is still yours to decide either way.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('ai_sentiment_score')
                            ->label('Sentiment (-1 to +1)')
                            ->disabled()
                            ->placeholder('Not available'),

                        Forms\Components\TextInput::make('ai_confidence_score')
                            ->label('Confidence')
                            ->formatStateUsing(fn ($state) => $state !== null ? number_format($state * 100).'%' : null)
                            ->disabled()
                            ->placeholder('Not available'),

                        Forms\Components\TextInput::make('ai_suggested_resolution')
                            ->label('Suggested next action')
                            ->disabled()
                            ->placeholder('Not available')
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('ai_analysis_summary')
                            ->label('Summary')
                            ->disabled()
                            ->placeholder('Not available')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('4. Admin Decision')
                    ->description('The AI never decides a case automatically - a person always makes and records the final call.')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                'open' => 'Open',
                                'under_review' => 'Under Review',
                                'resolved_buyer' => 'Resolved (Refund Buyer)',
                                'resolved_seller' => 'Resolved (Release to Seller)',
                            ])
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),

                Tables\Columns\TextColumn::make('transaction.id')
                    ->label('Tx #')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reporter.name')
                    ->label('Raised By')
                    ->searchable(),

                Tables\Columns\TextColumn::make('reason')
                    ->limit(40)
                    ->tooltip(fn (Dispute $record): string => $record->reason),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'open',
                        'primary' => 'under_review',
                        'success' => 'resolved_buyer',
                        'success' => 'resolved_seller',
                    ]),

                Tables\Columns\TextColumn::make('ai_sentiment_score')
                    ->label('AI Sentiment')
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format($state, 2) : 'N/A')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ai_confidence_score')
                    ->label('Confidence')
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format($state * 100).'%' : 'N/A')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'under_review' => 'Under Review',
                        'resolved_buyer' => 'Resolved (Buyer)',
                        'resolved_seller' => 'Resolved (Seller)',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('resolve_buyer')
                    ->label('Refund Buyer')
                    ->color('success')
                    ->icon('heroicon-o-receipt-refund')
                    ->action(function (Dispute $record) {
                        $record->update(['status' => 'resolved_buyer']);
                        $record->transaction->update(['status' => 'refunded']);

                        app(AuditLoggerService::class)->recordAction(
                            Auth::user(),
                            'DISPUTE_RESOLVED_BUYER',
                            'Dispute',
                            (string) $record->id,
                            ['resolution' => 'refunded_buyer']
                        );
                    }),
                Tables\Actions\Action::make('resolve_seller')
                    ->label('Release to Seller')
                    ->color('primary')
                    ->icon('heroicon-o-check-circle')
                    ->action(function (Dispute $record) {
                        $record->update(['status' => 'resolved_seller']);
                        $record->transaction->update(['status' => 'completed']);
                        $record->transaction->listing->update(['status' => 'sold']);

                        app(AuditLoggerService::class)->recordAction(
                            Auth::user(),
                            'DISPUTE_RESOLVED_SELLER',
                            'Dispute',
                            (string) $record->id,
                            ['resolution' => 'released_to_seller']
                        );
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDisputes::route('/'),
            'edit' => Pages\EditDispute::route('/{record}/edit'),
        ];
    }
}
