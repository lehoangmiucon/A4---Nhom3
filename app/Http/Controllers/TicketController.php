<?php

namespace App\Http\Controllers;

use App\Models\Building;
use App\Models\ActivityLog;
use App\Models\Room;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketLog;
use App\Models\TicketSchedule;
use App\Models\User;
use App\Notifications\TicketActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TicketController extends Controller
{
    public function rooms(): JsonResponse
    {
        return response()->json(['data' => Room::with('building:id,code,name')->orderBy('code')->get()]);
    }

    public function room(string $code): JsonResponse
    {
        $room = Room::with('building:id,code,name')->where('qr_code', $code)->orWhere('code', $code)->firstOrFail();
        return response()->json(['data' => $room]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'staff', 'student', 'teacher'], true), 403, 'Vui lòng đăng nhập bằng tài khoản trường để gửi sự cố.');
        $data = $request->validate([
            'reporter' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'room_code' => ['required', 'string', 'max:80'],
            'room_id' => ['nullable', 'integer', Rule::exists('rooms', 'id')],
            'building_code' => ['nullable', 'string', Rule::exists('buildings', 'code')],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'max:5000'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'priority' => ['nullable', 'string', 'max:100'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['image', 'max:5120'],
        ]);
        $roomQuery = Room::with('building');
        if (!empty($data['room_id'])) $roomQuery->whereKey($data['room_id']);
        $roomQuery->where(fn($query) => $query->where('qr_code', $data['room_code'])->orWhere('code', $data['room_code']));
        if (!empty($data['building_code'])) {
            $roomQuery->whereHas('building', fn($query) => $query->where('code', $data['building_code']));
        }
        $room = $roomQuery->first();
        if (!$room) throw ValidationException::withMessages(['room_code' => 'Mã QR không gắn với phòng học hợp lệ.']);

        $category = \App\Models\Category::findOrFail($data['category_id']);
        $ticket = DB::transaction(function () use ($request, $data, $room, $category) {
            $ticket = Ticket::create([
                'ticket_number' => 'HD-' . now()->format('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3))),
                'reporter_id' => $request->user()->id,
                'reporter_name' => $request->user()->name,
                'reporter_email' => $request->user()->email,
                'reporter_phone' => $data['phone'] ?? null,
                'room_id' => $room->id,
                'room_label' => $room->name,
                'building_label' => $room->building?->name,
                'category_id' => $category->id,
                'equipment_label' => $category->name,
                'title' => $data['title'],
                'description' => $data['description'],
                'priority' => $request->user()->role === 'teacher' ? 'high' : $this->priority($data['priority'] ?? 'normal'),
                'status' => 'new',
            ]);
            TicketLog::create(['ticket_id' => $ticket->id, 'user_id' => $request->user()->id, 'to_status' => 'new', 'note' => 'Người báo sự cố đã gửi báo cáo.']);
            foreach ($request->file('attachments', []) as $file) {
                $path = $file->store('ticket-attachments/reports', 'public');
                TicketAttachment::create(['ticket_id' => $ticket->id, 'uploaded_by_id' => $request->user()->id, 'path' => $path, 'original_name' => $file->getClientOriginalName(), 'attachment_type' => 'report']);
            }
            return $ticket;
        });
        ActivityLog::record($request, 'ticket.created', $ticket, 'Created a ticket.', [
            'ticket_number' => $ticket->ticket_number,
            'status' => $ticket->status,
        ]);

        $payload = [
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'title' => $ticket->title,
            'status' => 'new',
            'message' => $request->user()->role === 'teacher'
                ? 'Có báo cáo ưu tiên cao từ giáo viên cần được tiếp nhận.'
                : 'Có phiếu sự cố mới cần tiếp nhận.',
        ];
        Notification::send(User::where('role', 'admin')->get(), new TicketActivity($payload));
        return response()->json(['message' => 'Đã gửi báo cáo. Phiếu đang chờ quản lý tiếp nhận.', 'data' => $ticket], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Ticket::with(['room.building:id,code,name', 'assignee:id,name', 'category:id,name,color'])->latest();
        $this->scopeTickets($query, $request->user());
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        return response()->json($query->paginate(20));
    }

    public function filterIncidents(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', Rule::in(['new', 'assigned', 'in_progress', 'resolved', 'closed', 'reopened'])],
            'category' => ['nullable', 'string', 'max:180'],
            'building' => ['nullable', 'string', Rule::exists('buildings', 'code')],
        ]);
        $query = Ticket::query();
        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['category'])) $query->where('equipment_label', $filters['category']);
        if (!empty($filters['building'])) {
            $buildingCode = $filters['building'];
            $query->where(fn ($tickets) => $tickets
                ->whereHas('room.building', fn ($building) => $building->where('code', $buildingCode))
                ->orWhere('building_label', $buildingCode)
                ->orWhereHas('room.building', fn ($building) => $building->where('name', Building::where('code', $buildingCode)->value('name'))));
        }
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $digits = preg_replace('/\D+/', '', $search);
            $query->where(fn ($tickets) => $tickets
                ->where('ticket_number', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhere('equipment_label', 'like', "%{$search}%")
                ->orWhere('room_label', 'like', "%{$search}%")
                ->orWhere('building_label', 'like', "%{$search}%")
                ->orWhere('reporter_name', 'like', "%{$search}%")
                ->orWhere('reporter_email', 'like', "%{$search}%")
                ->orWhere('reporter_phone', 'like', "%{$search}%")
                ->when($digits !== '', fn ($tickets) => $tickets->orWhereRaw("REPLACE(REPLACE(REPLACE(reporter_phone, ' ', ''), '-', ''), '(', '') LIKE ?", ["%{$digits}%"]))
                ->orWhereHas('room', fn ($rooms) => $rooms->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                ->orWhereHas('room.building', fn ($buildings) => $buildings->where('name', 'like', "%{$search}%")));
        }
        $ids = $query->latest()->pluck('id');
        return response()->json(['data' => ['ids' => $ids, 'count' => $ids->count()]]);
    }

    public function filterSchedules(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'staff' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(['assigned', 'in_progress', 'resolved', 'closed', 'reopened', 'new'])],
        ]);
        $query = TicketSchedule::query()->where('status', '!=', 'cancelled');
        if (!empty($filters['staff'])) $query->whereHas('staff', fn ($staff) => $staff->where('name', $filters['staff']));
        if (!empty($filters['category'])) $query->whereHas('ticket', fn ($ticket) => $ticket->where('category_id', $filters['category']));
        if (!empty($filters['status'])) $query->whereHas('ticket', fn ($ticket) => $ticket->where('status', $filters['status']));
        $ids = $query->pluck('id');
        return response()->json(['data' => ['ids' => $ids, 'count' => $ids->count()]]);
    }

    public function updateReport(Request $request, Ticket $ticket): JsonResponse
    {
        abort_unless(in_array($request->user()?->role, ['student', 'teacher'], true) && (int) $ticket->reporter_id === (int) $request->user()->id, 403, 'Bạn không có quyền sửa phiếu này.');
        abort_unless($ticket->status === 'new', 422, 'Chỉ có thể sửa phiếu khi đang chờ tiếp nhận.');

        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:40'],
            'room_code' => ['required', 'string', 'max:80'],
            'room_id' => ['required', 'integer', Rule::exists('rooms', 'id')],
            'building_code' => ['required', 'string', Rule::exists('buildings', 'code')],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'max:5000'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'priority' => ['nullable', 'string', 'max:100'],
            'remove_attachments' => ['nullable', 'boolean'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['image', 'max:5120'],
        ]);

        $room = Room::with('building')->whereKey($data['room_id'])
            ->where(fn ($query) => $query->where('qr_code', $data['room_code'])->orWhere('code', $data['room_code']))
            ->whereHas('building', fn ($query) => $query->where('code', $data['building_code']))
            ->first();
        if (! $room) throw ValidationException::withMessages(['room_code' => 'Phòng học không khớp với tòa nhà đã chọn.']);
        $category = \App\Models\Category::findOrFail($data['category_id']);

        $replacedPaths = [];
        DB::transaction(function () use ($request, $ticket, $data, $room, $category, &$replacedPaths) {
            $lockedTicket = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $lockedTicket->reporter_id === (int) $request->user()->id && $lockedTicket->status === 'new', 422, 'Phiếu đã được tiếp nhận nên không thể chỉnh sửa.');

            $lockedTicket->update([
                'reporter_phone' => $data['phone'] ?? null,
                'room_id' => $room->id,
                'room_label' => $room->name,
                'building_label' => $room->building?->name,
                'category_id' => $category->id,
                'equipment_label' => $category->name,
                'title' => $data['title'],
                'description' => $data['description'],
                'priority' => $request->user()->role === 'teacher' ? 'high' : $this->priority($data['priority'] ?? 'normal'),
            ]);

            if ($request->hasFile('attachments') || $request->boolean('remove_attachments')) {
                $oldAttachments = $lockedTicket->reportAttachments()->get();
                $replacedPaths = $oldAttachments->pluck('path')->all();
                $lockedTicket->reportAttachments()->delete();
                foreach ($request->file('attachments', []) as $file) {
                    $path = $file->store('ticket-attachments/reports', 'public');
                    TicketAttachment::create([
                        'ticket_id' => $lockedTicket->id,
                        'uploaded_by_id' => $request->user()->id,
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'attachment_type' => 'report',
                    ]);
                }
            }
        });

        foreach ($replacedPaths as $path) Storage::disk('public')->delete($path);
        ActivityLog::record($request, 'ticket.report_updated', $ticket, 'Reporter updated a waiting ticket.', ['ticket_number' => $ticket->ticket_number]);

        return response()->json(['message' => 'Đã lưu các thay đổi của phiếu.', 'data' => $ticket->fresh()->load(['room.building', 'category', 'reportAttachments'])]);
    }

    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        abort_unless($this->canView($request->user(), $ticket), 403, 'Bạn không có quyền xem phiếu này.');
        $relations = ['room.building:id,code,name', 'assignee:id,name', 'reporter:id,name,email', 'category:id,name,color', 'reportAttachments', 'logs.user:id,name'];
        if (! in_array($request->user()->role, ['student', 'teacher'], true)) $relations[] = 'repairAttachments';
        $ticket->load($relations);
        $data = $ticket->toArray();
        $data['attachments'] = in_array($request->user()->role, ['student', 'teacher'], true)
            ? $ticket->reportAttachments->values()->toArray()
            : $ticket->reportAttachments->concat($ticket->repairAttachments)->values()->toArray();
        $data['assigned_staff'] = $ticket->assignee;
        $data['equipment'] = ['name' => $ticket->equipment_label];
        return response()->json(['data' => $data]);
    }

    public function logs(Request $request, Ticket $ticket): JsonResponse
    {
        abort_unless($this->canView($request->user(), $ticket), 403, 'Bạn không có quyền xem lịch sử phiếu này.');
        $logs = $ticket->logs()->with('user:id,name')->get()->map(fn($log) => [
            'actor' => $log->user,
            'created_at' => $log->created_at,
            'note' => $log->note,
        ]);
        return response()->json(['data' => $logs]);
    }

    public function staffSchedules(Request $request): JsonResponse
    {
        $schedules = TicketSchedule::with(['ticket.room.building:id,code,name', 'ticket.category:id,name,color'])->where('status', '!=', 'cancelled');
        if ($request->user()->role !== 'admin') $schedules->where('staff_id', $request->user()->id);
        $items = $schedules->where('starts_at', '>=', now()->subDays(30))
            ->orderBy('starts_at')->get()->map(fn($schedule) => [
                'ticket_id' => $schedule->ticket_id,
                'starts_at' => $schedule->starts_at,
                'note' => $schedule->note,
                'ticket' => [
                    'ticket_number' => $schedule->ticket->ticket_number,
                    'title' => $schedule->ticket->title,
                    'status' => $schedule->ticket->status,
                    'room' => [
                        'name' => $schedule->ticket->room?->name,
                        'building' => ['name' => $schedule->ticket->room?->building?->name],
                    ],
                    'category' => $schedule->ticket->category,
                ],
            ]);
        return response()->json(['data' => $items]);
    }

    public function assign(Request $request, Ticket $ticket): JsonResponse
    {
        $data = $request->validate([
            'staff_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'staff')->where('is_deleted', false)],
            'scheduled_at' => ['nullable', 'date', 'after_or_equal:now'],
            'note' => ['nullable', 'string', 'max:2000'],
            'schedule_id' => ['nullable', 'integer', Rule::exists('ticket_schedules', 'id')->where('ticket_id', $ticket->id)],
        ]);
        if ($ticket->category_id && ! User::findOrFail($data['staff_id'])->categories()->whereKey($ticket->category_id)->exists()) {
            throw ValidationException::withMessages([
                'staff_id' => 'Nhân viên này chưa được gán danh mục sự cố phù hợp với phiếu.',
            ]);
        }
        abort_unless(in_array($ticket->status, ['new', 'reopened', 'assigned'], true), 422, 'Chỉ có thể phân công phiếu đang chờ tiếp nhận hoặc cần xử lý lại.');
        $oldStatus = $ticket->status;
        $previousStaffId = $ticket->assigned_to_id;
        $selectedSchedule = null;
        if (!empty($data['schedule_id'])) {
            $selectedSchedule = $ticket->schedules()->findOrFail($data['schedule_id']);
            if ($selectedSchedule->status === 'cancelled') abort(422, 'Lịch này đã bị hủy. Hãy tạo lịch mới cho phiếu.');
        }

        $staffChanged = $previousStaffId && (int) $previousStaffId !== (int) $data['staff_id'];
        $scheduleTimeChanged = filled($data['scheduled_at'] ?? null)
            && (! $selectedSchedule
                || ! $selectedSchedule->starts_at
                || ! $selectedSchedule->starts_at->equalTo(\Illuminate\Support\Carbon::parse($data['scheduled_at'])));
        $acknowledgedScheduleIsBeingChanged = $selectedSchedule?->status === 'acknowledged'
            && ($staffChanged || (int) $selectedSchedule->staff_id !== (int) $data['staff_id'] || $scheduleTimeChanged);

        if ($acknowledgedScheduleIsBeingChanged || ($staffChanged && $ticket->schedules()
            ->where('status', 'acknowledged')
            ->whereNull('cancelled_at')
            ->exists())) {
            abort(422, 'Nhân viên đã xác nhận đang trên đường. Hãy hủy lịch đã xác nhận trước khi phân công lại.');
        }
        DB::transaction(function () use ($ticket, $data, $oldStatus, $previousStaffId, $request) {
            $ticket->update(['assigned_to_id' => $data['staff_id'], 'status' => 'assigned']);
            if ($previousStaffId && (int) $previousStaffId !== (int) $data['staff_id']) {
                $ticket->schedules()->where('status', '!=', 'cancelled')->update(['staff_id' => $data['staff_id']]);
            }
            if ($oldStatus !== 'assigned' || (int) $previousStaffId !== (int) $data['staff_id']) TicketLog::create(['ticket_id' => $ticket->id, 'user_id' => $request->user()->id, 'from_status' => $oldStatus, 'to_status' => 'assigned', 'note' => $data['note'] ?? 'Quản lý đã phân công nhân viên.']);
            if (!empty($data['scheduled_at'])) {
                $schedule = !empty($data['schedule_id'])
                    ? $ticket->schedules()->findOrFail($data['schedule_id'])
                    : new TicketSchedule(['ticket_id' => $ticket->id]);
                if (!$schedule->exists) {
                    $schedule->created_by_id = $request->user()->id;
                }
                $schedule->fill([
                    'staff_id' => $data['staff_id'],
                    'starts_at' => $data['scheduled_at'],
                    'ends_at' => now()->parse($data['scheduled_at'])->addHour(),
                    'note' => $data['note'] ?? null,
                ])->save();
            }
        });
        $payload = ['ticket_id' => $ticket->id, 'ticket_number' => $ticket->ticket_number, 'title' => $ticket->title, 'status' => 'assigned', 'message' => 'Bạn được phân công xử lý phiếu.'];
        Notification::send(User::whereKey($data['staff_id'])->get(), new TicketActivity([...$payload, 'message' => empty($data['scheduled_at']) ? 'Bạn được phân công xử lý phiếu.' : 'Bạn được phân công và có lịch xử lý mới.']));
        Notification::send(User::whereKey($ticket->reporter_id)->get(), new TicketActivity([...$payload, 'message' => 'Quản lý đã tiếp nhận và phân công phiếu của bạn.']));
        ActivityLog::record($request, 'ticket.assigned', $ticket, 'Assigned ticket to staff.', [
            'staff_id' => $data['staff_id'],
            'scheduled_at' => $data['scheduled_at'] ?? null,
        ]);

        return response()->json(['message' => 'Đã phân công nhân viên.', 'data' => $ticket->fresh()->load('assignee:id,name')]);
    }

    public function acknowledgeSchedule(Request $request, TicketSchedule $schedule): JsonResponse
    {
        abort_unless($request->user()->role === 'staff' && (int) $schedule->staff_id === (int) $request->user()->id, 403);
        abort_unless($schedule->status !== 'cancelled', 422, 'Lịch này đã bị quản lý hủy.');
        $ticket = $schedule->ticket;
        abort_unless(in_array($ticket->status, ['assigned', 'reopened', 'in_progress'], true), 422, 'Phiếu không ở trạng thái có thể bắt đầu xử lý.');
        if ($schedule->status === 'acknowledged' && $ticket->status === 'in_progress') {
            return response()->json(['message' => 'Bạn đã xác nhận lịch này rồi.', 'data' => $schedule]);
        }

        $previousStatus = $ticket->status;
        DB::transaction(function () use ($schedule, $ticket, $request, $previousStatus) {
            $schedule->update(['status' => 'acknowledged', 'acknowledged_at' => now()]);
            if (in_array($previousStatus, ['assigned', 'reopened'], true)) {
                $ticket->update(['status' => 'in_progress']);
                TicketLog::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $request->user()->id,
                    'from_status' => $previousStatus,
                    'to_status' => 'in_progress',
                    'note' => 'Nhân viên xác nhận đã đến và bắt đầu xử lý.',
                ]);
            }
        });
        $ticket->refresh();
        $payload = [
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'title' => $ticket->title,
            'status' => $ticket->status,
            'schedule_id' => $schedule->id,
            'schedule_status' => 'acknowledged',
            'message' => $request->user()->name . ' đã xác nhận có mặt và bắt đầu xử lý phiếu.',
        ];
        Notification::send(User::where('role', 'admin')->get(), new TicketActivity($payload));
        Notification::send(User::whereKey($ticket->reporter_id)->get(), new TicketActivity($payload));
        ActivityLog::record($request, 'schedule.acknowledged', $schedule, 'Staff confirmed arrival and started working on the ticket.', [
            'ticket_id' => $ticket->id,
            'starts_at' => $schedule->starts_at?->toIso8601String(),
        ]);

        return response()->json(['message' => 'Đã xác nhận lịch. Quản lý và người báo sự cố đã được thông báo.', 'data' => $schedule->fresh()]);
    }

    public function cancelSchedule(Request $request, TicketSchedule $schedule): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);
        abort_unless($schedule->status !== 'cancelled', 422, 'Lịch này đã được hủy trước đó.');
        $ticket = $schedule->ticket;
        abort_unless(in_array($ticket->status, ['assigned', 'reopened'], true), 422, 'Chỉ có thể hủy lịch của phiếu đang được phân công.');

        DB::transaction(function () use ($schedule, $ticket, $data, $request) {
            $schedule->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_note' => $data['note'] ?? null,
            ]);

            $hasAnotherActiveSchedule = $ticket->schedules()
                ->where('id', '!=', $schedule->id)
                ->where('status', '!=', 'cancelled')
                ->exists();
            if (! $hasAnotherActiveSchedule) {
                $ticket->update([
                    'status' => $ticket->status === 'assigned' ? 'new' : 'reopened',
                    'assigned_to_id' => null,
                ]);
                if ($ticket->status === 'new') {
                    TicketLog::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $request->user()->id,
                        'from_status' => 'assigned',
                        'to_status' => 'new',
                        'note' => 'Quản lý đã hủy lịch và đưa phiếu về hàng chờ phân công.' . (($data['note'] ?? null) ? ' Lý do: ' . $data['note'] : ''),
                    ]);
                }
            }
        });

        $payload = [
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'title' => $ticket->title,
            'status' => $ticket->fresh()->status,
            'schedule_id' => $schedule->id,
            'schedule_status' => 'cancelled',
            'message' => 'Lịch xử lý đã bị quản lý hủy.' . (filled($data['note'] ?? null) ? ' Lý do: ' . $data['note'] : ''),
        ];
        Notification::send(User::whereKey($schedule->staff_id)->get(), new TicketActivity($payload));
        Notification::send(User::whereKey($ticket->reporter_id)->get(), new TicketActivity($payload));
        ActivityLog::record($request, 'schedule.cancelled', $schedule, 'Manager cancelled a ticket schedule.', [
            'ticket_id' => $ticket->id,
            'staff_id' => $schedule->staff_id,
            'note' => $data['note'] ?? null,
        ]);

        return response()->json(['message' => 'Đã hủy lịch và thông báo cho nhân viên cùng người báo sự cố.', 'data' => $schedule->fresh()]);
    }

    public function staffUpdate(Request $request, Ticket $ticket): JsonResponse
    {
        abort_unless($request->user()->role === 'admin' || $ticket->assigned_to_id === $request->user()->id, 403);
        $data = $request->validate([
            'status' => ['required', Rule::in(['in_progress', 'resolved'])],
            'note' => ['required', 'string', 'max:3000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['image', 'max:5120'],
        ]);

        // Once the ticket is in progress, uploading repair evidence submits the result for manager review.
        $targetStatus = $data['status'] === 'resolved'
            || ($ticket->status === 'in_progress' && $request->hasFile('attachments'))
            ? 'resolved'
            : 'in_progress';

        if ($targetStatus === 'in_progress' && !in_array($ticket->status, ['assigned', 'reopened', 'in_progress'], true)) {
            abort(422, 'Phiếu chưa sẵn sàng để bắt đầu xử lý.');
        }
        if ($targetStatus === 'resolved' && $ticket->status !== 'in_progress') {
            abort(422, 'Nhân viên cần bắt đầu xử lý phiếu trước khi gửi kết quả hoàn thành.');
        }

        $activeAcknowledgedSchedule = $ticket->schedules()
            ->where('staff_id', $ticket->assigned_to_id)
            ->where('status', 'acknowledged')
            ->whereNull('cancelled_at')
            ->exists();
        if (!$activeAcknowledgedSchedule) {
            abort(422, 'Chưa thể cập nhật tiến độ: cần xác nhận lịch xử lý trước.');
        }
        if ($targetStatus === 'resolved' && !$request->hasFile('attachments')) {
            throw ValidationException::withMessages(['attachments' => 'Vui lòng tải ảnh minh chứng sau sửa chữa trước khi gửi quản lý xác nhận.']);
        }

        $previous = $ticket->status;
        DB::transaction(function () use ($request, $ticket, $data, $previous, $targetStatus) {
            $ticket->update([
                'status' => $targetStatus,
                'resolution_note' => $targetStatus === 'resolved' ? $data['note'] : $ticket->resolution_note,
                'resolved_at' => $targetStatus === 'resolved' ? now() : null,
            ]);
            TicketLog::create([
                'ticket_id' => $ticket->id,
                'user_id' => $request->user()->id,
                'from_status' => $previous,
                'to_status' => $targetStatus,
                'note' => $data['note'],
            ]);
            foreach ($request->file('attachments', []) as $file) {
                $path = $file->store('ticket-attachments/repairs', 'public');
                TicketAttachment::create([
                    'ticket_id' => $ticket->id,
                    'uploaded_by_id' => $request->user()->id,
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'attachment_type' => 'repair',
                ]);
            }
        });

        $message = $targetStatus === 'resolved'
            ? 'Nhân viên đã gửi kết quả xử lý, đang chờ quản lý xác nhận.'
            : 'Nhân viên đã bắt đầu xử lý phiếu.';
        $payload = [
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'title' => $ticket->title,
            'status' => $targetStatus,
            'message' => $message,
        ];
        Notification::send(User::where('role', 'admin')->get(), new TicketActivity($payload));
        Notification::send(User::whereKey($ticket->reporter_id)->get(), new TicketActivity($payload));
        ActivityLog::record($request, 'ticket.status_updated', $ticket, 'Updated ticket status.', [
            'from_status' => $previous,
            'to_status' => $targetStatus,
        ]);

        return response()->json(['message' => $message, 'data' => $ticket->fresh()]);
    }
    public function close(Request $request, Ticket $ticket): JsonResponse
    {
        abort_unless($ticket->status === 'resolved', 422, 'Chỉ có thể xác nhận phiếu đã được nhân viên báo hoàn thành.');
        abort_unless($ticket->repairAttachments()->exists(), 422, 'Phiếu chưa có ảnh minh chứng sửa chữa để quản lý kiểm tra.');
        $hasAcknowledgedSchedule = $ticket->schedules()
            ->where('staff_id', $ticket->assigned_to_id)
            ->where('status', 'acknowledged')
            ->whereNull('cancelled_at')
            ->exists();
        abort_unless($hasAcknowledgedSchedule, 422, 'Không thể xác nhận hoàn thành: phiếu chưa có lịch xử lý được nhân viên xác nhận.');
        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);
        $this->transition($request, $ticket, 'closed', $data['note'] ?? 'Quản lý đã xác nhận kết quả.');
        $this->notifyPeople($ticket, ['ticket_id' => $ticket->id, 'ticket_number' => $ticket->ticket_number, 'title' => $ticket->title, 'status' => 'closed', 'message' => 'Phiếu đã được quản lý xác nhận hoàn thành.']);
        return response()->json(['message' => 'Đã đóng phiếu.', 'data' => $ticket->fresh()]);
    }

    public function reopen(Request $request, Ticket $ticket): JsonResponse
    {
        abort_unless(in_array($ticket->status, ['resolved', 'closed'], true), 422, 'Chỉ có thể yêu cầu xử lý lại phiếu đã hoàn thành.');
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        $oldRepairPaths = [];
        DB::transaction(function () use ($request, $ticket, $data, &$oldRepairPaths) {
            $lockedTicket = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($lockedTicket->status, ['resolved', 'closed'], true), 422, 'Phiếu đã được cập nhật. Hãy tải lại trang trước khi thao tác.');

            $oldRepairPaths = $lockedTicket->repairAttachments()->lockForUpdate()->pluck('path')->all();
            $this->transition($request, $lockedTicket, 'reopened', $data['note']);
            $lockedTicket->repairAttachments()->delete();
        });
        if ($oldRepairPaths !== []) {
            Storage::disk('public')->delete($oldRepairPaths);
        }
        $this->notifyPeople($ticket, ['ticket_id' => $ticket->id, 'ticket_number' => $ticket->ticket_number, 'title' => $ticket->title, 'status' => 'reopened', 'message' => 'Quản lý yêu cầu tiếp tục xử lý phiếu.']);
        return response()->json(['message' => 'Đã yêu cầu xử lý lại. Ảnh sửa chữa cũ đã được xóa để nhân viên gửi kết quả mới.', 'data' => $ticket->fresh()]);
    }

    private function transition(Request $request, Ticket $ticket, string $status, ?string $note): void
    {
        DB::transaction(function () use ($request, $ticket, $status, $note) {
            $old = $ticket->status;
            $ticket->update(['status' => $status, 'closed_at' => $status === 'closed' ? now() : null]);
            TicketLog::create(['ticket_id' => $ticket->id, 'user_id' => $request->user()->id, 'from_status' => $old, 'to_status' => $status, 'note' => $note]);
        });
        ActivityLog::record($request, 'ticket.status_updated', $ticket, 'Updated ticket status.', [
            'to_status' => $status,
        ]);
    }

    private function notifyPeople(Ticket $ticket, array $payload): void
    {
        if (($payload['status'] ?? null) === 'reopened') {
            $reason = $ticket->logs()->where('to_status', 'reopened')->latest()->value('note');
            if ($reason) {
                $payload['note'] = $reason;
                $payload['message'] = 'Phiếu được quản lý mở lại. Lý do cần xử lý: '.$reason.' Ảnh minh chứng sửa chữa cũ đã được xóa; vui lòng gửi ảnh mới sau khi xử lý.';
            }
        }
        Notification::send(User::whereIn('id', array_filter([$ticket->reporter_id, $ticket->assigned_to_id]))->get(), new TicketActivity($payload));
    }

    private function scopeTickets(Builder $query, ?User $user): void
    {
        if ($user?->role === 'admin') return;
        if ($user?->role === 'staff') $query->where('assigned_to_id', $user->id);
        else $query->where('reporter_id', $user?->id ?? 0);
    }

    private function canView(?User $user, Ticket $ticket): bool
    {
        return $user && ($user->role === 'admin' || ($user->role === 'staff' && $ticket->assigned_to_id === $user->id) || $ticket->reporter_id === $user->id);
    }

    private function priority(string $label): string
    {
        $label = mb_strtolower($label);
        if (str_contains($label, 'an toàn') || str_contains($label, 'sớm')) return 'urgent';
        if (str_contains($label, 'cả lớp') || str_contains($label, 'phòng')) return 'high';
        return in_array($label, ['low', 'normal', 'high', 'urgent'], true) ? $label : 'normal';
    }
}
