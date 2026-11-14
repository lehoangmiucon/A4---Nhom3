<?php

namespace App\Http\Controllers;

use App\Models\Building;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketSchedule;
use App\Support\PeriodRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApiDashboardController extends Controller
{
    private const STATUS_LABELS = [
        'new' => 'Chờ tiếp nhận',
        'assigned' => 'Đã tiếp nhận',
        'in_progress' => 'Đang xử lý',
        'resolved' => 'Chờ xác nhận',
        'closed' => 'Đã đóng',
        'reopened' => 'Mở lại',
    ];

    private function tickets(Request $request, string $role): array
    {
        $filters = $request->validate(['period' => ['nullable', Rule::in(array_keys(PeriodRange::OPTIONS))]]);
        $period = $filters['period'] ?? ($role === 'admin' ? 'month' : null);
        $query = Ticket::query();
        if ($role === 'customer') $query->where('reporter_id', $request->user()->id);
        if ($role === 'staff' && $request->user()->role !== 'admin') {
            $query->where('assigned_to_id', $request->user()->id);
        }
        if ($period) $query->whereBetween('created_at', PeriodRange::bounds($period));

        return [$query, $period];
    }

    private function summary(Request $request, Builder $query, ?string $period, bool $customer = false): array
    {
        $counts = array_replace(
            array_fill_keys(array_keys(self::STATUS_LABELS), 0),
            (clone $query)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->all()
        );
        $labels = self::STATUS_LABELS;
        if ($customer) {
            $labels['assigned'] = $labels['reopened'] = 'Đang xử lý';
        }
        return [
            'period' => $period,
            'period_title' => $period ? PeriodRange::title($period) : 'Tất cả thời gian',
            'period_options' => PeriodRange::OPTIONS,
            'status_labels' => $labels,
            'counts' => $counts,
            'total' => array_sum($counts),
            'unread_notifications' => $request->user()->unreadNotifications()->count(),
            'recent_tickets' => (clone $query)->with(['room.building', 'category', 'assignee:id,name', 'reporter:id,name,role'])
                ->latest()->limit(6)->get(),
        ];
    }

    public function admin(Request $request): JsonResponse
    {
        [$query, $period] = $this->tickets($request, 'admin');
        $data = $this->summary($request, $query, $period);
        $data['unassigned'] = (clone $query)->pendingAssignment()->count();
        $data['awaiting_confirmation'] = $data['counts']['resolved'];
        $data['in_progress'] = $data['counts']['in_progress'];
        $data['teacher_reports'] = (clone $query)
            ->whereHas('reporter', fn ($reporter) => $reporter->where('role', 'teacher'))->count();
        $data['categories'] = Category::whereHas('tickets', fn ($tickets) =>
            $tickets->whereBetween('created_at', PeriodRange::bounds($period))
        )->withCount(['tickets' => fn ($tickets) =>
            $tickets->whereBetween('created_at', PeriodRange::bounds($period))
        ])->orderByDesc('tickets_count')->limit(5)->get();
        $resolved = (clone $query)->whereNotNull('resolved_at')->get(['created_at', 'resolved_at']);
        $data['average_resolution_hours'] = round(($resolved->avg(
            fn ($ticket) => $ticket->created_at->diffInMinutes($ticket->resolved_at)
        ) ?? 0) / 60, 1);
        $data['buildings'] = Building::withCount('rooms')->with(['rooms' => fn ($rooms) =>
            $rooms->withCount(['tickets as open_tickets_count' => fn ($tickets) =>
                $tickets->whereBetween('created_at', PeriodRange::bounds($period))->where('status', '!=', 'closed')
            ])
        ])->orderBy('code')->get()->map(fn ($building) => [
            'id' => $building->id, 'code' => $building->code, 'name' => $building->name,
            'rooms_count' => $building->rooms_count,
            'open_tickets_count' => $building->rooms->sum('open_tickets_count'),
        ]);
        return response()->json(['data' => $data]);
    }

    public function staff(Request $request): JsonResponse
    {
        [$query, $period] = $this->tickets($request, 'staff');
        $data = $this->summary($request, $query, $period);
        $schedules = TicketSchedule::where('status', '!=', 'cancelled');
        if ($request->user()->role !== 'admin') $schedules->where('staff_id', $request->user()->id);
        $data['today_schedules'] = (clone $schedules)->with(['ticket.room.building', 'ticket.category'])
            ->whereDate('starts_at', today())->orderBy('starts_at')->get();
        $data['today_schedule_count'] = $data['today_schedules']->count();
        $data['pending_acknowledgements'] = (clone $schedules)->where('status', 'scheduled')->count();
        return response()->json(['data' => $data]);
    }

    public function customer(Request $request): JsonResponse
    {
        [$query, $period] = $this->tickets($request, 'customer');
        return response()->json(['data' => $this->summary($request, $query, $period, true)]);
    }
}
