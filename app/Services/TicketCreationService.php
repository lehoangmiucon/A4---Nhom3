<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Room;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TicketCreationService
{
    public function create(User $actor, Room $room, Category $category, array $data, array $attachments = [], bool $deduplicate = true): array
    {
        return DB::transaction(function () use ($actor, $data, $room, $category, $attachments, $deduplicate) {
            Room::query()->whereKey($room->id)->lockForUpdate()->firstOrFail();
            $existingTicket = $deduplicate && in_array($actor->role, ['student', 'teacher'], true) ? Ticket::query()
                ->where('room_id', $room->id)
                ->where('category_id', $category->id)
                ->whereIn('status', ['new', 'reopened', 'assigned', 'in_progress'])
                ->latest()
                ->lockForUpdate()
                ->first() : null;
            if ($existingTicket) {
                return ['duplicate' => $existingTicket];
            }

            $ticket = Ticket::create([
                'ticket_number' => 'HD-'.now()->format('Ymd').'-'.strtoupper(bin2hex(random_bytes(3))),
                'reporter_id' => $actor->id,
                'reporter_name' => $actor->name,
                'reporter_email' => $actor->email,
                'reporter_phone' => $data['phone'] ?? null,
                'room_id' => $room->id,
                'room_label' => $room->name,
                'building_label' => $room->building?->name,
                'category_id' => $category->id,
                'equipment_label' => $category->name,
                'title' => $data['title'],
                'description' => $data['description'],
                'priority' => $actor->role === 'teacher' ? 'high' : $this->priority($data['priority'] ?? 'normal'),
                'status' => 'new',
            ]);
            TicketLog::create(['ticket_id' => $ticket->id, 'user_id' => $actor->id, 'to_status' => 'new', 'note' => 'Người báo sự cố đã gửi báo cáo.']);
            foreach ($attachments as $file) {
                $path = ImageStorage::attachment($file, $ticket, 'report');
                TicketAttachment::create(['ticket_id' => $ticket->id, 'uploaded_by_id' => $actor->id, 'path' => $path, 'original_name' => basename($path), 'attachment_type' => 'report']);
            }

            return ['ticket' => $ticket];
        });
    }

    private function priority(string $label): string
    {
        $label = trim(mb_strtolower($label));
        if ($label === 'low' || str_starts_with($label, 'thấp')) {
            return 'low';
        }
        if ($label === 'urgent' || str_starts_with($label, 'khẩn cấp') || str_contains($label, 'an toàn') || str_contains($label, 'sớm')) {
            return 'urgent';
        }
        if ($label === 'high' || str_starts_with($label, 'cao') || str_contains($label, 'cả lớp') || str_contains($label, 'phòng')) {
            return 'high';
        }

        return 'normal';
    }
}
