<?php

namespace App\Livewire\Trainer;

use Illuminate\Contracts\View\View;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * What the owner left for this trainer. It shows up on their next visit to the CRM and goes away
 * with one click — the studio chose a pop-up over mail and push, which would have meant a PWA
 * this product deliberately does not build (SC-39).
 */
class Reminders extends Component
{
    public function dismiss(string $notification): void
    {
        auth()->user()?->unreadNotifications()->whereKey($notification)->first()?->markAsRead();
    }

    public function render(): View
    {
        /** @var Collection<int, DatabaseNotification> $waiting */
        $waiting = auth()->user()
            ? auth()->user()->unreadNotifications()->latest()->get()
            : collect();

        return view('livewire.trainer.reminders', ['reminder' => $waiting->first(), 'waiting' => $waiting->count()]);
    }
}
