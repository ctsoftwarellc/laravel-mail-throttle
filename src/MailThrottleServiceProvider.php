<?php

declare(strict_types=1);

namespace Ctsoftwarellc\MailThrottle;

use Illuminate\Support\ServiceProvider;

class MailThrottleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mail-throttle.php', 'mail-throttle');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/mail-throttle.php' => config_path('mail-throttle.php'),
            ], 'mail-throttle-config');
        }
    }
}
