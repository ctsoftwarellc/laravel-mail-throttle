<?php

declare(strict_types=1);

namespace Ctsoftwarellc\MailThrottle\Concerns;

use Ctsoftwarellc\MailThrottle\Middleware\ThrottleMail;

/**
 * Add rate limiting to queued notifications and mailables.
 *
 * Requires:
 * - Redis installed and configured
 * - `rate_limit` set in config/mail.php for your mailer
 *
 * Example config/mail.php:
 * ```
 * 'resend' => [
 *     'transport' => 'resend',
 *     'rate_limit' => 2,      // 2 emails
 *     'rate_limit_per' => 1,  // per 1 second
 * ],
 * ```
 */
trait ThrottlesMail
{
    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            new ThrottleMail,
            ...$this->additionalMiddleware(),
        ];
    }

    /**
     * Override to add more middleware while keeping throttling.
     *
     * @return array<int, object>
     */
    protected function additionalMiddleware(): array
    {
        return [];
    }
}
