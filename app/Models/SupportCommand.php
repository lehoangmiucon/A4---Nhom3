<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportCommand extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['response_data' => 'array', 'version' => 'integer'];
    }
}
