<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\Ticket;
use App\Models\User;

class ChatConversationService
{
    public function syncTicketAssignment(Ticket $ticket, User $manager, User $staff): Conversation
    {
        $conversation = Conversation::firstOrNew([
            'type' => 'ticket',
            'ticket_id' => $ticket->id,
        ]);
        $isNew = ! $conversation->exists;
        $previousParticipants = $conversation->exists
            ? $conversation->participants()->pluck('users.id')->map(fn ($id) => (int) $id)->all()
            : [];

        if ($isNew) {
            $conversation->created_by_id = $manager->id;
        }
        $conversation->save();
        $conversation->participants()->sync([$manager->id, $staff->id]);

        if ($isNew || ! in_array((int) $staff->id, $previousParticipants, true)) {
            ChatMessage::create([
                'conversation_id' => $conversation->id,
                'sender_type' => 'system',
                'body' => "Phiếu {$ticket->ticket_number} được phân công cho {$staff->name}.",
            ]);
            $conversation->touch();
        }

        return $conversation;
    }
}
