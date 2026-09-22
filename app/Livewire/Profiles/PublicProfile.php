<?php

namespace App\Livewire\Profiles;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class PublicProfile extends Component
{
    public User $user;

    public function mount(User $user): void
    {
        $this->user = $user;
    }

    public function message()
    {
        if (! Auth::check()) {
            return redirect()->guest(route('login'))
                ->with('status', 'Please log in to message this student.');
        }

        if ($this->user->id === Auth::id()) {
            return null;
        }

        return redirect()->route('chat.thread', ['receiver' => $this->user->id]);
    }

    public function render()
    {
        return view('livewire.profiles.public-profile', [
            'activeListingsCount' => $this->user->listings()->active()->count(),
            'averageRating' => $this->user->averageRating(),
            'ratingsCount' => $this->user->ratingsCount(),
            'ratingBreakdown' => $this->user->ratingBreakdown(),
            'recentRatings' => $this->user->ratingsReceived()->with('rater:id,name,avatar_path')->latest()->limit(10)->get(),
        ])->layout('layouts.app', ['title' => $this->user->name.' - UniMarket']);
    }
}
