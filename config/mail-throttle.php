<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Release Delay
    |--------------------------------------------------------------------------
    |
    | When a job is throttled, it will be released back to the queue after
    | this many seconds. This should be short to maintain throughput.
    | The delay will never exceed the rate limit window (rate_limit_per).
    |
    */
    'release_delay' => (int) env('MAIL_THROTTLE_RELEASE_DELAY', 1),

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
