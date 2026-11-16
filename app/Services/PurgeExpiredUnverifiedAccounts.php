<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class PurgeExpiredUnverifiedAccounts
{
    public function handle(): int
    {
        $cutoff = now()->subHours((int) config('auth.unverified_account_expire_hours', 24));

        return DB::transaction(function () use ($cutoff): int {
            $users = User::query()
                ->whereIn('role', ['student', 'teacher'])
                ->whereNull('email_verified_at')
                ->where('created_at', '<=', $cutoff)
                ->lockForUpdate()
                ->get(['id', 'email']);

            if ($users->isEmpty()) {
                return 0;
            }

            $ids = $users->modelKeys();
            $emails = $users->pluck('email');

            DB::table('email_otps')->whereIn('email', $emails)->delete();
            DB::table('notifications')
                ->where('notifiable_type', User::class)
                ->whereIn('notifiable_id', $ids)
                ->delete();
            DB::table('personal_access_tokens')
                ->where('tokenable_type', User::class)
                ->whereIn('tokenable_id', $ids)
                ->delete();
            User::whereIn('id', $ids)->delete();

            return count($ids);
        });
    }
}
