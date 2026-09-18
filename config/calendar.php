<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Google Calendar — read only
    |--------------------------------------------------------------------------
    |
    | The studio's diary lives in Google Calendar and the CRM never writes to
    | it. This is the other direction: the CRM reads what was booked so it can
    | offer the sessions for logging (docs/AGENT-API.md, SC-65).
    |
    | The refresh token is deliberately NOT the one Gmail uses. One token
    | covering both would mean a revoked or mis-issued calendar consent also
    | stops the reminders going out, and docs/START-TUTAJ.md §9 is explicit
    | that a failure in one channel must never take another down with it.
    | The client id and secret can be shared — they belong to the same Google
    | Cloud project — so only the token needs a second consent run.
    |
    */

    'client_id' => env('GOOGLE_CALENDAR_CLIENT_ID', env('GMAIL_CLIENT_ID')),
    'client_secret' => env('GOOGLE_CALENDAR_CLIENT_SECRET', env('GMAIL_CLIENT_SECRET')),
    'refresh_token' => env('GOOGLE_CALENDAR_REFRESH_TOKEN'),

    // Which diary to read. "primary" is the account's own calendar; a studio
    // calendar shared with that account is addressed by its id.
    'calendar_id' => env('GOOGLE_CALENDAR_ID', 'primary'),

    // The account that grants consent — only used to preselect it on the
    // consent screen, so nobody authorises the wrong Google account.
    'account' => env('GOOGLE_CALENDAR_ACCOUNT', env('GMAIL_SEND_AS')),

    'redirect_uri' => env('GOOGLE_CALENDAR_REDIRECT_URI', env('GMAIL_REDIRECT_URI', 'http://localhost')),

    // Read only, and nothing else. The CRM has no business writing to a diary
    // three people keep by hand.
    'scope' => 'https://www.googleapis.com/auth/calendar.readonly',

    'endpoints' => [
        'auth' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token' => 'https://oauth2.googleapis.com/token',
        'events' => 'https://www.googleapis.com/calendar/v3/calendars/:calendar/events',
    ],

    // How far back to offer sessions for logging. Counted from the last logged
    // session, capped here so a long silence does not drag in a whole year.
    'lookback_days' => (int) env('GOOGLE_CALENDAR_LOOKBACK_DAYS', 30),

    'token_leeway' => 60,
    'timeout' => 15,

];
