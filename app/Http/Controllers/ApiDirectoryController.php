<?php

namespace App\Http\Controllers;

use App\Models\Building;
use App\Models\Room;
use App\Models\Ticket;
use App\Models\TicketSchedule;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApiDirectoryController extends Controller
{
    private function pageSize(Request $request): int
    {
        return $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']])['per_page'] ?? 15;
    }

    public function team(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'role' => ['nullable', Rule::in(['admin', 'staff'])],
            'status' => ['nullable', Rule::in(['active', 'unverified', 'locked'])],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);
        $query = User::whereIn('role', ['admin', 'staff'])->with('categories:id,name,color')->withCount('assignedTickets');
        if (!empty($filters['role'])) $query->where('role', $filters['role']);
        if (!empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(fn ($users) => $users->where('name', 'like', $search)
                ->orWhere('email', 'like', $search)->orWhere('phone', 'like', $search));
        }
        if (!empty($filters['category_id'])) {
            $query->whereHas('categories', fn ($categories) => $categories->whereKey($filters['category_id']));
        }
        if (!empty($filters['status'])) {
            match ($filters['status']) {
                'locked' => $query->where('is_deleted', true),
                'unverified' => $query->where('is_deleted', false)->whereNull('email_verified_at'),
                'active' => $query->where('is_deleted', false)->whereNotNull('email_verified_at'),
            };
        }
        return response()->json($query->orderBy('name')->paginate($this->pageSize($request))->withQueryString());
    }

    public function userSummary(): JsonResponse
    {
        $users = User::whereIn('role', ['student', 'teacher']);
        return response()->json(['data' => [
            'total' => (clone $users)->count(),
            'students' => (clone $users)->where('role', 'student')->count(),
            'teachers' => (clone $users)->where('role', 'teacher')->count(),
            'locked' => (clone $users)->where('is_deleted', true)->count(),
            'verified' => (clone $users)->whereNotNull('email_verified_at')->count(),
            'monthly_tickets' => Ticket::whereBetween('created_at', \App\Support\PeriodRange::bounds('month'))
                ->whereHas('reporter', fn ($reporter) => $reporter->whereIn('role', ['student', 'teacher']))->count(),
        ]]);
    }

    public function teamSummary(): JsonResponse
    {
        $users = User::whereIn('role', ['admin', 'staff']);
        return response()->json(['data' => [
            'total' => (clone $users)->count(),
            'admins' => (clone $users)->where('role', 'admin')->count(),
            'staff' => (clone $users)->where('role', 'staff')->count(),
            'verified' => (clone $users)->whereNotNull('email_verified_at')->count(),
            'locked' => (clone $users)->where('is_deleted', true)->count(),
        ]]);
    }

    public function user(User $user): JsonResponse
    {
        $user->loadCount(['reportedTickets', 'assignedTickets'])->load('categories:id,name,color');
        return response()->json(['data' => [
            'user' => $user,
            'reported_counts' => $user->reportedTickets()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'assigned_counts' => $user->assignedTickets()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'schedules_count' => TicketSchedule::where('staff_id', $user->id)->count(),
        ]]);
    }

    public function teamMember(User $user): JsonResponse
    {
        abort_unless(in_array($user->role, ['admin', 'staff'], true), 404);
        return $this->user($user);
    }

    public function reportedTickets(Request $request, User $user): JsonResponse
    {
        return response()->json($user->reportedTickets()->with(['room.building', 'category', 'assignee:id,name'])
            ->latest()->paginate($this->pageSize($request))->withQueryString());
    }

    public function assignedTickets(Request $request, User $user): JsonResponse
    {
        abort_unless(in_array($user->role, ['admin', 'staff'], true), 404);
        return response()->json($user->assignedTickets()->with(['room.building', 'category', 'assignee:id,name'])
            ->latest()->paginate($this->pageSize($request))->withQueryString());
    }

    public function buildingOptions(): JsonResponse
    {
        return response()->json(['data' => Building::with('rooms')->orderBy('code')->get()]);
    }

    public function locationsSummary(): JsonResponse
    {
        return response()->json(['data' => ['buildings_count' => Building::count(), 'rooms_count' => Room::count()]]);
    }

    public function building(Building $building): JsonResponse
    {
        return response()->json(['data' => $building->load(['rooms' => fn ($rooms) =>
            $rooms->with('equipment')->withCount('tickets')->orderBy('code')
        ])->loadCount('rooms')]);
    }

    public function room(Request $request, Room $room): JsonResponse
    {
        return response()->json(['data' => [
            'room' => $room->load(['building', 'equipment'])->loadCount('tickets'),
            'tickets' => Ticket::with(['category', 'assignee:id,name'])->where('room_id', $room->id)
                ->latest()->paginate($this->pageSize($request))->withQueryString(),
            'report_url' => url('/r/'.rawurlencode($room->qr_code ?: $room->code)),
        ]]);
    }
}
