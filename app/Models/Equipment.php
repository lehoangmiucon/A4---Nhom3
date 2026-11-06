<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Equipment extends Model
{
    protected $table = 'equipment';

    protected $fillable = ['room_id', 'category_id', 'name', 'status'];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
