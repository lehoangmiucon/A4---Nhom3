# Kiểm chứng LOCAL-AI-01

Ngày: 20/11/2026. Trạng thái: **DONE_CODE + model offline đã chạy local**, chưa RELEASED. Task ở [todo.md](todo.md); cách chạy ở [docs/local-ai.md](../docs/local-ai.md).

## Quyết định và phạm vi

Người dùng chọn rõ “Muốn chạy model offline trên máy”, thay giả định trước đó về dùng khóa OpenAI cho test local. Giữ Laravel, HTTP API và backend kiểm soát phiếu. Bổ sung provider Ollama tùy chọn; mặc định repository vẫn OpenAI, `.env` local chọn Ollama. Không fine-tune, không tải thêm model, không đổi production, không gửi dữ liệu hội thoại lên cloud trong các lần thử.

Máy có RAM 15,7 GB, RTX 4060 Laptop 8188 MiB; Ollama 0.33.2 và qwen2.5:7b Q4_K_M đã có sẵn (4,7 GB, digest 845dbda0ea48). Ollama đã khởi động. `ollama ps` xác nhận 100% GPU, context 4096. Kiểm tra CIM/Ollama ban đầu bị sandbox chặn truy cập ứng dụng; chạy kiểm tra với quyền được duyệt đã thành công.

## RED → GREEN

Viết LocalHelpdeskAiTest trước: **3 failed, 1 passed**. Nguyên nhân đúng: service chỉ gọi OpenAI, yêu cầu khóa và gửi request cloud ngay cả khi cấu hình local/unknown provider.

Sau triển khai: **4 passed / 11 assertions**. Bao phủ reply local không cần key, request JSON schema/system history, không header Authorization, lỗi kết nối không chuyển cloud, output chưa xong/JSON sai bị từ chối, provider không hợp lệ không gửi request. Suite mặc định force provider OpenAI để không phụ thuộc `.env` local; riêng test local chọn cấu hình qua config(). HTTP test dùng fake, DB SQLite :memory:.

## Kết quả thực

| Lệnh | Kết quả |
| --- | --- |
| `php artisan test --compact --filter=LocalHelpdeskAiTest` | 4 passed / 11 assertions |
| `php artisan test --compact` | **162 passed / 2130 assertions**, 9,53 giây |
| `vendor/bin/pint app/Services/HelpdeskAiService.php tests/Feature/LocalHelpdeskAiTest.php config/services.php` | Đạt, format file thuộc phạm vi |
| `git diff --check` | Đạt, cảnh báo CRLF của Git |
| POST loopback `/api/generate` với prompt rỗng | Nạp model thành công |
| `php storage/framework/testing/cr01/local-ai-smoke.php` | Service thật trả tiếng Việt, 2,25 giây; chỉ đọc danh mục local |
| `php artisan test --compact storage/framework/testing/cr01/OfflineConversationSmokeTest.php` | **1 passed / 10 assertions**, 2,42 giây, model thật + SQLite :memory: |
| `php artisan serve --host=127.0.0.1 --port=8000` | Server local đã chạy; GET /login trả 200 |

Smoke API thật được chạy riêng, không nằm trong suite mặc định: chỉ cho phép HTTP tới `127.0.0.1:11434/api/chat`. Tạo phiên qua controller, nhận câu trả lời model, yêu cầu báo cáo và replay cùng key. Chỉ một request tới model, một ticket/report trong SQLite bộ nhớ; không tác động dữ liệu đang dùng, không notification mạng. [Kết quả JSON](evidence/local-ai/offline-conversation.json) ghi câu trả lời thực, thời gian toàn luồng 1,71 giây và trạng thái handed_off.

Lần chạy đầu smoke đã qua các hành vi nhưng FAIL ở assertion đếm HTTP do facade chưa bật recording cho request thật. Thêm `Http::fake([])` để bật recorder, giữ allowlist loopback; chạy lại đạt. Không đổi logic ứng dụng hoặc bỏ assertion để làm xanh.

## Review và giới hạn

Contract reply/draft và prompt hiện có được dùng chung, parse/validate draft giữ nguyên. Provider chọn từ server config; URL không nhận từ input người dùng. Timeout 20 giây phù hợp receipt xử lý 30 giây và UI timeout 35 giây hiện có. Chế độ local lỗi không gọi OpenAI; đường gửi báo cáo vẫn không cần model. `.env` vẫn bị Git ignore, không đưa khóa hoặc nội dung `.env` vào evidence.

Không thay UI/JS ở task này nên không lặp lại smoke responsive; bằng chứng trước ở verification-cr01.md. Chưa test đăng nhập/CSRF qua browser nối backend thật, chưa đánh giá độc lập chất lượng/an toàn tiếng Việt hoặc thử mất điện, tải đồng thời và MySQL concurrency. Model nhỏ có thể trả lời chưa sát nghiệp vụ; chưa coi đạt bộ safety/guide/eval TARGET. Không migration thêm hoặc phát hành production.
