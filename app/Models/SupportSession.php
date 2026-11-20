<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupportSession extends Model
{
    use SoftDeletes;

    public const STATUSES = ['collecting', 'awaiting_result', 'handoff_pending', 'handed_off', 'resolved', 'cancelled'];

    protected $fillable = [
        'user_id', 'room_id', 'equipment_id', 'room_label', 'room_code',
        'building_label', 'equipment_label', 'status', 'version', 'last_activity_at',
        'category_id', 'report_requested',
    ];

    protected function casts(): array
    {
        return ['version' => 'integer', 'last_activity_at' => 'datetime', 'report_requested' => 'boolean'];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'session_id');
    }

    public function initialMessage(): HasOne
    {
        return $this->hasOne(SupportMessage::class, 'session_id')->oldestOfMany();
    }

    public function report(): HasOne
    {
        return $this->hasOne(SupportReport::class, 'session_id');
    }

    public function canDelete(): bool
    {
        return ! $this->trashed() && $this->status === 'handed_off' && $this->report?->ticket?->status === 'closed';
    }

    /** Explicit public fields; never serialize the whole model into API responses. */
    public function toSupportArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'ticket_status' => $this->report?->ticket?->status,
            'is_completed' => $this->status === 'handed_off' && $this->report?->ticket?->status === 'closed',
            'can_delete' => $this->canDelete(),
            'version' => $this->version,
            'room_id' => $this->room_id,
            'room_label' => $this->room_label,
            'room_code' => $this->room_code,
            'building_label' => $this->building_label,
            'equipment_id' => $this->equipment_id,
            'equipment_label' => $this->equipment_label,
            'initial_message_id' => $this->initialMessage?->id,
            'initial_description' => $this->initialMessage?->content_text,
            'permitted_actions' => [],
            'handoff' => null,
            'last_activity_at' => $this->last_activity_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
