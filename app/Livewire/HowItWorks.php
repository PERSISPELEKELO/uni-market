<?php

declare(strict_types=1);

namespace App\Livewire;

use Livewire\Component;

class HowItWorks extends Component
{
    public function render()
    {
        return view('livewire.how-it-works')
            ->layout('layouts.app', ['title' => 'How UniMarket Works - UniMarket']);
    }
}
