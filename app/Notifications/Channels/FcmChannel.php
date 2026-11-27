<?php

namespace App\Notifications\Channels;

use App\Services\FcmPush;
use Illuminate\Notifications\Notification;

class FcmChannel
{
    public function __construct(private FcmPush $push) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $data = $notification->toArray($notifiable);
        $this->push->sendToUser(
            $notifiable->id,
            ($data['status'] ?? null) === 'new'
                ? 'Có phiếu sự cố mới'
                : 'Cập nhật phiếu sự cố',
            (string) ($data['message'] ?? 'Bạn có cập nhật mới về phiếu hỗ trợ.'),
            ['type' => 'ticket', 'ticket_id' => (string) ($data['ticket_id'] ?? '')],
        );
    }
}
