<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#10233f">
    <script>
        window.HELPDESK_CONFIG = {
            apiEnabled: true,
            appUrl: @json(url('/')),
            apiBase: "/api"
        };
    </script>
    <title>@yield('title', 'Helpdesk quản trị')</title>
    <link rel="stylesheet" href="{{ url('/admin/assets/css/style.css?v=20261103-admin-layout1') }}">
    <link rel="stylesheet" href="{{ url('/admin/assets/css/extra.css?v=20261110-priority-labels1') }}">
    @if(in_array($pagePath ?? '', ['pages/categories', 'pages/team', 'pages/ticket-review', 'pages/notifications'], true))
        <link rel="stylesheet" href="{{ url('/admin/assets/css/roles.css') }}">
    @endif
    <link rel="stylesheet" href="{{ url('/admin/assets/css/ticket-status.css?v=20261104-status2') }}">
    @if(($pagePath ?? '') === 'pages/notifications')
        <link rel="stylesheet" href="{{ url('/admin/assets/css/notifications.css?v=20261110-read-all1') }}">
    @endif
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css" crossorigin="anonymous">
    @stack('styles')
</head>
<body>
    <input class="nav-toggle" type="checkbox" id="nav-toggle" aria-hidden="true">
    <div class="app-shell">
        @include('admin.layouts.sidebar')
        <main class="main-area">
            @include('admin.layouts.topbar')
            <div class="page-content @yield('content_class')">
                @yield('content')
            </div>
            <footer class="page-footer">HELPDESK <span>·</span> TRƯỜNG HỌC</footer>
        </main>
    </div>
    <label class="nav-scrim" for="nav-toggle" aria-label="Đóng menu"></label>
    @yield('scripts')
    <script src="{{ url('/admin/assets/js/image-lightbox.js?v=20261104-lightbox1') }}"></script>
    <script src="{{ url('/admin/assets/js/period-select.js?v=20261103-period-menu2') }}"></script>
    <script src="{{ url('/admin/assets/js/notification-groups.js?v=20261110-read-all1') }}"></script>
    <script src="{{ url('/admin/assets/js/native-selects.js?v=20261110-custom-select1') }}"></script>
</body>
</html>
