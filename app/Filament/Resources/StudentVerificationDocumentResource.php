<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StudentVerificationDocumentResource\Pages;
use App\Models\StudentVerificationDocument;
use App\Services\StudentVerificationService;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class StudentVerificationDocumentResource extends Resource
{
    protected static ?string $model = StudentVerificationDocument::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'Moderation & Disputes';

    protected static ?string $navigationLabel = 'Student Verification';

    protected static ?string $modelLabel = 'verification submission';

    public static function canCreate(): bool
    {
        return false; // Submissions only ever come from students, through the marketplace.
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::where('status', StudentVerificationDocument::STATUS_PENDING)->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            // Pending submissions always surface first, oldest first, so the
            // queue reads as a FIFO line - but an admin can still click a
            // column header to sort some other way.
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [StudentVerificationDocument::STATUS_PENDING])
                ->orderBy('submitted_at', 'asc'))
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable(),

                Tables\Columns\TextColumn::make('user.student_id')
                    ->label('Student ID (registration)')
                    ->copyable(),

                Tables\Columns\TextColumn::make('original_filename')
                    ->label('Document')
                    ->limit(24),

                Tables\Columns\TextColumn::make('submitted_at')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => StudentVerificationDocument::STATUS_PENDING,
                        'success' => StudentVerificationDocument::STATUS_APPROVED,
                        'danger' => StudentVerificationDocument::STATUS_REJECTED,
                    ]),

                Tables\Columns\TextColumn::make('user.student_verification_status')
                    ->label('Current account status')
                    ->badge(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        StudentVerificationDocument::STATUS_PENDING => 'Pending review',
                        StudentVerificationDocument::STATUS_APPROVED => 'Approved',
                        StudentVerificationDocument::STATUS_REJECTED => 'Rejected',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('view_document')
                    ->label('Review')
                    ->icon('heroicon-o-eye')
                    ->url(fn (StudentVerificationDocument $record): string => static::getUrl('view', ['record' => $record])),

                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (StudentVerificationDocument $record): bool => $record->isPending() && Auth::id() !== $record->user_id)
                    ->form(fn (StudentVerificationDocument $record): array => [
                        Forms\Components\Placeholder::make('registered_details')
                            ->label('Confirm against the registration')
                            ->content("{$record->user->name} - Student ID {$record->user->student_id}"),

                        Forms\Components\Checkbox::make('checklist_front_side')
                            ->label('This is the front of a ZUT student ID card')
                            ->rule('accepted'),
                        Forms\Components\Checkbox::make('checklist_name_matches')
                            ->label('The name on the card matches the registered name')
                            ->rule('accepted'),
                        Forms\Components\Checkbox::make('checklist_number_matches')
                            ->label('The student number on the card matches the registered number')
                            ->rule('accepted'),
                        Forms\Components\Checkbox::make('checklist_photo_clear')
                            ->label('The photo is clear, unedited, and not a photo of a screen')
                            ->rule('accepted'),
                    ])
                    ->action(function (StudentVerificationDocument $record, StudentVerificationService $service) {
                        static::run($record, $service, fn () => $service->approve($record, Auth::user()), 'Student verified.');
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (StudentVerificationDocument $record): bool => $record->isPending() && Auth::id() !== $record->user_id)
                    ->form([
                        Forms\Components\Select::make('reason_code')
                            ->label('Reason (shown to the student)')
                            ->options(StudentVerificationDocument::REJECTION_REASONS)
                            ->required()
                            ->live(),
                        Forms\Components\Textarea::make('note')
                            ->label('Details')
                            ->visible(fn (Get $get): bool => $get('reason_code') === 'other')
                            ->requiredIf('reason_code', 'other')
                            ->minLength(10),
                    ])
                    ->action(function (StudentVerificationDocument $record, array $data, StudentVerificationService $service) {
                        static::run($record, $service, fn () => $service->reject($record, Auth::user(), $data['reason_code'], $data['note'] ?? null), 'Decision recorded.');
                    }),

                Tables\Actions\Action::make('request_resubmission')
                    ->label('Request resubmission')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (StudentVerificationDocument $record): bool => $record->isPending() && Auth::id() !== $record->user_id)
                    ->form([
                        Forms\Components\Select::make('reason_code')
                            ->label('What is unclear or missing? (shown to the student)')
                            ->options(StudentVerificationDocument::RESUBMISSION_REASONS)
                            ->required()
                            ->live(),
                        Forms\Components\Textarea::make('note')
                            ->label('Details')
                            ->visible(fn (Get $get): bool => $get('reason_code') === 'other')
                            ->requiredIf('reason_code', 'other')
                            ->minLength(10),
                    ])
                    ->action(function (StudentVerificationDocument $record, array $data, StudentVerificationService $service) {
                        static::run($record, $service, fn () => $service->requestResubmission($record, Auth::user(), $data['reason_code'], $data['note'] ?? null), 'Resubmission requested.');
                    }),
            ]);
    }

    /**
     * Run a decision, showing the admin a clear success or failure banner either way.
     */
    protected static function run(StudentVerificationDocument $record, StudentVerificationService $service, \Closure $decision, string $successMessage): void
    {
        Gate::authorize('review', $record);

        try {
            $decision();

            FilamentNotification::make()->title($successMessage)->success()->send();
        } catch (\InvalidArgumentException $exception) {
            FilamentNotification::make()->title('Could not apply this decision')->body($exception->getMessage())->danger()->send();
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStudentVerificationDocuments::route('/'),
            'view' => Pages\ViewSubmission::route('/{record}'),
        ];
    }
}
