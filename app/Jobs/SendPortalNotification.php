<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\PortalNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Delivers a portal notification to a chunk of users on the `notifications`
 * queue. Roster fan-outs (exam publish, announcements, postponements,
 * payroll) dispatch one job per 500 recipients, so the HTTP request never
 * waits for the fan-out. Tests run with QUEUE_CONNECTION=sync and can still
 * use Notification::fake() because delivery goes through notify().
 */
class SendPortalNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<int|string>  $userIds
     */
    public function __construct(
        public array $userIds,
        public string $title,
        public string $body,
        public ?string $url = null,
    ) {
        $this->queue = 'notifications';
        $this->tries = 3;
    }

    public function handle(): void
    {
        User::query()
            ->whereIn('id', collect($this->userIds)->map(fn ($id) => (string) $id)->unique()->values())
            ->get()
            ->each->notify(new PortalNotification($this->title, $this->body, $this->url));
    }
}
