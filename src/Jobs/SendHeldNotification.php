<?php

namespace DevFactory\AiSpam\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

/**
 * Sends a notification once its submission is approved. Going through the
 * facade (rather than SendQueuedNotifications) keeps Notification::fake() working.
 */
class SendHeldNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  list<string>|null  $channels
     */
    public function __construct(
        public mixed $notifiables,
        public BaseNotification $notification,
        public ?array $channels = null,
    ) {
        $this->onConnection($notification->connection ?? null);
        $this->onQueue($notification->queue ?? null);
    }

    public function handle(): void
    {
        Notification::sendNow($this->notifiables, $this->notification, $this->channels);
    }
}
