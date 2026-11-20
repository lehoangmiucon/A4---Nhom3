<?php

namespace App\Http\Controllers;

use App\Http\Requests\Support\StoreSupportSessionRequest;
use App\Models\SupportSession;
use App\Models\Ticket;
use App\Services\Support\SessionCreationService;
use App\Support\SupportApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SupportSessionController extends Controller
{
    public function store(StoreSupportSessionRequest $request, SessionCreationService $service): JsonResponse
    {
        return SupportApiResponse::success($service->create($request->user(), $request->validated()), 201);
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('create', SupportSession::class);
        $data = $request->validate([
            'status' => ['nullable', Rule::in(SupportSession::STATUSES)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $page = SupportSession::where('user_id', $request->user()->id)->with(['initialMessage', 'report.ticket'])
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('id')->paginate($data['per_page'] ?? 20);

        return SupportApiResponse::success($page->getCollection()->map->toSupportArray()->all(), meta: [
            'pagination' => ['current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()],
        ]);
    }

    public function show(SupportSession $supportSession): JsonResponse
    {
        Gate::authorize('view', $supportSession);

        return SupportApiResponse::success($supportSession->load(['initialMessage', 'report.ticket'])->toSupportArray());
    }

    public function destroy(SupportSession $supportSession): JsonResponse
    {
        Gate::authorize('view', $supportSession);
        DB::transaction(function () use ($supportSession) {
            $session = SupportSession::withTrashed()->whereKey($supportSession->id)->lockForUpdate()->firstOrFail();
            if ($session->trashed()) {
                return;
            }
            $report = $session->report()->lockForUpdate()->first();
            $ticket = $report ? Ticket::whereKey($report->ticket_id)->lockForUpdate()->first() : null;
            if ($session->status !== 'handed_off' || $ticket?->status !== 'closed') {
                throw ValidationException::withMessages(['session' => 'Chỉ được xóa phiên đã gửi báo cáo và phiếu đã được quản lý xác nhận hoàn thành.']);
            }
            $session->delete();
        }, 3);

        return SupportApiResponse::success(['id' => $supportSession->id, 'deleted' => true]);
    }

    public function messages(Request $request, SupportSession $supportSession): JsonResponse
    {
        Gate::authorize('view', $supportSession);
        $data = $request->validate([
            'after_id' => ['nullable', 'integer', 'min:0'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $page = $supportSession->messages()
            ->when(isset($data['after_id']), fn ($query) => $query->where('id', '>', $data['after_id']))
            ->orderBy('id')->paginate($data['per_page'] ?? 20);
        $messages = $page->getCollection()->map(fn ($message) => [
            'id' => $message->id, 'session_id' => $message->session_id, 'role' => $message->role,
            'content_text' => $message->content_text, 'created_at' => $message->created_at->toISOString(),
        ])->all();

        return SupportApiResponse::success($messages, meta: [
            'pagination' => ['current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()],
        ]);
    }
}
