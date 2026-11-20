@if(!($embedded ?? false))
<button id="portal-chat-launch" class="portal-chat-launch" type="button" popovertarget="portal-chat" aria-controls="portal-chat" hidden>
    <span class="portal-chat-icon" aria-hidden="true">···</span> Hỏi AI
</button>
@endif

<section id="portal-chat" class="{{ ($embedded ?? false) ? 'support-view support-chat-view' : 'portal-chat' }}" @if($embedded ?? false) data-embedded="true" hidden @else popover="auto" @endif aria-labelledby="portal-chat-title" data-endpoint="{{ route('customer.chat') }}">
    @if(!($embedded ?? false))
    <header class="portal-chat-header">
        <h2 id="portal-chat-title">Trợ lý Helpdesk AI</h2>
        <button id="portal-chat-close" type="button" popovertarget="portal-chat" popovertargetaction="hide" aria-label="Đóng chat">×</button>
    </header>
    @else
    <h3 id="portal-chat-title" class="support-summary-title">Hỏi trợ lý AI</h3>
    <p class="portal-chat-status">Chat AI hiện chưa được lưu vào phiên hỗ trợ. Bạn có thể dùng câu trả lời để điền nháp vào phiếu.</p>
    @endif
    <div id="portal-chat-log" class="portal-chat-log" role="log" aria-label="Cuộc trò chuyện" tabindex="0">
        <p class="portal-chat-message">Bạn gặp sự cố gì? Hãy kể tên thiết bị và dấu hiệu bạn thấy. Mình có thể hướng dẫn kiểm tra và soạn nháp phiếu.</p>
    </div>
    <p id="portal-chat-status" class="portal-chat-status" aria-live="polite" aria-atomic="true"></p>
    <button id="portal-chat-draft" class="portal-chat-draft" type="button" hidden>Điền nháp vào phiếu</button>
    <form id="portal-chat-form" class="portal-chat-form">
        <input id="portal-chat-input" type="text" maxlength="1200" required placeholder="Mô tả sự cố…" aria-label="Tin nhắn" aria-describedby="portal-chat-privacy">
        <button type="submit">Gửi</button>
    </form>
    <p id="portal-chat-privacy" class="portal-chat-privacy">Tin nhắn được gửi đến OpenAI. Tránh nhập mật khẩu, OTP và thông tin cá nhân.</p>
</section>
