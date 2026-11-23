<?php

namespace App\Notifications;

use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TicketActivity extends Notification
{
    use Queueable;

    public function __construct(private readonly array $payload) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }
    public function toArray(object $notifiable): array
    {
        return $this->payload;
    }
}
