<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class EmailVerificationOtp extends Mailable
{
    public function __construct(
        public string $name,
        public string $code,
        public int $expiresMinutes = 5,
        public bool $passwordReset = false,
    ) {}

    public function build(): self
    {
        return $this->subject($this->passwordReset ? 'Mã đặt lại mật khẩu Helpdesk' : 'Mã xác thực tài khoản Helpdesk')
            ->view('emails.verify-otp');
    }
}
