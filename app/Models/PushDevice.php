<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushDevice extends Model
{
    protected $fillable = ['user_id', 'token_hash', 'token', 'platform'];
    protected $hidden = ['token', 'token_hash'];
}
