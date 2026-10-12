<?php

namespace App\Livewire\Account;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ThemeToggle extends Component
{
    private const OPTIONS = ['light', 'dark', 'auto'];

    /**
     * The JS side applies the theme immediately on click (see
     * window.uniMarketTheme.set in layouts/app.blade.php); this just
     * persists it on the user so it follows them to their next device.
     */
    public function persist(string $theme): void
    {
        if (! in_array($theme, self::OPTIONS, true)) {
            return;
        }

        Auth::user()?->update(['theme' => $theme]);
    }

    public function render()
    {
        return view('livewire.account.theme-toggle', [
            'current' => Auth::user()?->theme ?? 'auto',
        ]);
    }
}
