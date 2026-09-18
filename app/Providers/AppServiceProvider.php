<?php

namespace App\Providers;

use App\Domain\Calendar\CalendarAccessToken;
use App\Domain\Calendar\GoogleCalendar;
use App\Domain\Messaging\Providers\SmsProvider;
use App\Domain\Team\Enums\UserStatus;
use App\Domain\Team\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Which carrier sends the messages is one binding, nothing else in the app knows.
        $this->app->bind(SmsProvider::class, function () {
            $provider = config('sms.provider');

            return $this->app->make(config("sms.providers.{$provider}"));
        });

        // The diary reader carries its own credentials, separate from the ones the
        // mail goes out on — see config/calendar.php and SC-65.
        $this->app->singleton(GoogleCalendar::class, function () {
            $config = config('calendar');

            return new GoogleCalendar(new CalendarAccessToken($config), $config);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The studio rules — reminder threshold, free cancellation window, retention, ticker —
        // belong to the owner. A trainer reads them and nothing more (docs/START-TUTAJ.md §7).
        Gate::define('manage-studio-rules', fn (User $user) => $user->status === UserStatus::Active && $user->is_owner);
    }
}
