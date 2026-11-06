<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeHelpdeskAdmin extends Command
{
    protected $signature = 'helpdesk:make-admin {email : Email của tài khoản đã đăng ký}';
    protected $description = 'Cấp vai trò quản trị cho một tài khoản đã xác thực email.';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (!$user) {
            $this->error('Không tìm thấy tài khoản. Hãy đăng ký và xác thực email trước.');
            return self::FAILURE;
        }
        if (!$user->email_verified_at) {
            $this->error('Email chưa được xác thực.');
            return self::FAILURE;
        }

        $user->update(['role' => 'admin']);
        $this->info("{$user->email} đã được cấp vai trò admin.");
        return self::SUCCESS;
    }
}
