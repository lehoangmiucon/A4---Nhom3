<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HelpdeskChatController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'messages' => ['required', 'array', 'list', 'min:1', 'max:9'],
            'messages.*' => ['required', 'array:role,content'],
            'messages.*.role' => ['required', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:2400'],
            'room_id' => ['nullable', 'integer', 'min:1'],
        ]);

        abort_unless($data['messages'][array_key_last($data['messages'])]['role'] === 'user', 422, 'Hãy nhập tin nhắn của bạn.');

        $result = app(\App\Services\HelpdeskAiService::class)->reply($data['messages'], isset($data['room_id']) ? Room::find($data['room_id']) : null);
        return isset($result['error']) ? response()->json(['message' => $result['error']], 503) : response()->json($result);
    }
}
