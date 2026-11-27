<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ChatConversationService
{
    /** Keep one support thread per student, regardless of how many tickets they create. */
    public function supportForStudent(User $student, array $additionalParticipants = []): Conversation
    {
        return DB::transaction(function () use ($student, $additionalParticipants): Conversation {
            User::query()->whereKey($student->id)->lockForUpdate()->first();

            $conversation = Conversation::query()
                ->where('type', 'support')
                ->whereNull('ticket_id')
                ->where('created_by_id', $student->id)
                ->first();
            $isNew = ! $conversation;

            if ($isNew) {
                $conversation = Conversation::create([
                    'type' => 'support',
                    'created_by_id' => $student->id,
                ]);
            }

            $managerIds = User::query()
                ->where('role', 'admin')
                ->where('is_deleted', false)
                ->pluck('id')
                ->all();
            $participants = array_values(array_unique([
                (int) $student->id,
                ...array_map('intval', $managerIds),
                ...array_map('intval', $additionalParticipants),
            ]));
            $conversation->participants()->syncWithoutDetaching($participants);

            if ($isNew) {
                ChatMessage::create([
                    'conversation_id' => $conversation->id,
                    'sender_type' => 'system',
                    'body' => 'Bạn đang trao đổi với bộ phận quản lý và nhân viên hỗ trợ.',
                    'metadata' => ['event' => 'support_started'],
                ]);
                $conversation->touch();
            }

            return $conversation->fresh();
        });
    }

    public function notifyTicketCreated(Ticket $ticket): void
    {
        $student = User::query()->find($ticket->reporter_id);
        if (! $student) {
            return;
        }

        $conversation = $this->supportForStudent($student);
        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'system',
            'body' => "Phiếu mới {$ticket->ticket_number}: {$ticket->title}. Quản lý vui lòng tiếp nhận và phân công nhân viên.",
            'metadata' => [
                'event' => 'ticket_created',
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'ticket_title' => $ticket->title,
            ],
        ]);
        $conversation->touch();
    }

    public function syncTicketAssignment(
        Ticket $ticket,
        User $manager,
        User $staff,
        bool $assignmentChanged = true,
    ): Conversation {
        $student = User::query()->findOrFail($ticket->reporter_id);
        $conversation = $this->supportForStudent($student, [$manager->id, $staff->id]);

        if ($assignmentChanged) {
            ChatMessage::create([
                'conversation_id' => $conversation->id,
                'sender_type' => 'system',
                'body' => "Phiếu {$ticket->ticket_number} đã được phân công cho {$staff->name}.",
                'metadata' => [
                    'event' => 'ticket_assigned',
                    'ticket_id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'ticket_title' => $ticket->title,
                    'staff_id' => $staff->id,
                    'staff_name' => $staff->name,
                ],
            ]);
            $conversation->touch();
        }

        return $conversation;
    }
}
