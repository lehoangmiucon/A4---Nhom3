<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $passwordReset ? 'Đặt lại mật khẩu Helpdesk' : 'Xác thực email Helpdesk' }}</title>
</head>
<body style="margin:0;padding:24px 12px;background:#f3f7fc;font-family:Arial,sans-serif;color:#29435d">
    <table role="presentation" style="width:100%;max-width:560px;margin:auto;border-spacing:0;background:#fff;border:1px solid #dce8f3;border-radius:16px;overflow:hidden">
        <tr><td style="padding:24px 28px;background:#153e70;color:#fff;font-size:24px;font-weight:bold">Helpdesk <span style="font-size:13px;font-weight:normal">· Hỗ trợ phòng học</span></td></tr>
        <tr><td style="padding:28px">
            <h1 style="margin:0 0 18px;font-size:23px;color:#173e65">{{ $passwordReset ? 'Đặt lại mật khẩu' : 'Xác thực email đăng ký' }}</h1>
            <p style="font-size:15px;line-height:1.7">Xin chào {{ $name }},</p>
            <p style="font-size:15px;line-height:1.7">{{ $passwordReset ? 'Nhập mã dưới đây để đặt lại mật khẩu tài khoản Helpdesk của bạn:' : 'Nhập mã dưới đây vào trang xác thực để hoàn tất đăng ký tài khoản sinh viên:' }}</p>
            <div style="padding:22px 10px;margin:24px 0;border:1px solid #cce2f6;border-radius:12px;background:#f0f7ff;text-align:center;font-size:34px;font-weight:bold;letter-spacing:8px;color:#157dc4">{{ $code }}</div>
            <p style="font-size:14px;line-height:1.7">Mã có hiệu lực trong <strong>{{ $expiresMinutes }} phút</strong> và chỉ sử dụng một lần. Không chia sẻ mã này với người khác.</p>
            <p style="font-size:13px;line-height:1.7;color:#7a8da0">{{ $passwordReset ? 'Nếu bạn không yêu cầu đặt lại mật khẩu, hãy bỏ qua email này và giữ an toàn cho tài khoản.' : 'Nếu bạn không đăng ký tài khoản Helpdesk, hãy bỏ qua email này.' }}</p>
        </td></tr>
        <tr><td style="padding:18px 28px;border-top:1px solid #e7eef5;font-size:12px;color:#8394a5;text-align:center">Email tự động từ hệ thống Helpdesk trường học.</td></tr>
    </table>
</body>
</html>
