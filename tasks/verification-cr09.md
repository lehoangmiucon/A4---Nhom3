# CR-09 — hiệu ứng hiện dần chữ

Ngày 20/11/2026. DONE_CODE; chưa smoke browser thực hoặc RELEASED. Task chính ở todo.md. Phạm vi UI-03, AI-FR-014, AI-NFR-008; TC-018/021/025 phần lifecycle client. Người dùng yêu cầu hiệu ứng sinh văn bản giống các mô hình ngôn ngữ khác.

## Hành vi

- Giữ ba chấm chờ trong thời gian request. Khi có JSON thành công của lượt chat, chỉ reply trợ lý cuối hiện dần theo nhóm ký tự, con trỏ nhấp nháy; tối đa 80 nhịp 20ms (~1,6 giây nếu tab hoạt động bình thường). Dùng Array.from để không tách surrogate pair Unicode, giữ nguyên full text server. Không innerHTML.
- Nút Hiện toàn bộ bỏ chờ và hiện chính xác nội dung đầy đủ. Lịch sử khi load/reload và xác nhận report/resolve không chạy lại hiệu ứng. prefers-reduced-motion hiển thị đầy đủ ngay.
- Timer được dừng/kết thúc khi render lại, tạo mới, gửi tiếp, đọc phiên khác, chuyển sang thủ công hoặc logout; không khôi phục nội dung đã dọn. Chỉ tự cuộn nếu đang gần cuối log, không kéo người đang đọc phía trên xuống.
- Visual text aria-hidden; bản đầy đủ trong span riêng dành cho screen reader, tránh announce từng nhịp. Không coi kiểm tra cấu trúc này là UAT screen reader thực.
- Asset CSS/JS version 20261120-cr9. Không đổi API/model, không tăng request, không sửa bản lưu hoặc nghiệp vụ phiếu. **Đây là typewriter sau nhận JSON, chưa streaming token thực**; không giảm thời gian model sinh câu trả lời.

## TDD và kết quả

Thêm ba test đầu: `node --test tests/js/support-conversation.test.mjs` RED **2 failed / 18 passed**, do chưa có nội dung hiển thị dần. Ca history/reduced-motion trước sửa đã đạt nên dùng làm guard hồi quy.

Sau triển khai cùng file **20 passed**. Thêm guard logout/không ép cuộn. `node --test tests/js/*.test.mjs`: **46 passed**, gồm 21 ca hội thoại. Test dùng fake timer 20ms kiểm tra nội dung tăng, hoàn tất chính xác, bấm skip, hủy timer khi tạo mới/logout và reduced-motion/history không có timer; không đợi animation thật.

`php artisan test --compact --filter=SupportSessionUiTest`: **7 passed / 49 assertions**, 1,39 giây. SQLite :memory:/HTTP fake; chạy ngoài sandbox do lỗi Windows PHPUnit đã biết.

`git diff --check`: đạt; cảnh báo CRLF/LF ở thay đổi đã có.

Không chạy lại toàn PHP suite vì chỉ sửa JS/CSS và version asset Blade. Không gọi model hoặc ghi DB đang dùng. Chưa kiểm tra trực quan trên Edge/browser thật; các lần Browser Use trước bị hạn chế URL/localhost nên không tuyên bố đã smoke. Token streaming, markdown/code highlighting, screen reader thực, staging/production không nằm trong kiểm chứng lượt này.
