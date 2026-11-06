<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Xác thực tài khoản Helpdesk UTH</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;color:#19344d;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;mso-hide:all;">Mã xác thực Helpdesk UTH: {{ $code }}. Có hiệu lực trong 10 phút.</div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f1f5f9;">
        <tr><td align="center" style="padding:32px 16px;">
            <table role="presentation" width="560" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:560px;">
                <tr><td style="padding:0 0 20px 4px;">
                    <span style="font-size:26px;font-weight:700;letter-spacing:-1px;color:#087fc2;">Helpdesk UTH</span>
                    <div style="margin-top:5px;font-size:12px;color:#64748b;">HỆ THỐNG HỖ TRỢ PHÒNG HỌC</div>
                </td></tr>
                <tr><td style="height:5px;background-color:#168aca;border-radius:12px 12px 0 0;font-size:0;line-height:0;">&nbsp;</td></tr>
                <tr><td style="background-color:#ffffff;padding:32px 24px;border:1px solid #e2e8f0;border-top:0;border-radius:0 0 12px 12px;">
                    <div style="font-size:11px;font-weight:700;letter-spacing:2px;color:#087fc2;">XÁC THỰC EMAIL</div>
                    <h1 style="margin:12px 0 16px;font-size:26px;line-height:1.3;color:#19344d;">Chỉ còn một bước nữa!</h1>
                    <p style="margin:0 0 12px;font-size:15px;line-height:1.7;color:#475569;">Cảm ơn bạn đã đăng ký Helpdesk UTH. Nhập mã bên dưới để xác thực tài khoản và bắt đầu gửi yêu cầu hỗ trợ.</p>
                    <p style="margin:0 0 24px;font-size:13px;line-height:1.6;color:#64748b;word-break:break-all;">Tài khoản: <strong style="color:#19344d;">{{ $email }}</strong></p>
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                        <tr><td align="center" style="padding:24px 12px;background-color:#eff8ff;border:1px solid #d4eafa;border-radius:10px;">
                            <div style="font-size:11px;font-weight:700;letter-spacing:1px;color:#54738c;">MÃ XÁC THỰC CỦA BẠN</div>
                            <div style="margin:12px 0;font-family:Consolas,'Courier New',monospace;font-size:36px;font-weight:700;letter-spacing:6px;line-height:1.3;color:#087fc2;">{{ $code }}</div>
                            <div style="font-size:13px;color:#54738c;">Có hiệu lực trong <strong>10 phút</strong></div>
                        </td></tr>
                    </table>
                    <p style="margin:16px 0 24px;font-size:13px;line-height:1.6;text-align:center;color:#64748b;">Bấm nút bên dưới để tự điền mã OTP, sau đó bấm “Xác thực tài khoản” để hoàn tất đăng ký.</p>
                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="margin:0 auto;">
                        <tr><td align="center" bgcolor="#087fc2" style="border-radius:8px;">
                            <a href="{{ $verificationUrl }}" target="_blank" rel="noopener noreferrer" style="display:inline-block;padding:15px 24px;border:1px solid #087fc2;border-radius:8px;font-size:14px;font-weight:700;text-decoration:none;color:#ffffff;">Mở trang nhập OTP &rarr;</a>
                        </td></tr>
                    </table>
                    <div style="margin-top:28px;padding-top:20px;border-top:1px solid #e8eef4;">
                        <p style="margin:0 0 8px;font-size:12px;line-height:1.7;color:#64748b;"><strong style="color:#475569;">Bảo vệ tài khoản của bạn</strong><br>Không chia sẻ mã này với bất kỳ ai. Nếu bạn không yêu cầu đăng ký hoặc gửi lại mã, hãy bỏ qua email này.</p>
                    </div>
                </td></tr>
                <tr><td align="center" style="padding:20px 12px;font-size:11px;line-height:1.8;color:#7b8b9d;">Helpdesk UTH &middot; Nhóm 3<br>Email tự động từ hệ thống. Vui lòng không trả lời.</td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
