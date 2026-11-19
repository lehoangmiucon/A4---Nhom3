<?php

namespace App\Services\Support;

use App\Models\Equipment;
use App\Models\Room;
use App\Models\SupportSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SessionCreationService
{
    public function create(User $actor, array $input): array
    {
        $payload = [
            'room_id' => (int) $input['room_id'],
            'equipment_id' => isset($input['equipment_id']) ? (int) $input['equipment_id'] : null,
            'description' => $input['description'],
        ];
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return DB::transaction(function () use ($actor, $input, $payload, $hash) {
            // All session-creation intents for one account share this short lock.
            // Never call an external API while holding it.
            $lockedActor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedActor->can('create', SupportSession::class), 403);
            $keys = DB::table('support_idempotency_keys')->where([
                'actor_id' => $actor->id, 'operation' => 'session.create', 'key' => hash('sha256', $input['idempotency_key']),
            ]);
            if ($existing = $keys->first()) {
                if (! hash_equals($existing->request_hash, $hash)) {
                    throw ValidationException::withMessages(['idempotency_key' => 'IDEMPOTENCY_KEY_REUSED']);
                }

                return json_decode($existing->response_data, true, 512, JSON_THROW_ON_ERROR);
            }

            $room = Room::with('building')->whereKey($payload['room_id'])->lockForUpdate()->first();
            if (! $room) {
                throw ValidationException::withMessages(['room_id' => 'Phòng không còn tồn tại.']);
            }
            $equipment = $payload['equipment_id'] === null ? null
                : Equipment::whereKey($payload['equipment_id'])->where('room_id', $room->id)->lockForUpdate()->first();
            if ($payload['equipment_id'] !== null && ! $equipment) {
                throw ValidationException::withMessages(['equipment_id' => 'Thiết bị không còn thuộc phòng đã chọn.']);
            }
            $session = SupportSession::create([
                'user_id' => $actor->id, 'room_id' => $room->id, 'equipment_id' => $equipment?->id,
                'room_label' => $room->name, 'room_code' => $room->code,
                'building_label' => $room->building?->name, 'equipment_label' => $equipment?->name,
                'status' => 'collecting', 'version' => 1, 'last_activity_at' => now(),
            ]);
            $session->messages()->create(['role' => 'user', 'content_text' => $payload['description']]);
            $result = $session->load('initialMessage')->toSupportArray();
            DB::table('support_idempotency_keys')->insert([
                'actor_id' => $actor->id, 'operation' => 'session.create', 'key' => hash('sha256', $input['idempotency_key']),
                'request_hash' => $hash, 'response_data' => json_encode($result, JSON_THROW_ON_ERROR),
                'response_status' => 201, 'created_at' => now(), 'updated_at' => now(),
            ]);

            return $result;
        }, 3);
    }
}
