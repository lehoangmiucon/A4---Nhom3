<?php

namespace App\Http\Controllers;

use App\Models\TicketSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApiScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
            'staff_id' => ['nullable', 'integer', 'exists:users,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'ticket_status' => ['nullable', Rule::in(['new', 'assigned', 'in_progress', 'resolved', 'closed', 'reopened'])],
            'status' => ['nullable', Rule::in(['scheduled', 'acknowledged', 'cancelled'])],
            'include_cancelled' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $query = TicketSchedule::with(['ticket.room.building', 'ticket.category', 'staff:id,name']);
        if ($request->user()->role !== 'admin') {
            $query->where('staff_id', $request->user()->id);
        } elseif (!empty($filters['staff_id'])) {
            $query->where('staff_id', $filters['staff_id']);
        }
        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        elseif (!$request->boolean('include_cancelled')) $query->where('status', '!=', 'cancelled');
        if (!empty($filters['from'])) $query->where('starts_at', '>=', $filters['from'].' 00:00:00');
        if (!empty($filters['to'])) $query->where('starts_at', '<=', $filters['to'].' 23:59:59');
        if (!empty($filters['category_id'])) {
            $query->whereHas('ticket', fn ($ticket) => $ticket->where('category_id', $filters['category_id']));
        }
        if (!empty($filters['ticket_status'])) {
            $query->whereHas('ticket', fn ($ticket) => $ticket->where('status', $filters['ticket_status']));
        }
        return response()->json($query->orderBy('starts_at')->orderBy('id')
            ->paginate($filters['per_page'] ?? 50)->withQueryString());
    }

    public function show(Request $request, TicketSchedule $schedule): JsonResponse
    {
        abort_unless($request->user()->role === 'admin'
            || (int) $schedule->staff_id === (int) $request->user()->id, 403);
        return response()->json(['data' => $schedule->load(['ticket.room.building', 'ticket.category', 'staff:id,name'])]);
    }
}
