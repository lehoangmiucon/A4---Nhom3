<?php

namespace App\Support;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PasswordPolicy
{
    public static function ensureDifferent(string $password, string $currentHash): void
    {
        if (Hash::check($password, $currentHash)) {
            throw ValidationException::withMessages([
                'password' => __('validation.custom.password.different'),
            ]);
        }
    }
}
