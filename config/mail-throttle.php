<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Maximum Release Delay
    |--------------------------------------------------------------------------
    |
    | The maximum delay (in seconds) before a throttled job is retried.
    | This caps the exponential backoff to prevent excessively long waits.
    |
    */
    'max_release_delay' => (int) env('MAIL_THROTTLE_MAX_RELEASE_DELAY', 30),

    /*
    |--------------------------------------------------------------------------
    | Maximum Backoff Multiplier
    |--------------------------------------------------------------------------
    |
    | Cap for exponential backoff multiplier. With default of 8:
    | Attempt 1: 1x base delay
    | Attempt 2: 2x base delay
    | Attempt 3: 4x base delay
    | Attempt 4+: 8x base delay (capped)
    |
    */
    'max_backoff_multiplier' => (int) env('MAIL_THROTTLE_MAX_BACKOFF', 8),

    /*
    |--------------------------------------------------------------------------
    | Jitter Percentage
    |--------------------------------------------------------------------------
    |
    | Random jitter added to release delays (0.0 to 1.0).
    | Prevents thundering herd when many workers release simultaneously.
    |
    | 0.5 = add 0-50% random jitter to each delay
    | 0.0 = disable jitter (not recommended at scale)
    |
    */
    'jitter_percent' => (float) env('MAIL_THROTTLE_JITTER', 0.5),

    /*
    |--------------------------------------------------------------------------
    | Fail Open
    |--------------------------------------------------------------------------
    |
    | When Redis is unavailable, should emails be sent anyway (fail open)
    | or should the job fail (fail closed)? Default: true (fail open).
    |
    | - true: Send email even if throttling fails (recommended for most apps)
    | - false: Throw exception if Redis is unavailable
    |
    */
    'fail_open' => (bool) env('MAIL_THROTTLE_FAIL_OPEN', true),

    /*
    |--------------------------------------------------------------------------
    | Key Prefix
    |--------------------------------------------------------------------------
    |
    | Prefix for Redis throttle keys. Prevents collisions when multiple
    | apps share the same Redis instance.
    |
    | Defaults to: cache.prefix -> app.name -> 'laravel'
    |
    */
    'key_prefix' => env('MAIL_THROTTLE_KEY_PREFIX'),
];
