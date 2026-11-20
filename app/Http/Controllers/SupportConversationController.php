<?php

namespace App\Http\Controllers;

use App\Models\SupportCommand;
use App\Models\SupportSession;
use App\Services\Support\SupportConversationService;
use App\Support\SupportApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SupportConversationController extends Controller
{
    public function store(Request $request, SupportConversationService $service): JsonResponse
    {
        return $this->execute($request, $service);
    }

    public function message(Request $request, SupportSession $supportSession, SupportConversationService $service): JsonResponse
    {
        Gate::authorize('view', $supportSession);

        return $this->execute($request, $service, $supportSession);
    }

    public function escalate(Request $request, SupportSession $supportSession, SupportConversationService $service): JsonResponse
    {
        Gate::authorize('view', $supportSession);
        $request->merge(['action' => 'report', 'content' => 'Gửi báo cáo giúp tôi']);

        return $this->execute($request, $service, $supportSession);
    }

    public function show(SupportSession $supportSession, SupportConversationService $service): JsonResponse
    {
        Gate::authorize('view', $supportSession);

        return SupportApiResponse::success($service->snapshot($supportSession));
    }

    public function command(SupportSession $supportSession, string $command, SupportConversationService $service): JsonResponse
    {
        Gate::authorize('view', $supportSession);
        $receipt = SupportCommand::where('session_id', $supportSession->id)->findOrFail($command);

        return SupportApiResponse::success($service->snapshot($supportSession, $receipt));
    }

    private function execute(Request $request, SupportConversationService $service, ?SupportSession $session = null): JsonResponse
    {
        Gate::authorize('create', SupportSession::class);
        $request->merge(['idempotency_key' => $request->header('Idempotency-Key')]);
        $data = $request->validate([
            'content' => ['required', 'string', 'max:1000'],
            'room_id' => ['nullable', 'integer', Rule::exists('rooms', 'id')],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'expected_version' => [$session ? 'required' : 'nullable', 'integer', 'min:0'],
            'idempotency_key' => ['required', 'string', 'max:128', 'regex:/\A[a-zA-Z0-9._:-]+\z/'],
            'action' => ['nullable', Rule::in(['chat', 'report', 'resolve'])],
            'messages' => ['prohibited'], 'user_id' => ['prohibited'], 'priority' => ['prohibited'],
            'title' => ['prohibited'], 'description' => ['prohibited'], 'report' => ['prohibited'],
        ]);
        $result = $service->execute($request->user(), $session, $data, $data['idempotency_key']);

        return SupportApiResponse::success($result['data'], $result['status']);
    }
}
