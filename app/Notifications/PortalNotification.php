<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Database-only portal notification (title/body/url) stored in the standard
 * `notifications` table. Each role reads its own inbox through the shared
 * notifications UI; scope is handled by the page that links the notification.
 *
 * Queued on the dedicated `notifications` queue so roster fan-outs (exam
 * publish, announcements, postponements) never block the HTTP request. Tests
 * run with QUEUE_CONNECTION=sync and stay synchronous.
 */
class PortalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $url = null,
    ) {
        // Dedicated queue so fan-outs don't congest the default worker.
        $this->queue = 'notifications';
    }

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
