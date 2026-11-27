<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lỗi {{ $status }} · Helpdesk</title>
    <style>
        body{font-family:system-ui,sans-serif;background:#f4f8fb;color:#18334e;margin:0;min-height:100vh;display:grid;place-items:center}
        main{background:#fff;border:1px solid #e4edf4;border-radius:18px;padding:32px;max-width:520px;margin:20px}
        h1{margin-top:0;font-size:22px}p{line-height:1.6;color:#526b82}a{color:#087ec1}
    </style>
</head>
<body>
<main>
    <h1>Không thể hoàn tất yêu cầu ({{ $status }})</h1>
    <p>{{ $message }}</p>
    <a href="{{ url('/') }}">Quay về trang chủ</a>
</main>
</body>
</html>