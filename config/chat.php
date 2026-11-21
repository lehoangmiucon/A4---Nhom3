<?php

use App\Services\UnavailableChatAssistant;

return [
    // Replace this adapter with a provider-backed ChatAssistant when AI is enabled.
    'assistant' => env('HELPDESK_CHAT_ASSISTANT', UnavailableChatAssistant::class),
];
