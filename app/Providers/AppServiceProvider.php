<?php

namespace App\Providers;

use App\Domain\Calendar\CalendarAccessToken;
use App\Domain\Calendar\GoogleCalendar;
use App\Domain\Messaging\Providers\SmsProvider;
use App\Domain\Team\Enums\UserStatus;
use App\Domain\Team\Models\User;
use Carbon\CarbonInterval;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Passport;

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

        // Claude signs in with a browser redirect, never by typing a code into a TV — the device
        // grant would only be one more door to watch (docs/CLAUDE-CONNECTOR.md).
        Passport::$deviceCodeGrantEnabled = false;
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The studio rules — reminder threshold, free cancellation window, retention, ticker —
        // belong to the owner. A trainer reads them and nothing more (docs/START-TUTAJ.md §7).
        Gate::define('manage-studio-rules', fn (User $user) => $user->status === UserStatus::Active && $user->is_owner);

        $this->configureClaudeConnector();
    }

    /**
     * Claude's connector — docs/CLAUDE-CONNECTOR.md, SC-68. One scope, and every token gets it,
     * since claude.ai does not always ask for one. An hour per access token: Claude refreshes on
     * its own, and a token copied out of a log goes stale before lunch. A month per refresh
     * token: a phone left untouched that long has to go through the consent screen again.
     */
    private function configureClaudeConnector(): void
    {
        Passport::tokensCan([Registrar::OAUTH_SCOPE => 'CRM studia w rozmowie z Claude']);
        Passport::defaultScopes(Registrar::OAUTH_SCOPE);
        Passport::tokensExpireIn(CarbonInterval::hour());
        Passport::refreshTokensExpireIn(CarbonInterval::days(30));
        Passport::authorizationView('auth.connect-claude');

        // Per account, not per address: every call comes from Anthropic's cloud, one IP range.
        RateLimiter::for('mcp', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->getAuthIdentifier() ?: $request->ip()));
    }
}
