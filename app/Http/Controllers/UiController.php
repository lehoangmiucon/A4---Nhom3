<?php

namespace App\Http\Controllers;

use App\Models\Building;
use App\Models\Category;
use App\Models\Room;
use App\Models\Ticket;
use App\Models\TicketSchedule;
use App\Models\User;
use App\Support\PeriodRange;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class UiController extends Controller
{
    public function page(Request $request, string $section, ?string $path)
    {
        $path = trim($path ?: 'index', '/');
        if (str_ends_with($path, '.html')) {
            $cleanPath = substr($path, 0, -5);
            return redirect(url('/'.$section.'/'.$cleanPath).($request->getQueryString() ? '?'.$request->getQueryString() : ''), 301);
        }
        if (!$request->user()) return redirect(url('/auth/login'));
        abort_if($request->user()->is_deleted, 403, 'Tai khoan da bi khoa.');
        $allowedRoles = match ($section) {
            'admin' => ['admin'],
            'staff' => ['admin', 'staff'],
            'customer' => ['admin', 'staff', 'student', 'teacher'],
            default => [],
        };
        abort_unless(in_array($request->user()->role, $allowedRoles, true), 403);
        return $this->show($request, $section, $path);
    }

    public function show(Request $request, string $section, ?string $path = null)
    {
        $path = trim($path ?: 'index', '/');
        abort_unless(!str_contains($path, '..'), 404);

        $view = str_replace('/', '.', $section . '/' . $path);
        abort_unless(View::exists($view), 404);

        $data = [
            'currentUser' => $request->user(),
            'pagePath' => $path,
            'pendingTicketCount' => Ticket::whereIn('status', ['new', 'reopened'])->count(),
            'unreadNotificationCount' => $request->user()?->unreadNotifications()->count() ?? 0,
            'statusLabels' => [
                'new' => 'Chờ tiếp nhận',
                'assigned' => 'Đã tiếp nhận',
                'in_progress' => 'Đang xử lý',
                'resolved' => 'Chờ xác nhận',
                'closed' => 'Đã đóng',
                'reopened' => 'Mở lại',
            ],
        ];
        if ($section === 'customer') {
            // Keep the internal reopen state hidden from student-facing pages.
            $data['statusLabels']['assigned'] = 'Đang xử lý';
            $data['statusLabels']['reopened'] = 'Đang xử lý';
        }
        if ($section === 'admin' && $request->user()) {
            $recentNotifications = $request->user()->notifications()->latest()->limit(100)->get();
            $data['notificationPreviewGroups'] = $this->groupNotifications($recentNotifications)->take(5);
        }
        if ($section === 'admin' && $path === 'index') {
            $period = PeriodRange::normalize($request->query('period', 'month'));
            $periodOptions = PeriodRange::OPTIONS;
            [$periodStart, $periodEnd] = PeriodRange::bounds($period);

            $data['dashboardPeriod'] = $period;
            $data['dashboardPeriodOptions'] = $periodOptions;
            $data['dashboardPeriodTitle'] = PeriodRange::title($period);
            $periodTickets = Ticket::whereBetween('created_at', [$periodStart, $periodEnd]);
            $data['dashboardCounts'] = (clone $periodTickets)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
            $data['dashboardTotal'] = (clone $periodTickets)->count();
            $data['dashboardTodayCount'] = $data['dashboardTotal'];
            $data['dashboardUnassignedCount'] = (clone $periodTickets)->whereIn('status', ['new', 'reopened'])->count();
            $data['dashboardTeacherCount'] = (clone $periodTickets)
                ->whereHas('reporter', fn ($reporter) => $reporter->where('role', 'teacher'))->count();
            $data['dashboardResolvedCount'] = $data['dashboardCounts']['resolved'] ?? 0;
            $data['dashboardInProgressCount'] = $data['dashboardCounts']['in_progress'] ?? 0;
            $data['weeklyTicketCount'] = $data['dashboardTotal'];
            $data['recentTickets'] = Ticket::with(['room.building', 'assignee', 'category'])
                ->whereBetween('created_at', [$periodStart, $periodEnd])->latest()->take(6)->get();
            $data['equipmentBreakdown'] = Category::query()
                ->withCount(['tickets as tickets_count' => fn($query) => $query->whereBetween('created_at', [$periodStart, $periodEnd])])
                ->whereHas('tickets', fn($query) => $query->whereBetween('created_at', [$periodStart, $periodEnd]))
                ->orderByDesc('tickets_count')->take(5)->get();
            $data['dashboardBuildings'] = Building::with(['rooms.tickets' => fn($query) => $query->whereBetween('created_at', [$periodStart, $periodEnd])])
                ->withCount('rooms')->orderBy('code')->get();
        }
        if ($section === 'admin' && $path === 'pages/reports') {
            $period = PeriodRange::normalize($request->query('period', 'month'));
            [$periodStart, $periodEnd] = PeriodRange::bounds($period);
            $data['reportPeriod'] = $period;
            $data['reportPeriodOptions'] = PeriodRange::OPTIONS;
            $data['reportPeriodTitle'] = PeriodRange::title($period);
            $periodTickets = Ticket::whereBetween('created_at', [$periodStart, $periodEnd]);
            $data['reportCounts'] = (clone $periodTickets)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
            $data['totalTickets'] = (clone $periodTickets)->count();
            $data['resolvedTickets'] = (clone $periodTickets)->whereIn('status', ['resolved', 'closed'])->count();
            $data['equipmentBreakdown'] = Category::query()
                ->withCount(['tickets as tickets_count' => fn($query) => $query->whereBetween('created_at', [$periodStart, $periodEnd])])
                ->whereHas('tickets', fn($query) => $query->whereBetween('created_at', [$periodStart, $periodEnd]))
                ->orderByDesc('tickets_count')->take(5)->get();
            $resolved = (clone $periodTickets)->whereNotNull('resolved_at')->get(['created_at', 'resolved_at']);
            $averageMinutes = $resolved->avg(fn($ticket) => $ticket->created_at->diffInMinutes($ticket->resolved_at));
            $data['averageResolutionHours'] = number_format(($averageMinutes ?? 0) / 60, 1, ',', '');
        }
        $this->addPageData($request, $section, $path, $data);

        return response()->view($view, $data);
    }

    public function reportSummary(Request $request)
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $period = PeriodRange::normalize($request->query('period', 'month'));
        [$periodStart, $periodEnd] = PeriodRange::bounds($period);
        $periodTickets = Ticket::whereBetween('created_at', [$periodStart, $periodEnd]);
        $counts = (clone $periodTickets)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $total = (clone $periodTickets)->count();
        $teacherReports = (clone $periodTickets)
            ->whereHas('reporter', fn ($reporter) => $reporter->where('role', 'teacher'))->count();
        $resolved = (clone $periodTickets)->whereNotNull('resolved_at')->get(['created_at', 'resolved_at']);
        $averageMinutes = $resolved->avg(fn($ticket) => $ticket->created_at->diffInMinutes($ticket->resolved_at));
        $categories = Category::query()
            ->withCount(['tickets as tickets_count' => fn($query) => $query->whereBetween('created_at', [$periodStart, $periodEnd])])
            ->whereHas('tickets', fn($query) => $query->whereBetween('created_at', [$periodStart, $periodEnd]))
            ->orderByDesc('tickets_count')->take(5)->get(['id', 'name', 'color']);

        return response()->json([
            'period' => $period,
            'period_title' => PeriodRange::title($period),
            'total' => $total,
            'teacher_reports' => $teacherReports,
            'closed' => (int) ($counts['closed'] ?? 0),
            'resolved' => (int) ($counts['resolved'] ?? 0) + (int) ($counts['closed'] ?? 0),
            'average_hours' => number_format(($averageMinutes ?? 0) / 60, 1, ',', ''),
            'counts' => $counts,
            'categories' => $categories,
            'max_category_count' => (int) ($categories->max('tickets_count') ?? 0),
        ]);
    }

    private function addPageData(Request $request, string $section, string $path, array &$data): void
    {
        $user = $request->user();

        if ($section === 'admin' && $path === 'pages/incidents') {
            $focus = $request->query('focus');
            $focusLabels = [
                'today' => 'Phiếu được gửi hôm nay',
                'unassigned' => 'Phiếu chờ phân công',
                'assigned' => 'Phiếu đã phân công, chờ nhân viên bắt đầu',
                'in_progress' => 'Phiếu đang xử lý',
                'resolved' => 'Phiếu nhân viên đã hoàn thành, chờ quản lý xác nhận',
            ];
            if (! is_string($focus) || ! array_key_exists($focus, $focusLabels)) {
                $focus = null;
            }
            $ticketQuery = Ticket::with(['room.building', 'assignee', 'schedules', 'reporter:id,name,role', 'category']);
            match ($focus) {
                'today' => $ticketQuery->whereDate('created_at', today()),
                'unassigned' => $ticketQuery->whereIn('status', ['new', 'reopened']),
                'assigned' => $ticketQuery->where('status', 'assigned'),
                'in_progress' => $ticketQuery->where('status', 'in_progress'),
                'resolved' => $ticketQuery->where('status', 'resolved'),
                default => null,
            };
            $data['tickets'] = $ticketQuery->latest()->get();
            $data['incidentFocus'] = $focus;
            $data['incidentFocusLabel'] = $focus ? $focusLabels[$focus] : null;
            $data['incidentCounts'] = [
                'all' => Ticket::count(),
                'pending' => Ticket::whereIn('status', ['new', 'reopened'])->count(),
                'in_progress' => Ticket::where('status', 'in_progress')->count(),
                'completed_month' => Ticket::whereIn('status', ['resolved', 'closed'])
                    ->whereMonth('resolved_at', now()->month)->whereYear('resolved_at', now()->year)->count(),
            ];
            $data['incidentEquipmentLabels'] = Ticket::query()->whereNotNull('equipment_label')->distinct()->orderBy('equipment_label')->pluck('equipment_label');
            $data['incidentBuildings'] = Building::orderBy('name')->get(['code', 'name']);
        }
        if ($section === 'admin' && $path === 'pages/notifications') {
            $data['notifications'] = $user->notifications()->latest()->get();
            $data['notificationGroups'] = $this->groupNotifications($data['notifications']);
            $ticketIds = $data['notifications']
                ->map(fn($notification) => $notification->data['ticket_id'] ?? null)
                ->filter()
                ->unique()
                ->values();
            $data['notificationTickets'] = Ticket::with('category:id,name,color')
                ->whereIn('id', $ticketIds)
                ->get()
                ->keyBy('id');
        }
        if ($section === 'admin' && $path === 'pages/locations') {
            $data['buildings'] = Building::with('rooms.tickets')->withCount('rooms')->orderBy('code')->get();
            $data['roomCount'] = Room::count();
        }
        if ($section === 'admin' && $path === 'pages/building-detail') {
            $data['building'] = Building::with(['rooms.equipment', 'rooms.tickets'])->where('code', $request->query('code', 'A'))->firstOrFail();
        }
        if ($section === 'admin' && $path === 'pages/room-detail') {
            $data['room'] = Room::with(['building', 'equipment', 'tickets'])->findOrFail($request->integer('room'));
        }
        if ($section === 'admin' && in_array($path, ['pages/ticket-detail', 'pages/ticket-review'], true)) {
            $data['ticket'] = Ticket::with(['room.building', 'room.equipment', 'category', 'reporter', 'assignee', 'reportAttachments', 'repairAttachments', 'logs.user', 'schedules'])
                ->findOrFail($request->integer('ticket'));
            if ($path === 'pages/ticket-detail') {
                $staffQuery = User::where('role', 'staff')->where('is_deleted', false);
                if ($data['ticket']->category_id) {
                    $staffQuery->whereHas('categories', fn($query) => $query->whereKey($data['ticket']->category_id));
                }
                $data['staffMembers'] = $staffQuery->orderBy('name')->get();
            }
        }
        if ($section === 'admin' && $path === 'pages/schedule') {
            $data['schedules'] = TicketSchedule::with(['ticket.room.building', 'ticket.category', 'staff'])->where('status', '!=', 'cancelled')->orderBy('starts_at')->get();
            $data['scheduleEvents'] = $data['schedules']->map(fn ($schedule) => [
                'id' => $schedule->id,
                'ticket_id' => $schedule->ticket_id,
                'ticket_number' => $schedule->ticket->ticket_number,
                'title' => $schedule->ticket->title,
                'room' => $schedule->ticket->room?->code,
                'date' => $schedule->starts_at->format('Y-m-d'),
                'time' => $schedule->starts_at->format('H:i'),
                'staff_id' => $schedule->staff_id,
                'staff_name' => $schedule->staff->name,
                'category_id' => $schedule->ticket->category_id,
                'category_name' => $schedule->ticket->category?->name ?? 'Chưa phân loại',
                'category_color' => $schedule->ticket->category?->color ?? '#64748B',
                'ticket_status' => $schedule->ticket->status,
                'schedule_status' => $schedule->status,
            ])->values();
            $data['staffMembers'] = User::with('categories:id,name,color')->where('role', 'staff')->where('is_deleted', false)->orderBy('name')->get();
            $data['tickets'] = Ticket::with('category')
                ->whereIn('status', ['new', 'reopened'])
                ->whereNull('assigned_to_id')
                ->whereDoesntHave('schedules', fn($query) => $query->where('status', '!=', 'cancelled'))
                ->latest()
                ->get();
            $data['categories'] = Category::orderBy('name')->get();
            $data['scheduleCounts'] = [
                'today' => TicketSchedule::where('status', '!=', 'cancelled')->whereDate('starts_at', today())->count(),
                'unassigned' => Ticket::whereIn('status', ['new', 'reopened'])
                    ->whereNull('assigned_to_id')
                    ->whereDoesntHave('schedules', fn($query) => $query->where('status', '!=', 'cancelled'))
                    ->count(),
                'staff' => \App\Models\User::where('role', 'staff')->where('is_deleted', false)->count(),
                'completed_today' => Ticket::whereIn('status', ['resolved', 'closed'])->whereDate('resolved_at', today())->count(),
            ];
        }
        if ($section === 'admin' && $path === 'pages/categories') {
            $data['categories'] = Category::orderBy('name')->get();
        }
        if ($section === 'admin' && $path === 'pages/users') {
            $data['users'] = User::whereIn('role', ['student', 'teacher'])->withCount('reportedTickets')->orderBy('name')->get();
            $data['monthlyTicketCount'] = Ticket::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->whereHas('reporter', fn($query) => $query->whereIn('role', ['student', 'teacher']))
                ->count();
        }
        if ($section === 'admin' && $path === 'pages/user-detail') {
            $data['student'] = User::whereIn('role', ['student', 'teacher'])
                ->withCount('reportedTickets')
                ->findOrFail($request->integer('user'));
            $data['reportedTicketPage'] = Ticket::with(['room.building', 'category', 'assignee'])
                ->where('reporter_id', $data['student']->id)->latest()->paginate(15)->withQueryString();
            $data['reportedTicketsInProgress'] = Ticket::where('reporter_id', $data['student']->id)
                ->whereIn('status', ['assigned', 'in_progress'])->count();
            $data['reportedTicketsResolved'] = Ticket::where('reporter_id', $data['student']->id)
                ->whereIn('status', ['resolved', 'closed'])->count();
        }
        if ($section === 'admin' && $path === 'pages/team-detail') {
            $data['teamMember'] = User::whereIn('role', ['admin', 'staff'])
                ->with('categories:id,name,color')
                ->withCount('assignedTickets')
                ->findOrFail($request->integer('user'));
            $data['assignedTicketPage'] = Ticket::with(['room.building', 'category', 'assignee'])
                ->where('assigned_to_id', $data['teamMember']->id)->latest()->paginate(15)->withQueryString();
            $data['teamScheduleCount'] = $data['teamMember']->role === 'staff'
                ? TicketSchedule::where('staff_id', $data['teamMember']->id)->count()
                : 0;
            $data['teamSchedules'] = $data['teamMember']->role === 'staff'
                ? TicketSchedule::with(['ticket.room.building', 'ticket.category'])
                ->where('staff_id', $data['teamMember']->id)
                ->orderByDesc('starts_at')->take(10)->get()
                : collect();
        }
        if ($section === 'admin' && $path === 'pages/team') {
            $data['teamUsers'] = User::with('categories:id,name,color')->withCount('assignedTickets')->whereIn('role', ['admin', 'staff'])->orderBy('role')->orderBy('name')->get();
            $data['managementUsers'] = $data['teamUsers']->where('role', 'admin');
            $data['staffUsers'] = $data['teamUsers']->where('role', 'staff');
            $data['categories'] = Category::orderBy('name')->get(['id', 'name', 'color']);
        }
        if ($section === 'customer' && $path === 'tickets') {
            $data['categories'] = Category::orderBy('name')->get(['id', 'name', 'color']);
            $selectedCategoryId = $request->query('category_id');
            $ticketQuery = Ticket::with(['room.building', 'category'])->where('reporter_id', $user->id);
            if (is_scalar($selectedCategoryId) && ctype_digit((string) $selectedCategoryId)) {
                $ticketQuery->where('category_id', (int) $selectedCategoryId);
            } else {
                $selectedCategoryId = '';
            }
            $searchInput = $request->query('q');
            $ticketSearch = is_string($searchInput) ? trim($searchInput) : '';
            if ($ticketSearch !== '') {
                $searchPattern = '%' . $ticketSearch . '%';
                $ticketQuery->where(function ($query) use ($searchPattern) {
                    $query->where('ticket_number', 'like', $searchPattern)
                        ->orWhere('title', 'like', $searchPattern)
                        ->orWhere('description', 'like', $searchPattern)
                        ->orWhere('room_label', 'like', $searchPattern)
                        ->orWhere('building_label', 'like', $searchPattern)
                        ->orWhere('equipment_label', 'like', $searchPattern)
                        ->orWhereHas('room', fn($room) => $room->where('name', 'like', $searchPattern)->orWhere('code', 'like', $searchPattern))
                        ->orWhereHas('room.building', fn($building) => $building->where('name', 'like', $searchPattern))
                        ->orWhereHas('category', fn($category) => $category->where('name', 'like', $searchPattern));
                });
            }
            $data['ticketSearch'] = $ticketSearch;
            $data['selectedCategoryId'] = (string) $selectedCategoryId;
            $data['tickets'] = $ticketQuery->latest()->paginate(15)->withQueryString();
        }
        if ($section === 'customer' && $path === 'notifications') {
            $data['notifications'] = $user->notifications()->latest()->get();
            $data['notificationGroups'] = $this->groupNotifications($data['notifications']);
        }
        if ($section === 'customer' && $path === 'ticket-detail') {
            $data['ticket'] = Ticket::with(['room.building', 'category', 'assignee', 'reportAttachments', 'logs.user', 'schedules'])
                ->where('reporter_id', $user->id)->findOrFail($request->integer('ticket'));
            $data['buildings'] = Building::with('rooms')->orderBy('code')->get();
            $data['categories'] = Category::orderBy('name')->get(['id', 'name']);
        }
        if ($section === 'customer' && $path === 'index') {
            $data['ticketCounts'] = Ticket::where('reporter_id', $user->id)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
            $data['recentTickets'] = Ticket::with('room.building')->where('reporter_id', $user->id)->latest()->take(5)->get();
        }
        if ($section === 'staff' && $path === 'index') {
            $data['categories'] = Category::orderBy('name')->get(['id', 'name', 'color']);
            $scheduleQuery = fn($query) => $query->orderBy('starts_at');
            if ($user->role === 'staff') {
                $scheduleQuery = fn($query) => $query->where('staff_id', $user->id)->orderBy('starts_at');
            }
            $tickets = Ticket::with(['room.building', 'category', 'schedules' => function ($query) use ($scheduleQuery) {
                $scheduleQuery($query)->where('status', '!=', 'cancelled');
            }]);
            $ticketCounts = Ticket::query();
            $todaySchedules = TicketSchedule::where('status', '!=', 'cancelled');
            if ($user->role !== 'admin') {
                $tickets->where('assigned_to_id', $user->id);
                $ticketCounts->where('assigned_to_id', $user->id);
                $todaySchedules->where('staff_id', $user->id);
            }
            $data['tickets'] = $tickets->latest()->get();
            $data['ticketCounts'] = $ticketCounts->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
            $data['todaySchedules'] = $todaySchedules->whereDate('starts_at', today())->get();

            $calendarPeriod = $request->query('period') === 'week' ? 'week' : 'month';
            $monthInput = $request->query('month');
            $calendarMonth = is_string($monthInput) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthInput)
                ? Carbon::createFromFormat('!Y-m', $monthInput)
                : now()->startOfMonth();
            $calendarMonth = $calendarMonth ?: now()->startOfMonth();
            $selectedDateInput = $request->query('day');
            $selectedDate = is_string($selectedDateInput) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDateInput)
                ? Carbon::createFromFormat('!Y-m-d', $selectedDateInput)
                : null;
            if (!$selectedDate) {
                $selectedDate = $calendarPeriod === 'week'
                    ? today()->toImmutable()
                    : ($calendarMonth->isSameMonth(today()) ? today()->toImmutable() : $calendarMonth->copy());
            }
            if ($calendarPeriod === 'week') {
                $calendarStart = $selectedDate->copy()->startOfWeek(Carbon::MONDAY);
                $calendarEnd = $selectedDate->copy()->endOfWeek(Carbon::SUNDAY);
                $calendarMonth = $selectedDate->copy()->startOfMonth();
            } else {
                if ($selectedDate->format('Y-m') !== $calendarMonth->format('Y-m')) {
                    $selectedDate = $calendarMonth->isSameMonth(today()) ? today()->toImmutable() : $calendarMonth->copy();
                }
                $calendarStart = $calendarMonth->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
                $calendarEnd = $calendarMonth->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
            }

            $calendarSchedules = TicketSchedule::with(['ticket.room.building', 'ticket.category'])
                ->where('status', '!=', 'cancelled')
                ->whereBetween('starts_at', [$calendarStart->copy()->startOfDay(), $calendarEnd->copy()->endOfDay()]);
            if ($user->role !== 'admin') $calendarSchedules->where('staff_id', $user->id);
            $calendarSchedules = $calendarSchedules->orderBy('starts_at')->get();

            $calendarDays = collect();
            for ($day = $calendarStart->copy(); $day->lte($calendarEnd); $day = $day->addDay()) {
                $date = $day->copy();
                $calendarDays->push([
                    'date' => $date,
                    'in_month' => $calendarPeriod === 'week' || $date->month === $calendarMonth->month,
                    'is_today' => $date->isToday(),
                    'is_selected' => $date->isSameDay($selectedDate),
                    'schedules' => $calendarSchedules->filter(fn($schedule) => $schedule->starts_at->isSameDay($date))->values(),
                ]);
            }

            $data['calendarMonth'] = $calendarMonth;
            $data['calendarPeriod'] = $calendarPeriod;
            $data['calendarStart'] = $calendarStart;
            $data['calendarEnd'] = $calendarEnd;
            $data['calendarDays'] = $calendarDays;
            $data['calendarCategories'] = $calendarSchedules->pluck('ticket.category')->filter()->unique('id')->take(5)->values();
            $data['hasCalendarSchedules'] = $calendarSchedules->isNotEmpty();
            $data['selectedDate'] = $selectedDate;
            $data['selectedDaySchedules'] = $calendarSchedules
                ->filter(fn($schedule) => $schedule->starts_at->isSameDay($selectedDate))->values();
        }
        if ($section === 'staff' && $path === 'schedule') {
            $weekInput = $request->query('week');
            $weekDate = is_string($weekInput) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekInput)
                ? Carbon::createFromFormat('!Y-m-d', $weekInput)
                : today()->toImmutable();
            $weekDate = $weekDate ?: today()->toImmutable();
            $weekStart = $weekDate->copy()->startOfWeek(Carbon::MONDAY);
            $weekEnd = $weekDate->copy()->endOfWeek(Carbon::SUNDAY);

            $schedules = TicketSchedule::with('ticket.room.building', 'ticket.category')
                ->where('status', '!=', 'cancelled')
                ->whereBetween('starts_at', [$weekStart->copy()->startOfDay(), $weekEnd->copy()->endOfDay()]);
            if ($user->role !== 'admin') $schedules->where('staff_id', $user->id);
            $schedules = $schedules->orderBy('starts_at')->get();
            $weekDays = collect();
            for ($day = $weekStart->copy(); $day->lte($weekEnd); $day = $day->addDay()) {
                $date = $day->copy();
                $weekDays->push([
                    'date' => $date,
                    'is_today' => $date->isToday(),
                    'schedules' => $schedules->filter(fn($schedule) => $schedule->starts_at->isSameDay($date))->values(),
                ]);
            }
            $data['schedules'] = $schedules;
            $data['weekDate'] = $weekDate;
            $data['weekStart'] = $weekStart;
            $data['weekEnd'] = $weekEnd;
            $data['weekDays'] = $weekDays;
        }
        if ($section === 'staff' && $path === 'notifications') {
            $data['notifications'] = $user->notifications()->latest()->get();
            $data['notificationGroups'] = $this->groupNotifications($data['notifications']);
            $accessibleTickets = Ticket::whereIn('id', $data['notificationGroups']->pluck('ticket_id')->filter());
            if ($user->role !== 'admin') $accessibleTickets->where('assigned_to_id', $user->id);
            $data['accessibleTicketIds'] = $accessibleTickets->pluck('id');
        }
        if ($section === 'staff' && $path === 'ticket-detail') {
            $scheduleQuery = fn($query) => $query->orderBy('starts_at');
            if ($user->role === 'staff') {
                $scheduleQuery = fn($query) => $query->where('staff_id', $user->id)->orderBy('starts_at');
            }
            $ticket = Ticket::with(['room.building', 'room.equipment', 'reporter', 'reportAttachments', 'repairAttachments', 'logs.user', 'schedules' => function ($query) use ($scheduleQuery) {
                $scheduleQuery($query)->where('status', '!=', 'cancelled');
            }]);
            if ($user->role !== 'admin') $ticket->where('assigned_to_id', $user->id);
            $data['ticket'] = $ticket->findOrFail($request->integer('ticket'));
        }
    }

    private function groupNotifications(Collection $notifications): Collection
    {
        return $notifications
            ->groupBy(function ($notification) {
                $ticketId = $notification->data['ticket_id'] ?? null;
                return $ticketId ? 'ticket:'.$ticketId : 'notification:'.$notification->id;
            })
            ->map(function (Collection $items) {
                $latest = $items->first();
                $ticketId = $latest->data['ticket_id'] ?? null;

                return [
                    'latest' => $latest,
                    'ticket_id' => $ticketId,
                    'count' => $items->count(),
                    'unread_count' => $items->whereNull('read_at')->count(),
                    'count_label' => $items->count().' cập nhật'.($items->whereNull('read_at')->count() ? ' | '.$items->whereNull('read_at')->count().' chưa đọc' : ''),
                    'read_url' => $ticketId
                        ? url('/api/notifications/tickets/'.$ticketId.'/read')
                        : url('/api/notifications/'.$latest->id.'/read'),
                ];
            })
            ->values();
    }
}
