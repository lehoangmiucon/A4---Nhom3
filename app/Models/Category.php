<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    protected $fillable = ['name', 'color'];

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class);
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'staff_categories', 'category_id', 'staff_id')->withTimestamps();
    }
}
