<?php

namespace App\Services;

use App\Contracts\ChatAssistant;
use App\Models\Conversation;
use App\Models\User;

class UnavailableChatAssistant implements ChatAssistant
{
    public function reply(User $user, Conversation $conversation, string $message): string
    {
        return 'Trợ lý AI Helpdesk đã sẵn sàng tích hợp nhưng chưa được kết nối nhà cung cấp AI. Tin nhắn của bạn đã được lưu trong cuộc trò chuyện.';
    }
}
