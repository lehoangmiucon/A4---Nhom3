# Chạy AI Helpdesk local hoặc qua API

LOCAL-AI-01, ngày 20/11/2026. Đây là cấu hình thử nghiệm local theo yêu cầu mới của người dùng; không tự đổi provider production. Laravel giữ nguyên API và nghiệp vụ phiếu, chỉ thay nguồn trả lời bằng Ollama.

Máy đã kiểm tra: RAM 15,7 GB, NVIDIA RTX 4060 Laptop 8 GB. Ollama 0.33.2 đã có model `qwen2.5:7b` Q4_K_M, digest `845dbda0ea48`, khoảng 4,7 GB. Không tải thêm model. `ollama ps` xác nhận 100% GPU, context 4096. Model hỗ trợ tiếng Việt theo [trang model chính thức](https://ollama.com/library/qwen2.5:7b).

## Cấu hình Ollama đã kiểm chứng tại checkpoint LOCAL-AI-01

Máy tác giả đã dùng `.env` local (được Git bỏ qua) như sau; đây không phải cấu hình tự có sau clone:

```dotenv
HELPDESK_AI_PROVIDER=ollama
OLLAMA_URL=http://127.0.0.1:11434
OLLAMA_MODEL=qwen2.5:7b
```

Không cần OPENAI_API_KEY khi chọn Ollama. Các cấu hình/secret khác được giữ nguyên. `.env.example` vẫn mặc định OpenAI để các môi trường khác không tự chuyển provider. Muốn quay về cấu hình trước: đặt `HELPDESK_AI_PROVIDER=openai`, cấu hình key trên backend và chạy `php artisan config:clear`.

Adapter gọi POST `/api/chat`, tắt stream, truyền JSON schema reply/draft và kiểm tra kết quả; cơ chế này theo [API chat](https://docs.ollama.com/api/chat) và [Structured Outputs](https://docs.ollama.com/capabilities/structured-outputs). Timeout 20 giây, connect timeout 2 giây, context 4096, tối đa 600 output tokens, temperature 0, giữ model trong bộ nhớ 10 phút sau lượt cuối. Không tự retry provider hoặc chuyển sang cloud. UI có thể gửi báo cáo ngay cả khi model lỗi.

## Mở để thử

1. Mở ứng dụng Ollama trên Windows. Kiểm tra `ollama list` thấy `qwen2.5:7b`.
2. Tại thư mục dự án, chạy:

```powershell
php artisan config:clear
php artisan serve --host=127.0.0.1 --port=8000
```

3. Vào [portal local](http://127.0.0.1:8000/customer/portal), đăng nhập student/teacher đang hoạt động, đã xác thực email. Chọn **Nhờ AI hỗ trợ**.
4. Chọn/nêu phòng có thật và danh mục, nhập vấn đề cụ thể. CR-12 hỏi phần thiếu trước hướng dẫn. CR-13 hỏi quan sát từng bước cho các nhánh đã hỗ trợ. Khi muốn bàn giao, nói “hãy báo cáo lên KTV cho mình” hoặc bấm Gửi báo cáo giúp tôi; không cần thử hết câu hỏi.

Thao tác gửi báo cáo trên portal tạo phiếu và DB notification thật trong database local đang dùng. Smoke tự động của công việc này dùng SQLite bộ nhớ riêng nên không để lại phiếu thử trong dữ liệu local.

Sau thời gian không dùng, model có thể cần nạp lại. Có thể nạp trước bằng lệnh không tạo hội thoại:

```powershell
Invoke-RestMethod -Method Post -Uri http://127.0.0.1:11434/api/generate -ContentType application/json -Body '{"model":"qwen2.5:7b","stream":false,"keep_alive":"10m","options":{"num_ctx":4096}}' -TimeoutSec 60
```

Nếu cổng 11434 chưa hoạt động, mở Ollama hoặc chạy `ollama serve` khi chưa có server. API mặc định của Ollama trên Windows được mô tả tại [hướng dẫn chính thức](https://docs.ollama.com/windows). Giữ URL loopback trên máy thử.

## Bản bàn giao CR-02

Phiếu mới từ chat có tóm tắt AI, trích lời báo về kiểm tra/kết quả, gợi ý dành cho KTV và lời báo gốc. Brief chuẩn bị cùng lượt chat; không thêm lần gọi model lúc gửi báo cáo. Brief thiếu/sai/stale dùng mẫu hệ thống để vẫn gửi được. Phiếu đã tạo trước CR-02 giữ nguyên; tạo hội thoại và phiếu mới để kiểm tra tính năng. [Evidence CR-02](../tasks/verification-cr02.md).

## Phiên chat dài hơn

CR-07: phiên web mặc định hỗ trợ 40 lượt chat, tối đa 12 lượt/phút/tài khoản và 200 tin cả hai phía. Không còn trần cứng 5 lượt. Có thể cấu hình:

```dotenv
HELPDESK_AI_MAX_TURNS_PER_SESSION=40
HELPDESK_AI_MAX_TURNS_PER_MINUTE=12
HELPDESK_AI_MAX_MESSAGES_PER_SESSION=200
```

Không cần thêm vào .env nếu dùng mặc định; khi thay cấu hình ở môi trường có config cache cần làm mới cache theo quy trình triển khai. Giới hạn lượt tính theo command chat đã lưu, không phải thời gian mở tab. Hết giới hạn phút có thể chờ khoảng một phút rồi chat tiếp; gửi báo cáo vẫn khả dụng. Lịch sử gửi model giữ hai lời báo đầu và tối đa chín tin gần nhất, giảm tin ở giữa nếu vượt 6000 ký tự; raw history vẫn lưu đầy đủ. Giới hạn ký tự không phải bảo đảm ngân sách token của mọi model. [Evidence](../tasks/verification-cr07.md).

## Máy thành viên mới: chuẩn bị trước khi chạy

PHP >=8.2, Laravel 12 theo composer.json; cài dependency bằng `composer install` khi chưa có vendor (Composer có thể không ở PATH trên một số máy Windows). Tạo `.env` từ `.env.example` chỉ khi chưa có; cấu hình DB/mail theo môi trường của bạn, chạy `php artisan key:generate` chỉ cho cài mới chưa có APP_KEY. Không in key/credential ra chat hoặc tài liệu.

Các migration support cần có trong checkout:

| Migration | Nội dung | Evidence local máy tác giả |
| --- | --- | --- |
| 2026_11_19_000001_create_support_session_tables | session/message/idempotency key | batch 15 |
| 2026_11_19_120000_add_support_conversation_workflow | command/report/context | batch 15 |
| 2026_11_20_090000_add_handoff_brief_to_support_messages | JSON brief | batch 16 |
| 2026_11_20_150000_add_soft_deletes_to_support_sessions | deleted_at | batch 17 |

```powershell
php artisan migrate:status
```

Chỉ sau khi kiểm tra đúng APP_ENV/DB và backup của môi trường đích, chạy `php artisan migrate` để áp dụng migration pending đã review; kiểm tra lại status. Không `migrate:fresh`, không seed lại tài khoản/phiếu hoặc chép DB test để sửa lỗi thiếu bảng. Batch trên máy bạn có thể khác. Đăng ký/xác thực email hoặc dùng tài khoản được cấp hợp lệ; chỉ student/teacher active/verified dùng endpoint hỗ trợ. Admin/staff không thay chủ phiên qua API này.

## Chuyển sang OpenAI API

Adapter đã có, dùng Responses API và JSON schema; chưa chứng minh mọi model hoặc nhà cung cấp OpenAI-compatible nhận cùng payload. Chọn model được tài khoản cấp quyền, kiểm chứng structured output và timeout/token theo [contract 03](<tài liệu AI/03_DU_LIEU_VA_API.md>) trước đổi model. `gpt-4.1-mini` là default code, không phải khuyến nghị model production mới nhất.

```dotenv
HELPDESK_AI_PROVIDER=openai
OPENAI_API_KEY=<đặt_key_riêng_trên_server>
OPENAI_MODEL=gpt-4.1-mini
```

```powershell
php artisan config:clear
```

Không cần sửa giao diện hoặc migration chỉ để đổi provider. Sau đổi env, khởi động lại process chạy lâu nếu có; môi trường deploy dùng config cache theo runbook riêng. OpenAI: connect 5s, timeout 20s, max_output_tokens=1000, store=false. Ollama: connect 2s/20s, num_predict=600. Client abort 35s; retry giữ key, không tự đổi provider. Key chỉ phía server; không dán vào chat/Git/frontend. API cần kết nối/quyền/hạn mức tài khoản; chi phí dùng API theo tài khoản provider, không coi đăng nhập ChatGPT là API key.

Đổi provider có thể đổi diễn đạt/brief/gợi ý/độ trễ; schema reply/draft/handoff_brief và luật tạo phiếu/phân nhánh/quyền vẫn do backend kiểm tra. Đổi trường JSON phải sửa responseFormat/validator/test và tài liệu cùng nhau; schema ai-decision trong bộ thiết kế là TARGET, không phải schema provider hiện tại. Không chỉ đổi OPENAI_MODEL rồi tuyên bố chất lượng đạt. Chưa chạy OpenAI thật hoặc đánh giá model thật sau CR-13; đợt smoke/eval trả phí phải có scope/ngân sách được giao. Gemini/Claude/native provider khác chưa có adapter.

## Kiểm chứng không tạo dữ liệu test trên DB local

```powershell
php artisan test --compact
node --test tests/js/*.test.mjs tests/JavaScript/*.test.cjs
php artisan test --compact --filter=SupportDiagnosticFlowTest
node tests/browser/support-diagnostic-smoke.mjs
```

PHPUnit ép SQLite :memory: theo phpunit.xml/TestCase, HTTP/notification fake trong test. Script Chrome diagnostic tạo service responses bằng fixture PHP trong SQLite :memory:, fake model/fetch, không gọi provider thật hoặc ghi DB local; cần Chrome Windows (hoặc CHROME_PATH). Không bật biến smoke model thật khi chỉ chạy suite mặc định. Windows sandbox có thể báo không đọc vendor/autoload.php dù file tồn tại: kiểm tra quyền/môi trường thực thi, không sửa expectation hoặc migrate DB đang dùng để chữa lỗi test.

Checkpoint CR-13: 276 PHP/2892 assertions, 62 JS; bằng chứng chi tiết và giới hạn ở [verification-cr13](../tasks/verification-cr13.md). Ảnh bố cục 390px không thay kiểm thử thiết bị mobile thật, fixture không thay staging login/CSRF/MySQL concurrency. Muốn test bằng portal thật phải biết thao tác gửi report sẽ ghi DB/notification; không gọi đó là test không tác dụng phụ.

## Bằng chứng và giới hạn

Xem [verification-local-ai.md](../tasks/verification-local-ai.md). Đã có câu trả lời model thật và luồng API chat → report → replay với SQLite bộ nhớ; chưa UAT đăng nhập đầy đủ qua trình duyệt hoặc nghiệm thu chất lượng/an toàn của model. Kho published guide và backend eligibility vẫn TARGET. Đường tạo phiếu tiếp tục do backend quyết định từ facts đã lưu và yêu cầu rõ ràng của người dùng.
