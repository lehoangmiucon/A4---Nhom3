<link rel="stylesheet" href="{{ url('/shared/css/chat-widget.css?v=20261124-widget10') }}">
<div class="chat-widget" id="helpdesk-chat-widget">
    <button class="chat-widget-launcher" type="button" data-open-chat-widget aria-label="Mở khung tin nhắn">
        <svg class="chat-widget-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5.5h16v11H9l-5 3v-14Z"/><path d="M8 10h8M8 13h5"/></svg><span>Tin nhắn</span>
    </button>
    <section class="chat-widget-window" id="chat-widget-window" hidden aria-label="Khung chat Helpdesk">
        <header class="chat-widget-header">
            <span class="chat-widget-brand"><span class="chat-widget-brand-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 13v-1a8 8 0 0 1 16 0v1"/><rect x="3" y="12" width="4" height="7" rx="2"/><rect x="17" y="12" width="4" height="7" rx="2"/><path d="M17 19a5 5 0 0 1-5 3h-2"/></svg></span><span><strong>Helpdesk</strong><small>Trao đổi và hỗ trợ</small></span></span>
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
                @if(!in_array(auth()->user()->role, ['admin', 'staff'], true))
                    <label class="chat-search-label" for="chat-search">Tìm cuộc trò chuyện</label>
                    <div class="chat-search-wrap"><svg class="chat-search-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 5 5"/></svg><input id="chat-search" type="search" autocomplete="off" placeholder="Tìm nội dung hoặc mã phiếu"></div>
                @endif
                @if(in_array(auth()->user()->role, ['admin', 'staff'], true))
                    <label class="chat-search-label" for="chat-search">Tìm theo email hoặc số điện thoại</label>
                    <div class="chat-search-wrap"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input id="chat-search" type="search" autocomplete="off" placeholder="Nhập email hoặc số điện thoại"></div>
                    <div class="chat-contact-results" id="chat-contact-results" hidden></div>
                @endif
                <div class="chat-conversation-heading"><span>Cuộc trò chuyện</span><button type="button" id="chat-refresh" aria-label="Tải lại">↻</button></div>
                <div class="chat-conversation-list" id="chat-conversation-list" aria-live="polite"></div>
            </aside>
            <section class="chat-thread" id="chat-thread">
                <div class="chat-empty" id="chat-empty">
                    <div class="chat-empty-copy"><div class="chat-empty-icon">✉</div><h2>Trợ lý Helpdesk</h2><p>Nhập câu hỏi để được hướng dẫn sử dụng hệ thống.</p><button class="chat-empty-browse" type="button" id="chat-empty-browse">Xem các cuộc trò chuyện</button></div>
                    <p class="chat-empty-notice" id="chat-empty-notice" role="status" hidden></p>
                    <form class="chat-composer chat-empty-composer" id="chat-empty-composer"><textarea id="chat-empty-message-input" rows="1" maxlength="5000" placeholder="Nhập tin nhắn..." required></textarea><button type="submit" id="chat-empty-send" aria-label="Gửi tin nhắn"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i></button></form>
                </div>
                <div class="chat-active" id="chat-active" hidden>
                    <header class="chat-thread-header">
                        <button type="button" class="chat-mobile-back" id="chat-mobile-back" aria-label="Quay lại danh sách cuộc trò chuyện" title="Chọn cuộc trò chuyện khác"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg></button>
                        <div class="chat-avatar" id="chat-avatar">H</div>
                        <div class="chat-thread-title"><strong id="chat-title"></strong><small id="chat-subtitle"></small></div>
                    </header>
                    <div class="chat-ticket-context" id="chat-ticket-context" hidden></div>
                    <div class="chat-messages" id="chat-messages" aria-live="polite"></div>
                    <form class="chat-composer" id="chat-composer"><textarea id="chat-message-input" rows="1" maxlength="5000" placeholder="Nhập tin nhắn..." aria-label="Tin nhắn" required></textarea><button type="submit" id="chat-send">Gửi</button></form>
                </div>
            </section>
            <p class="chat-notice" id="chat-notice" aria-live="polite" aria-atomic="true" hidden></p>
        </section>
    </section>
</div>
<script>
    window.HELPDESK_CHAT_CONFIG = {
        apiBase: @json(url('/api/chat')),
        currentUserId: @json(auth()->id()),
        currentUserName: @json(auth()->user()->name),
        canSearchContacts: @json(auth()->user()->role === 'admin' || auth()->user()->role === 'staff')
    };
</script>
<script src="{{ url('/shared/js/chat.js?v=20261124-widget9') }}" defer></script>
