<?php

namespace App\Policies;

use App\Models\SupportSession;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SupportSessionPolicy
{
    public function create(User $user): bool
    {
        return ! $user->is_deleted && $user->email_verified_at !== null
            && in_array($user->role, ['teacher', 'student'], true);
    }

    public function view(User $user, SupportSession $session): Response
    {
        return $this->create($user) && (int) $session->user_id === (int) $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
