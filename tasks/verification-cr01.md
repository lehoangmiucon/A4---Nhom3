# Kiểm chứng CR-01 — hai cách báo sự cố

Ngày cập nhật: 20/11/2026. Trạng thái: **DONE_CODE local**, chưa RELEASED. Snapshot là working tree đang có thay đổi chưa commit; giữ nguyên các thay đổi ngoài phạm vi. Checklist duy nhất: [todo.md](todo.md).

## Yêu cầu và kết quả

Người dùng yêu cầu báo thủ công như trước hoặc trò chuyện AI để xử lý sự cố; khi không khắc phục được, có thể yêu cầu gửi báo cáo ngay trong chat, không điền lại form. CR-01 thay giao diện popup/điền nháp trước đó bằng hai chế độ trên portal. Không còn hai launcher nổi.

- Luồng thủ công tiếp tục dùng API phiếu và quy tắc ưu tiên/gộp cũ qua TicketCreationService.
- Luồng AI lưu tin trên server, đọc lại lịch sử, hỏi phòng/danh mục/mô tả thiếu; hỗ trợ yêu cầu “Gửi báo cáo giúp tôi” hoặc nút gửi ngay trong chat. Nội dung chưa gửi trong composer được đưa vào yêu cầu báo cáo.
- Backend quyết định tạo phiếu từ lời người dùng đã lưu, không tin lịch sử/facts/priority/tác vụ model hoặc client tự gán. Report giữ transcript; phiếu ghi rõ nguồn và dữ liệu chưa được KTV xác minh.
- TX-A lưu command/idempotency và lượt người dùng; provider chạy ngoài transaction. TX-B commit report, phiếu, log, audit, DB notification và kết quả command cùng nhau. Lỗi TX-B giữ receipt để retry. Một phiên chỉ có một report; phiên khác không tự gộp chỉ vì cùng phòng/danh mục.
- Gửi báo cáo không cần model và không bị khóa theo quota chat. Kết quả AI đến muộn sau bàn giao bị loại. Chỉ hiển thị thành công khi có phiếu từ server.

Truy vết: AI-FR-002/003/007/008/014/017/018, AI-NFR-001/003/004/008; UC-01/02/04/05/12, nhóm TC-003/004/006/011/012/018/021/023/025/026/027/030 trong phạm vi trên. Không coi bằng chứng này là hoàn thành toàn bộ các TC hoặc M2/M3/M4.

## RED → GREEN và review

Baseline trước CR-01: PHP 143 passed / 1956 assertions; JS 30 passed. BASE-001 đã được sửa trong review M1b.

- Nhóm đầu chín test hội thoại RED do endpoint chưa tồn tại (404), sau đó triển khai service/controller/receipt và đạt GREEN.
- Bổ sung ca yêu cầu báo cáo chưa có dấu hiệu sự cố và ca giới hạn tin nhắn: RED cho thấy có thể tạo phiếu thiếu mô tả hoặc chặn cả đường báo trực tiếp; đã sửa và GREEN.
- Test JS RED khi nhấn gửi báo cáo với nội dung còn trong composer bị bỏ mất; đã gửi nội dung này cùng action report và GREEN.
- Expectation UI cũ về popup/điền nháp được đổi theo yêu cầu mới của người dùng. Các kiểm thử API cũ vẫn giữ và đạt.
- Hồi quy Swagger response catalog và auth khi command ID mẫu là `1`: sửa production generator và route để giữ convention tài liệu hiện có, xác thực trước object lookup; không hạ expectation test.
- Review tập trung quyền chủ phiên, facts server, retry, version, giới hạn, thông báo, rollback, trạng thái muộn và giữ nguyên form cũ. Pint chỉ áp dụng file thuộc thay đổi; không format toàn repo.

## Lệnh và kết quả thực

Môi trường: Windows, PHP 8.3.36, Laravel 12; test backend dùng SQLite `:memory:` và fake HTTP/notification theo test. Không gọi API trả phí.

| Lệnh / kiểm tra | Kết quả |
| --- | --- |
| `php artisan test --compact` | **158 passed, 2119 assertions**, 11.37 giây |
| `php artisan test --compact --filter=SupportConversationTest` | **15 passed, 98 assertions** |
| `php artisan test --compact --filter="HelpdeskChatTest\|MobileApiTest"` | 31 passed, 609 assertions sau tách service |
| `php artisan test --compact --filter="MobileApiTest\|SwaggerDocumentationTest"` | 30 passed, 1280 assertions sau sửa hồi quy |
| `node --test tests/js/*.test.mjs tests/JavaScript/*.test.cjs` | **38 passed**, gồm 8 ca CR-01 |
| `node --check public/customer/assets/js/support-conversation.js` | Đạt |
| `git diff --check` | Đạt; có cảnh báo chuẩn hóa CRLF của Git |
| Chrome headless, API giả | **320/768/1024/1440 × 900 đều PASS**, không JS error/unhandled rejection |

Các ca backend bao gồm chat rồi tạo phiếu, thiếu ngữ cảnh/mô tả, phủ định/điều kiện không tự gửi, idempotency replay/mismatch, khác chủ phiên, facts giả, stale version, provider lỗi/tuyên bố gửi giả, quota, không gộp quá rộng, resolved chưa tạo phiếu, TX-B lỗi sau notification và retry một lần, kết quả AI muộn, giới hạn tin vẫn cho báo cáo.

Smoke browser kiểm tra hai chế độ, giữ nháp thủ công, không tự POST model khi đổi chế độ, lỗi mạng/retry giữ key, lịch sử, gửi phiếu và receipt trong chat, cuộc trò chuyện mới, lỗi danh sách/thử lại và responsive. Lần khởi động lại harness dùng sai document root khiến asset 404 và FAIL AI mode; đã dùng `-t public`, chạy lại cả bốn viewport đạt. Đây là sửa cấu hình harness, không thay đổi ứng dụng để làm xanh.

Harness local (ignored) ở `storage/framework/testing/cr01/`: `render.php`, `router.php`, `browser-check.html`, `browser-check.cjs`. Máy chủ kiểm thử: `php -S 127.0.0.1:8124 -t public storage/framework/testing/cr01/router.php`; chạy Chrome bằng `node storage/framework/testing/cr01/browser-check.cjs`. Render dùng model fixture, fetch được thay bằng fake, không gửi phiếu thật vào DB local. Harness chỉ dùng nội bộ và không có route production.

Bằng chứng lưu: [kết quả browser](evidence/cr01/browser-results.json), [mobile 320px](evidence/cr01/viewport-320.png), [desktop 1440px](evidence/cr01/viewport-1440.png), [readiness không chứa secret](evidence/cr01/readiness.json).

## Migration và provider

Đã xác nhận environment local và chạy đúng hai migration bổ sung, không xóa/reset dữ liệu:

```powershell
php artisan migrate --path=database/migrations/2026_11_19_000001_create_support_session_tables.php --path=database/migrations/2026_11_19_120000_add_support_conversation_workflow.php --force
php artisan migrate:status
```

Cả hai Ran, batch 15. Không dùng migrate:fresh. Readiness xác nhận `support_schema_ready=true`, `ai_configured=false`. `OPENAI_API_KEY` chưa có trong môi trường local; không in credential. Chưa có lần gọi model thật hoặc phát notification mạng thật trong công việc này.

## Contract delta và giới hạn

Contract hiện có ở [03 mục 2.1](<../docs/tài liệu AI/03_DU_LIEU_VA_API.md>), UI ở [13](<../docs/tài liệu AI/13_THIET_KE_GIAO_DIEN.md>). CR-01 dùng DB notification đồng bộ trong TX-B; outbox/worker/push/mail vẫn TARGET. Error envelope CR-01 và snapshot receipt 503 được mô tả riêng, không giả đã đáp ứng contract M2 tương lai.

AI dùng lại prompt/provider của chat cũ; chưa có published-guide catalog, eligibility đầy đủ, circuit breaker, cost reservation hoặc bộ eval 100 ca. Lọc mẫu tuyên bố gửi phiếu giả không phải chứng minh mọi nội dung AI an toàn. Chưa nghiệm thu MySQL concurrency, screen reader, phiên đăng nhập/CSRF qua backend thật trên trình duyệt, deployment/staging/production hoặc Flutter. SQLite và browser fake không thay những bằng chứng này.

Bước vận hành còn lại: cấu hình provider trong môi trường đích, kiểm tra hội thoại thật theo ngân sách được giao, UAT hai luồng với tài khoản thử và triển khai các gate còn mở trong todo.md. Không đánh dấu RELEASED từ các kết quả local này.
