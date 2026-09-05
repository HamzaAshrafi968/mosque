<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Database-only portal notification (title/body/url) stored in the standard
 * `notifications` table. Each role reads its own inbox through the shared
 * notifications UI; scope is handled by the page that links the notification.
 */
class PortalNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $url = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array{title: string, body: string, url: ?string} */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
        ];
    }
}
