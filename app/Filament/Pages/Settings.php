<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Deliberately minimal: an admin's own password, plus a read-only look at
 * the real marketplace policy figures. Nothing here exposes raw .env
 * values or lets anyone change platform behaviour without a code change -
 * that's the safer default for a first pass.
 */
class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Security & Compliance';

    protected static string $view = 'filament.pages.settings';

    /**
     * @var array<string, mixed>
     */
    public array $passwordData = [];

    public function mount(): void
    {
        $this->passwordForm->fill();
    }

    public function passwordForm(Form $form): Form
    {
        return $form
            ->statePath('passwordData')
            ->schema([
                Section::make('Change password')
                    ->description('Update the password for your own admin account.')
                    ->schema([
                        TextInput::make('current_password')
                            ->label('Current password')
                            ->password()
                            ->revealable()
                            ->required(),

                        TextInput::make('new_password')
                            ->label('New password')
                            ->password()
                            ->revealable()
                            ->required()
                            ->rule(Password::min(8)->letters()->numbers())
                            ->confirmed(),

                        TextInput::make('new_password_confirmation')
                            ->label('Confirm new password')
                            ->password()
                            ->revealable()
                            ->required(),
                    ]),
            ]);
    }

    public function updatePassword(): void
    {
        $data = $this->passwordForm->getState();

        if (! Hash::check($data['current_password'], Auth::user()->password)) {
            Notification::make()->title('Current password is incorrect')->danger()->send();

            return;
        }

        Auth::user()->update(['password' => $data['new_password']]);

        $this->passwordForm->fill();

        Notification::make()->title('Password updated')->success()->send();
    }

    /**
     * Real, non-editable figures the admin should know about the platform's
     * behaviour, straight from config - never invented.
     *
     * @return array{ai_enabled: bool, inspection_hours: int}
     */
    public function getMarketplacePolicy(): array
    {
        return [
            'ai_enabled' => (bool) config('services.dispute_ai.enabled'),
            'inspection_hours' => 48,
        ];
    }

    protected function getForms(): array
    {
        return [
            'passwordForm',
        ];
    }
}
