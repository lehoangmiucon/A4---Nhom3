<?php

namespace App\Contracts;

use App\Models\Conversation;
use App\Models\User;

interface ChatAssistant
{
    public function reply(User $user, Conversation $conversation, string $message): string;
}
