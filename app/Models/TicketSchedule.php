<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketSchedule extends Model
{
    protected $fillable = ['ticket_id', 'staff_id', 'created_by_id', 'starts_at', 'ends_at', 'note', 'status', 'acknowledged_at', 'cancelled_at', 'cancellation_note'];
    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'acknowledged_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id')->withTrashed();
    }
}
