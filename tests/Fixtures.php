<?php

namespace DevFactory\AiSpam\Tests;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContactReceived extends Notification
{
    use Queueable;

    public function __construct(public string $message) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->line($this->message);
    }
}

class SubscribeToNewsletter implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public string $email) {}

    public function handle(): void {}
}
