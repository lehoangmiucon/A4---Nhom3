<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketAttachment extends Model
{
    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        if ($this->attachment_type === 'repair') {
            return route('tickets.attachments.show', ['ticket' => $this->ticket_id, 'attachment' => $this->id]);
        }

        return url(\Illuminate\Support\Facades\Storage::disk('public')->url($this->path));
    }

    protected $fillable = ['ticket_id', 'uploaded_by_id', 'path', 'original_name', 'attachment_type'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }
}
