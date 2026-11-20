<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends Model
{
    protected $fillable = ['session_id', 'role', 'content_text', 'client_message_id', 'handoff_brief'];

    protected function casts(): array
    {
        return ['handoff_brief' => 'array'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(SupportSession::class, 'session_id');
    }
}
