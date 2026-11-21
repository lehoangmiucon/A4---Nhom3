<?php

namespace App\Http\Controllers;

use App\Contracts\ChatAssistant;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $conversations = Conversation::query()
            ->whereHas('participants', fn ($query) => $query->whereKey($user->id))
            ->with([
                'participants:id,name,email,phone,role,avatar_path',
                'ticket:id,ticket_number,title',
                'lastMessage.sender:id,name,role',
            ])
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get()
            ->map(fn (Conversation $conversation) => $this->conversationData($conversation, $user));

        return response()->json(['data' => $conversations]);
    }

    public function contacts(Request $request): JsonResponse
    {
        $validated = $request->validate(['q' => ['required', 'string', 'min:2', 'max:120']]);
        $query = trim($validated['q']);
        $phoneQuery = str_replace([' ', '-', '+', '(', ')'], '', $query);

        $contacts = User::query()
            ->where('id', '!=', $request->user()->id)
            ->where('is_deleted', false)
            ->whereNotNull('email_verified_at')
            ->where(function ($builder) use ($query, $phoneQuery): void {
                $builder->where('name', 'like', '%'.$query.'%')
                    ->orWhereRaw('LOWER(email) LIKE ?', ['%'.mb_strtolower($query).'%']);
                if ($phoneQuery !== '') {
                    $builder->orWhereRaw(
                        "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '(', ''), ')', '') LIKE ?",
                        ['%'.$phoneQuery.'%'],
                    );
                }
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email', 'phone', 'role', 'avatar_path']);

        return response()->json(['data' => $contacts]);
    }

    public function startDirect(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);
        $user = $request->user();
        $other = User::query()->whereKey($data['user_id'])
            ->where('is_deleted', false)
            ->whereNotNull('email_verified_at')
            ->firstOrFail();

        if ((int) $user->id === (int) $other->id) {
            throw ValidationException::withMessages(['user_id' => 'Chọn một người khác để bắt đầu trò chuyện.']);
        }

        $conversation = DB::transaction(function () use ($user, $other): Conversation {
            $existing = Conversation::query()
                ->where('type', 'direct')
                ->whereNull('ticket_id')
                ->whereHas('participants', fn ($query) => $query->whereKey($user->id))
                ->whereHas('participants', fn ($query) => $query->whereKey($other->id))
                ->first();

            if ($existing) {
                return $existing;
            }

            $conversation = Conversation::create([
                'type' => 'direct',
                'created_by_id' => $user->id,
            ]);
            $conversation->participants()->attach([$user->id, $other->id]);

            return $conversation;
        });

        return response()->json([
            'message' => 'Cuộc trò chuyện đã sẵn sàng.',
            'data' => $this->loadConversation($conversation, $user),
        ], 201);
    }

    public function startAssistant(Request $request): JsonResponse
    {
        $user = $request->user();
        $conversation = DB::transaction(function () use ($user): Conversation {
            $existing = Conversation::query()
                ->where('type', 'assistant')
                ->whereHas('participants', fn ($query) => $query->whereKey($user->id))
                ->first();

            if ($existing) {
                return $existing;
            }

            $conversation = Conversation::create([
                'type' => 'assistant',
                'created_by_id' => $user->id,
            ]);
            $conversation->participants()->attach($user->id);

            return $conversation;
        });

        return response()->json([
            'message' => 'Cuộc trò chuyện với Trợ lý Helpdesk đã sẵn sàng.',
            'data' => $this->loadConversation($conversation, $user),
        ], 201);
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorizeParticipant($request, $conversation);
        $data = $request->validate(['after_id' => ['nullable', 'integer', 'min:0']]);
        $messages = $conversation->messages()
            ->with('sender:id,name,role')
            ->when(($data['after_id'] ?? 0) > 0, fn ($query) => $query->where('id', '>', $data['after_id']))
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->map(fn (ChatMessage $message) => $this->messageData($message));

        $conversation->participants()->updateExistingPivot($request->user()->id, ['last_read_at' => now()]);

        return response()->json(['data' => $messages]);
    }

    public function sendMessage(Request $request, Conversation $conversation, ChatAssistant $assistant): JsonResponse
    {
        $this->authorizeParticipant($request, $conversation);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $body = trim($data['body']);
        if ($body === '') {
            throw ValidationException::withMessages(['body' => 'Nội dung tin nhắn không được để trống.']);
        }

        $message = $conversation->messages()->create([
            'user_id' => $request->user()->id,
            'sender_type' => 'user',
            'body' => $body,
        ]);
        $conversation->touch();
        $result = ['data' => $this->messageData($message->load('sender:id,name,role'))];

        if ($conversation->type === 'assistant') {
            $reply = trim($assistant->reply($request->user(), $conversation, $body));
            if ($reply !== '') {
                $assistantMessage = $conversation->messages()->create([
                    'sender_type' => 'assistant',
                    'body' => $reply,
                ]);
                $conversation->touch();
                $result['assistant'] = $this->messageData($assistantMessage);
            }
        }

        return response()->json(['message' => 'Đã gửi tin nhắn.', ...$result], 201);
    }

    private function authorizeParticipant(Request $request, Conversation $conversation): void
    {
        abort_unless(
            $conversation->participants()->whereKey($request->user()->id)->exists(),
            403,
            'Bạn không thuộc cuộc trò chuyện này.',
        );
    }

    private function loadConversation(Conversation $conversation, User $user): array
    {
        return $this->conversationData(
            $conversation->load([
                'participants:id,name,email,phone,role,avatar_path',
                'ticket:id,ticket_number,title',
                'lastMessage.sender:id,name,role',
            ]),
            $user,
        );
    }

    private function conversationData(Conversation $conversation, User $user): array
    {
        $participants = $conversation->participants
            ->where('id', '!=', $user->id)
            ->values()
            ->map(fn (User $participant) => $participant->only('id', 'name', 'email', 'phone', 'role', 'avatar_url'));
        $last = $conversation->lastMessage;

        return [
            'id' => $conversation->id,
            'type' => $conversation->type,
            'title' => $conversation->type === 'assistant'
                ? 'Trợ lý Helpdesk'
                : ($conversation->ticket?->ticket_number ?? $participants->first()['name'] ?? 'Cuộc trò chuyện'),
            'participants' => $participants,
            'ticket' => $conversation->ticket?->only('id', 'ticket_number', 'title'),
            'last_message' => $last ? $this->messageData($last) : null,
            'updated_at' => $conversation->updated_at,
        ];
    }

    private function messageData(ChatMessage $message): array
    {
        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'user_id' => $message->user_id,
            'sender_type' => $message->sender_type,
            'sender' => $message->sender?->only('id', 'name', 'role'),
            'body' => $message->body,
            'created_at' => $message->created_at,
        ];
    }
}
