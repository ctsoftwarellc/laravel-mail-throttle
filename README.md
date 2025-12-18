# Laravel Mail Throttle

Distributed rate limiting for Laravel mail across multiple queue workers.

## Problem

Email providers have rate limits:
- **Resend**: 2/sec (free), 100/sec (pro)
- **Postmark**: 10/sec
- **SES**: 14/sec (default)
- **Mailgun**: Varies by plan

Their SDKs don't handle this. When you dispatch 1,000 notifications, your queue workers blast them out and you hit 429 errors.

## Solution

This package provides:
- **Distributed throttling** via Redis - works across multiple Horizon workers
- **Opt-in per class** - add a trait to enable throttling
- **Config in mail.php** - rate limits live with your mailer config
- **Fail-safe** - configurable behavior when Redis is unavailable

## Requirements

- PHP 8.2+
- Laravel 10, 11, or 12
- Redis (required for distributed throttling)

## Installation

```bash
composer require ctsoftwarellc/laravel-mail-throttle
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag=mail-throttle-config
```

## Configuration

Add rate limits to your mailers in `config/mail.php`:

```php
'mailers' => [
    'resend' => [
        'transport' => 'resend',
        'rate_limit' => env('MAIL_RATE_LIMIT', 2),        // emails per window
        'rate_limit_per' => env('MAIL_RATE_LIMIT_PER', 1), // window in seconds
    ],

    'postmark' => [
        'transport' => 'postmark',
        'rate_limit' => 10,
        'rate_limit_per' => 1,
    ],

    // No rate_limit = no throttling (existing behavior preserved)
    'smtp' => [
        'transport' => 'smtp',
    ],
],
```

## Usage

### Notifications

Add the `ThrottlesMail` trait to your queued notifications:

```php
<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Ctsoftwarellc\MailThrottle\Concerns\ThrottlesMail;

class WelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable, ThrottlesMail;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome!')
            ->line('Thanks for joining.');
    }
}
```

### Mailables

Add the trait to your queued mailables:

```php
<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Ctsoftwarellc\MailThrottle\Concerns\ThrottlesMail;

class InvoiceMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, ThrottlesMail;

    public function content(): Content
    {
        return new Content(view: 'emails.invoice');
    }
}
```

### Custom Rate Limits

Override the config-based limits per class:

```php
use Ctsoftwarellc\MailThrottle\Middleware\ThrottleMail;

class BulkNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function middleware(): array
    {
        // 1 email per 2 seconds for bulk sends
        return [new ThrottleMail(maxAttempts: 1, perSeconds: 2)];
    }
}
```

### Specify a Different Mailer

```php
public function middleware(): array
{
    // Use postmark's rate limit config
    return [new ThrottleMail(mailer: 'postmark')];
}
```

## How It Works

```
+-------------+     +-------------+     +-------------+
|  Worker 1   |     |  Worker 2   |     |  Worker 3   |
+------+------+     +------+------+     +------+------+
       |                   |                   |
       +-------------------+-------------------+
                           |
                           v
                 +-------------------+
                 |   Redis Throttle  |
                 |  (Atomic Counter) |
                 +-------------------+
                           |
           +---------------+---------------+
           v                               v
   +---------------+               +---------------+
   | Under limit   |               | At limit      |
   | -> Send email |               | -> Release    |
   +---------------+               |    (retry 1s) |
                                   +---------------+
```

All workers share the same Redis key per mailer, so the rate limit is global across your entire application.

## Package Config

Publish with `php artisan vendor:publish --tag=mail-throttle-config`

```php
return [
    // Seconds to wait before retrying a throttled job
    'release_delay' => env('MAIL_THROTTLE_RELEASE_DELAY', 1),

    // Send emails anyway if Redis is unavailable?
    'fail_open' => env('MAIL_THROTTLE_FAIL_OPEN', true),

    // Redis key prefix (defaults to cache.prefix or app.name)
    'key_prefix' => env('MAIL_THROTTLE_KEY_PREFIX'),
];
```

## Multi-Channel Notifications

The middleware only throttles the `mail` channel. Other channels (SMS, Slack, database) pass through without throttling:

```php
public function via(object $notifiable): array
{
    return ['mail', 'database', 'vonage']; // Only mail is throttled
}
```

## License

MIT License. See [LICENSE](LICENSE) for details.
