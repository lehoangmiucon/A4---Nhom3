<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Helpdesk API · Swagger UI</title>
    <link rel="stylesheet" href="{{ url('/vendor/swagger-ui/swagger-ui.css') }}">
    <style>
        body{margin:0;background:#fafafa;color:#263238;font-family:Arial,sans-serif}
        .docs-header{background:#17324e;color:#fff;padding:18px 28px}
        .docs-header strong{font-size:22px}.docs-header span{color:#b9d0e3;margin-left:12px}
        .docs-help{max-width:1400px;margin:20px auto 0;padding:14px 18px;box-sizing:border-box;border:1px solid #dce6ee;border-radius:8px;background:#fff;font-size:14px;line-height:1.6}
        .docs-help p{margin:4px 0}
        .docs-note{color:#66788a;font-size:12px}
        #swagger-auth-status{color:#126ca6;font-size:13px}
        .swagger-ui .info,.swagger-ui .topbar{display:none}
        @media(max-width:700px){.docs-header{padding:16px}.docs-header span{display:block;margin:6px 0 0}.docs-help{margin:12px}}
    </style>
</head>
<body>
    <header class="docs-header"><strong>Helpdesk API</strong><span>Web &amp; Mobile</span></header>
    <section class="docs-help">
        <p><strong>Cách dùng:</strong> Đăng nhập → dán <b>access_token</b> vào <b>Authorize</b> → chọn API → <b>Execute</b>.</p>
        <p class="docs-note">OTP lấy từ email. Kết quả xem ở <b>Server response</b>.</p>
        <p id="swagger-auth-status" role="status" aria-live="polite" hidden></p>
    </section>
    <div id="swagger-ui" data-api-url="{{ url('/api') }}"></div>
    <script id="swagger-spec" type="application/json">@json($spec, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE)</script>
    <script src="{{ url('/vendor/swagger-ui/swagger-ui-bundle.js') }}"></script>
    <script src="{{ url('/shared/js/swagger-docs.js?v=20261114-short-copy3') }}"></script>
</body>
</html>
