<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SupportSession extends Model
{
    public const STATUSES = ['collecting', 'awaiting_result', 'handoff_pending', 'handed_off', 'resolved', 'cancelled'];

    protected $fillable = [
        'user_id', 'room_id', 'equipment_id', 'room_label', 'room_code',
        'building_label', 'equipment_label', 'status', 'version', 'last_activity_at',
    ];

    protected function casts(): array
    {
        return ['version' => 'integer', 'last_activity_at' => 'datetime'];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'session_id');
    }

    public function initialMessage(): HasOne
    {
        return $this->hasOne(SupportMessage::class, 'session_id')->oldestOfMany();
    }

    /** Explicit public fields; never serialize the whole model into API responses. */
    public function toSupportArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'version' => $this->version,
            'room_id' => $this->room_id,
            'room_label' => $this->room_label,
            'room_code' => $this->room_code,
            'building_label' => $this->building_label,
            'equipment_id' => $this->equipment_id,
            'equipment_label' => $this->equipment_label,
            'initial_message_id' => $this->initialMessage?->id,
            'permitted_actions' => [],
            'handoff' => null,
            'last_activity_at' => $this->last_activity_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
