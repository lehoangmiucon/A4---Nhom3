<?php

namespace App\Services\Support;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Room;
use App\Models\SupportReport;
use App\Models\SupportSession;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketActivity;
use App\Services\TicketCreationService;
use Illuminate\Support\Facades\Notification;

class ConversationReportService
{
    // Called within the command's completion transaction; database is the only notification channel.
    public function create(SupportSession $session, User $actor): ?SupportReport
    {
        if ($report = SupportReport::where('session_id', $session->id)->first()) {
            return $report;
        }
        $room = Room::with('building')->findOrFail($session->room_id);
        $category = Category::findOrFail($session->category_id);
        $transcript = $session->messages()->orderBy('id')->get(['role', 'content_text', 'created_at'])->toArray();
        $facts = app(ConversationContext::class)->incidentFacts($session);
        $description = app(ConversationBrief::class)->description($session);
        $result = app(TicketCreationService::class)->create($actor, $room, $category, [
            'title' => app(ConversationFallback::class)->build($facts, $category->name)['title'],
            'description' => $description,
        ]);
        if (isset($result['duplicate'])) {
            return null;
        }
        $ticket = $result['ticket'];
        $report = SupportReport::create(['session_id' => $session->id, 'ticket_id' => $ticket->id, 'transcript' => $transcript]);
        ActivityLog::create(['user_id' => $actor->id, 'action' => 'ticket.created', 'subject_type' => Ticket::class,
            'subject_id' => $ticket->id, 'description' => 'Report submitted from saved support conversation.',
            'properties' => ['session_id' => $session->id, 'report_id' => $report->id, 'ticket_number' => $ticket->ticket_number]]);
        Notification::send(User::where('role', 'admin')->where('is_deleted', false)->get(), new TicketActivity([
            'ticket_id' => $ticket->id, 'ticket_number' => $ticket->ticket_number, 'title' => $ticket->title,
            'status' => 'new', 'message' => 'Có báo cáo từ cuộc trò chuyện AI cần tiếp nhận.',
        ]));
        $session->update(['status' => 'handed_off', 'report_requested' => false]);

        return $report;
    }
}
