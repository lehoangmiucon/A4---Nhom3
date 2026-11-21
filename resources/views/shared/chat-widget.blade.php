<link rel="stylesheet" href="{{ url('/shared/css/chat-widget.css?v=20261121-widget1') }}">
<div class="chat-widget" id="helpdesk-chat-widget">
    <button class="chat-widget-launcher" type="button" data-open-chat-widget aria-label="Mở khung tin nhắn">
        <i class="fa-solid fa-comments" aria-hidden="true"></i><span>Tin nhắn</span>
    </button>
    <section class="chat-widget-window" id="chat-widget-window" hidden aria-label="Khung chat Helpdesk">
        <header class="chat-widget-header">
            <span class="chat-widget-brand"><i class="fa-solid fa-headset" aria-hidden="true"></i><span><strong>Helpdesk</strong><small>Trao đổi và hỗ trợ</small></span></span>
            <button class="chat-widget-close" type="button" data-close-chat-widget aria-label="Đóng khung chat">×</button>
        </header>
        <section class="chat-frame chat-widget-frame" aria-label="Tin nhắn Helpdesk">
            <aside class="chat-sidebar" id="chat-sidebar">
                <div class="chat-sidebar-heading">
                    <div><p class="chat-eyebrow">HELPDESK</p><h2>Tin nhắn</h2></div>
                    <span class="chat-count" id="conversation-count">0</span>
                </div>
                <button class="chat-ai-button" id="chat-ai" type="button">
                    <span class="chat-ai-icon">✦</span>
                    <span><strong>Hỏi Trợ lý Helpdesk</strong><small>Hướng dẫn sử dụng hệ thống</small></span>
                    <span aria-hidden="true">›</span>
                </button>
                <label class="chat-search-label" for="chat-search">Tìm theo email hoặc số điện thoại</label>
                <div class="chat-search-wrap"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input id="chat-search" type="search" autocomplete="off" placeholder="Nhập email hoặc số điện thoại"></div>
                <div class="chat-contact-results" id="chat-contact-results" hidden></div>
                <div class="chat-conversation-heading"><span>Cuộc trò chuyện</span><button type="button" id="chat-refresh" aria-label="Tải lại">↻</button></div>
                <div class="chat-conversation-list" id="chat-conversation-list" aria-live="polite"></div>
            </aside>
            <section class="chat-thread" id="chat-thread">
                <div class="chat-empty" id="chat-empty"><div class="chat-empty-icon">✉</div><h2>Trao đổi công việc</h2><p>Chọn một cuộc trò chuyện, tìm người dùng hoặc hỏi Trợ lý Helpdesk.</p></div>
                <div class="chat-active" id="chat-active" hidden>
                    <header class="chat-thread-header">
                        <button type="button" class="chat-mobile-back" id="chat-mobile-back" aria-label="Danh sách">‹</button>
                        <div class="chat-avatar" id="chat-avatar">H</div>
                        <div class="chat-thread-title"><strong id="chat-title"></strong><small id="chat-subtitle"></small></div>
                    </header>
                    <div class="chat-ticket-context" id="chat-ticket-context" hidden></div>
                    <div class="chat-messages" id="chat-messages" aria-live="polite"></div>
                    <form class="chat-composer" id="chat-composer"><textarea id="chat-message-input" rows="1" maxlength="5000" placeholder="Nhập tin nhắn..." required></textarea><button type="submit" id="chat-send" aria-label="Gửi tin nhắn"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i></button></form>
                    <p class="chat-notice" id="chat-notice" role="status" hidden></p>
                </div>
            </section>
        </section>
    </section>
</div>
<script>
    window.HELPDESK_CHAT_CONFIG = {
        apiBase: @json(url('/api/chat')),
        currentUserId: @json(auth()->id()),
        currentUserName: @json(auth()->user()->name)
    };
</script>
<script src="{{ url('/shared/js/chat.js?v=20261121-widget2') }}" defer></script>
