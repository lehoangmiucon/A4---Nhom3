<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    protected $fillable = [
        'ticket_number', 'reporter_id', 'reporter_name', 'reporter_email', 'reporter_phone',
        'room_id', 'room_label', 'building_label', 'equipment_id', 'equipment_label', 'category_id', 'assigned_to_id',
        'title', 'description', 'priority', 'status', 'resolution_note', 'resolved_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(TicketLog::class)->latest();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    public function reportAttachments(): HasMany
    {
        return $this->attachments()->where('attachment_type', 'report');
    }

    public function repairAttachments(): HasMany
    {
        return $this->attachments()->where('attachment_type', 'repair');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(TicketSchedule::class);
    }
}
