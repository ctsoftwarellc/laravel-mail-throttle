<?php

declare(strict_types=1);

namespace Ctsoftwarellc\MailThrottle\Middleware;

use Closure;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class ThrottleMail
{
    /**
     * @param  int|null  $maxAttempts  Override configured rate limit
     * @param  int|null  $perSeconds  Override configured time window
     * @param  string|null  $mailer  Explicit mailer to throttle against
     */
    public function __construct(
        protected ?int $maxAttempts = null,
        protected ?int $perSeconds = null,
        protected ?string $mailer = null,
    ) {}

    /**
     * Process the queued job through rate limiting.
     *
     * Supports both direct job calls and notification middleware signature.
     *
     * @param  object  $job  The job, notification, or mailable
     * @param  Closure(object): void  $next
     * @param  string|null  $channel  Notification channel (when called as notification middleware)
     */
    public function handle(object $job, Closure $next, ?string $channel = null): void
    {
        // For notifications, only throttle the mail channel
        if ($channel !== null && $channel !== 'mail') {
            $next($job);

            return;
        }

        $mailer = $this->resolveMailer($job);
        $config = config("mail.mailers.{$mailer}", []);

        // No rate limit configured = pass through (opt-in behavior)
        if (! $this->hasRateLimit($config)) {
            $next($job);

            return;
        }

        // Attempt throttling, fail-open if Redis unavailable and configured
        if (! $this->tryThrottle($job, $next, $mailer, $config)) {
            $next($job);
        }
    }

    /**
     * Attempt to throttle the job.
     *
     * @param  array<string, mixed>  $config
     * @return bool True if throttling was handled, false if it failed and should pass through
     */
    protected function tryThrottle(object $job, Closure $next, string $mailer, array $config): bool
    {
        try {
            $rate = $this->maxAttempts ?? (int) $config['rate_limit'];
            $perSeconds = $this->perSeconds ?? (int) ($config['rate_limit_per'] ?? 1);

            Redis::throttle($this->throttleKey($mailer))
                ->allow($rate)
                ->every($perSeconds)
                ->block(0)
                ->then(
                    fn () => $next($job),
                    function () use ($job, $rate, $perSeconds): void {
                        $delay = $this->calculateReleaseDelay($job, $rate, $perSeconds);
                        $job->release($delay);
                    }
                );

            return true;
        } catch (Throwable $e) {
            if (config('mail-throttle.fail_open', true)) {
                Log::warning('Mail throttle Redis error, failing open', [
                    'error' => $e->getMessage(),
                    'mailer' => $mailer,
                ]);

                return false;
            }

            throw $e;
        }
    }

    /**
     * Resolve the mailer from explicit config, job property, or default.
     */
    protected function resolveMailer(object $job): string
    {
        // 1. Explicit constructor override
        if ($this->mailer !== null) {
            return $this->mailer;
        }

        // 2. Mailable with explicit mailer set
        if ($job instanceof Mailable && $job->mailer !== null) {
            return $job->mailer;
        }

        // 3. Notification with mailer property (custom implementation)
        if ($job instanceof Notification && property_exists($job, 'mailer') && $job->mailer !== null) {
            return $job->mailer;
        }

        // 4. Check for SendQueuedMailable wrapper
        if (property_exists($job, 'mailable') && $job->mailable instanceof Mailable) {
            if ($job->mailable->mailer !== null) {
                return $job->mailable->mailer;
            }
        }

        // 5. Check for SendQueuedNotifications wrapper
        if (property_exists($job, 'notification') && $job->notification instanceof Notification) {
            if (property_exists($job->notification, 'mailer') && $job->notification->mailer !== null) {
                return $job->notification->mailer;
            }
        }

        // 6. Fall back to default
        return config('mail.default');
    }

    /**
     * Calculate release delay with dynamic backoff and jitter.
     *
     * Uses exponential backoff based on attempt count (capped) plus random
     * jitter to prevent thundering herd when many workers release at once.
     */
    protected function calculateReleaseDelay(object $job, int $rate, int $perSeconds): int
    {
        // Base delay: time for one slot to become available
        // e.g., 2 emails/sec = 500ms per slot = 1 second base
        $baseDelay = max(1, (int) ceil($perSeconds / $rate));

        // Get attempt count for backoff (if available)
        $attempts = method_exists($job, 'attempts') ? $job->attempts() : 1;

        // Exponential backoff: 1x, 2x, 4x, 8x... capped at configured max
        $maxMultiplier = (int) config('mail-throttle.max_backoff_multiplier', 8);
        $multiplier = min($maxMultiplier, pow(2, $attempts - 1));

        // Apply backoff
        $delay = $baseDelay * $multiplier;

        // Cap at configured maximum delay
        $maxDelay = (int) config('mail-throttle.max_release_delay', 30);
        $delay = min($delay, $maxDelay);

        // Add jitter: random 0-50% of delay to spread out retries
        $jitterPercent = (float) config('mail-throttle.jitter_percent', 0.5);
        if ($jitterPercent > 0) {
            $jitter = (int) round($delay * $jitterPercent * (mt_rand(0, 1000) / 1000));
            $delay += $jitter;
        }

        return max(1, $delay);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function hasRateLimit(array $config): bool
    {
        return isset($config['rate_limit']) && $config['rate_limit'] !== null;
    }

    /**
     * Generate prefixed throttle key to avoid collisions across apps.
     */
    protected function throttleKey(string $mailer): string
    {
        $prefix = config('mail-throttle.key_prefix')
            ?? config('cache.prefix')
            ?? config('app.name', 'laravel');

        return "{$prefix}:mail-throttle:{$mailer}";
    }
}
