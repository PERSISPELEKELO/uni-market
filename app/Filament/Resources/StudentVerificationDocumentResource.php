<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StudentVerificationDocumentResource\Pages;
use App\Models\StudentVerificationDocument;
use App\Services\StudentVerificationService;
use Filament\Forms;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
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

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('submitted_at', 'desc')
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
                    ->label('View document')
                    ->icon('heroicon-o-eye')
                    ->url(fn (StudentVerificationDocument $record): string => route('verification.documents.show', $record))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (StudentVerificationDocument $record): bool => $record->isPending())
                    ->requiresConfirmation()
                    ->modalDescription(fn (StudentVerificationDocument $record) => "Confirm that the name and student ID on the document match {$record->user->name} (Student ID {$record->user->student_id}) before approving.")
                    ->action(function (StudentVerificationDocument $record, StudentVerificationService $service) {
                        static::run($service, fn () => $service->approve($record, Auth::user()), 'Student verified.');
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (StudentVerificationDocument $record): bool => $record->isPending())
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason (shown to the student)')
                            ->required()
                            ->minLength(10),
                    ])
                    ->action(function (StudentVerificationDocument $record, array $data, StudentVerificationService $service) {
                        static::run($service, fn () => $service->reject($record, Auth::user(), $data['reason']), 'Decision recorded.');
                    }),

                Tables\Actions\Action::make('request_resubmission')
                    ->label('Request resubmission')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (StudentVerificationDocument $record): bool => $record->isPending())
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('What is unclear or missing? (shown to the student)')
                            ->required()
                            ->minLength(10),
                    ])
                    ->action(function (StudentVerificationDocument $record, array $data, StudentVerificationService $service) {
                        static::run($service, fn () => $service->requestResubmission($record, Auth::user(), $data['reason']), 'Resubmission requested.');
                    }),
            ]);
    }

    /**
     * Run a decision, showing the admin a clear success or failure banner either way.
     */
    protected static function run(StudentVerificationService $service, \Closure $decision, string $successMessage): void
    {
        Gate::authorize('review', StudentVerificationDocument::class);

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
        ];
    }
}
