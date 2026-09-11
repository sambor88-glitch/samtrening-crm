<?php

namespace App\View\Components;

use App\Domain\Settings\Models\Setting;
use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    /**
     * Route name => label, in the order of the prototype.
     */
    public const TRAINER_NAVIGATION = [
        'dashboard' => 'Pulpit',
        'clients.index' => 'Klienci',
        'sessions.index' => 'Sesje',
        'payments.index' => 'Płatności',
        'earnings.index' => 'Zarobki',
        'messages.index' => 'Wiadomości',
        'settings.index' => 'Ustawienia',
    ];

    public const ADMIN_NAVIGATION = [
        'admin.dashboard' => 'Pulpit',
        'admin.trainers.index' => 'Trenerzy',
        'admin.clients.index' => 'Klienci studia',
        'admin.outstanding.index' => 'Zaległości',
        'admin.activity.index' => 'Log zmian',
        'admin.messages.index' => 'Wiadomości',
        'admin.settings.index' => 'Ustawienia',
    ];

    public function __construct(public ?string $title = null) {}

    /**
     * The panel follows the URL: admin.* routes are the admin panel (owner-only, see the `owner`
     * middleware); everything else is the trainer panel.
     */
    public function render(): View
    {
        $admin = request()->routeIs('admin.*');

        return view('layouts.app', [
            'admin' => $admin,
            'navigation' => $admin ? self::ADMIN_NAVIGATION : self::TRAINER_NAVIGATION,
            'tickerEnabled' => (bool) (Setting::query()->value('ticker_enabled') ?? true),
        ]);
    }
}
