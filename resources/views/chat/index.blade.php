<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Tin nhắn · Helpdesk</title>
    <link rel="stylesheet" href="{{ url('/shared/css/chat.css?v=20261121-chat1') }}">
</head>
<body>
    <header class="chat-topbar">
        <a class="chat-brand" href="{{ url(auth()->user()->role === 'admin' ? '/admin/index' : (auth()->user()->role === 'staff' ? '/staff/index' : '/customer/index')) }}">
            <span class="chat-brand-mark">H</span><strong>Helpdesk</strong>
        </a>
        <div class="chat-topbar-right">
            <span>{{ auth()->user()->name }}</span>
            <a class="chat-back-link" href="{{ url(auth()->user()->role === 'admin' ? '/admin/index' : (auth()->user()->role === 'staff' ? '/staff/index' : '/customer/index')) }}">Quay lại</a>
        </div>
    </header>
    <main class="chat-page">
        <section class="chat-frame" aria-label="Tin nhắn Helpdesk">
            <aside class="chat-sidebar" id="chat-sidebar">
                <div class="chat-sidebar-heading">
                    <div><p class="chat-eyebrow">HELPDESK</p><h1>Tin nhắn</h1></div>
                    <span class="chat-count" id="conversation-count">0</span>
                </div>
                <button class="chat-ai-button" id="chat-ai" type="button">
                    <span class="chat-ai-icon">✦</span>
                    <span><strong>Hỏi Trợ lý Helpdesk</strong><small>Hỏi về cách sử dụng hệ thống</small></span>
                    <span aria-hidden="true">›</span>
                </button>
                <label class="chat-search-label" for="chat-search">Tìm người theo email hoặc số điện thoại</label>
                <div class="chat-search-wrap">
                    <span aria-hidden="true">⌕</span>
                    <input id="chat-search" type="search" autocomplete="off" placeholder="Nhập email hoặc số điện thoại">
                </div>
                <div class="chat-contact-results" id="chat-contact-results" hidden></div>
                <div class="chat-conversation-heading"><span>Cuộc trò chuyện</span><button type="button" id="chat-refresh" aria-label="Tải lại">↻</button></div>
                <div class="chat-conversation-list" id="chat-conversation-list" aria-live="polite"></div>
            </aside>
            <section class="chat-thread" id="chat-thread">
                <div class="chat-empty" id="chat-empty">
                    <div class="chat-empty-icon">✉</div>
                    <h2>Trao đổi công việc</h2>
                    <p>Chọn một cuộc trò chuyện hoặc tìm người theo email, số điện thoại để nhắn tin.</p>
                </div>
                <div class="chat-active" id="chat-active" hidden>
                    <header class="chat-thread-header">
                        <button type="button" class="chat-mobile-back" id="chat-mobile-back" aria-label="Danh sách">‹</button>
                        <div class="chat-avatar" id="chat-avatar">H</div>
                        <div class="chat-thread-title"><strong id="chat-title"></strong><small id="chat-subtitle"></small></div>
                        <span class="chat-live-dot" aria-label="Trò chuyện trực tuyến"></span>
                    </header>
                    <div class="chat-ticket-context" id="chat-ticket-context" hidden></div>
                    <div class="chat-messages" id="chat-messages" aria-live="polite"></div>
                    <form class="chat-composer" id="chat-composer">
                        <textarea id="chat-message-input" rows="1" maxlength="5000" placeholder="Nhập tin nhắn..." required></textarea>
                        <button type="submit" id="chat-send" aria-label="Gửi tin nhắn">Gửi</button>
                    </form>
                    <p class="chat-notice" id="chat-notice" role="status" hidden></p>
                </div>
            </section>
        </section>
    </main>
    <script>
        window.HELPDESK_CHAT_CONFIG = {
            apiBase: @json(url('/api/chat')),
            currentUserId: @json(auth()->id()),
            currentUserName: @json(auth()->user()->name)
        };
    </script>
    <script src="{{ url('/shared/js/chat.js?v=20261121-chat1') }}" defer></script>
</body>
</html>
