# DOC-SYNC-01 — đồng bộ tài liệu AI trước push

20/11/2026. Baseline tài liệu AI-SDLC-1.2, đối chiếu mã đến CR-13. Đây là review/bàn giao tài liệu, không phải phiên bản release ứng dụng, không chứng minh staging/model thật đạt.

## Phạm vi và nguồn đối chiếu

Rà soát README gốc, docs/local-ai.md, docs/mobile-api.md, bộ tài liệu sở hữu 01–16, index sơ đồ/SVG, tasks/plan.md/todo.md và evidence các checkpoint, tập trung các phần hiện trạng, contract, bàn giao và truy vết. Đối chiếu routes/api.php, controller/policy/DTO, SupportConversationService, SupportDiagnosticFlow, HelpdeskAiService, TicketCreationService, config/services.php/.env.example, migration và TestCase/phpunit. CodeGraph dùng trước khi đọc code; phần code không được tool trả đủ đọc trực tiếp theo quy tắc repo.

Không thay hành vi chat/phiếu, provider/config runtime, DB hoặc dữ liệu. File PHP duy nhất sửa trong lượt này là **chuỗi mô tả tài liệu** của OpenApiDocument, không đổi route/schema request/validation/quyền hoặc logic nghiệp vụ. Không đọc/in giá trị .env, gọi model, migrate, gửi thông báo, commit hoặc push.

## Các sai lệch đã sửa

| Nhóm | Sai lệch trước review | Kết quả bàn giao |
| --- | --- | --- |
| Trang vào/plan | Vẫn ghi M1a/CR-01, popup điền nháp, chưa có UI, số test 158/168/198 là hiện tại | Tổng quan CR-01–13, checkpoint gần nhất 276 PHP/2892 assertions + 62 JS; lịch sử giữ evidence riêng |
| Kiến trúc | Worker/outbox/usage/attempt và enum command TARGET dễ bị hiểu là đang chạy | Tách luồng HTTP đồng bộ/TX-A/TX-B/processing-completed thực, bản đồ file và phần còn thiếu |
| Contract | 5 lượt/5 phút/100 tin; response thiếu diagnosis; client tạo command_id; description chỉ có lời người báo | Mặc định 40/12/200, server UUID, các trường/10 method-path hiện có, các phần brief/projection/raw source và khác biệt replay/GET |
| Chống trùng | Mục cũ nói chat không chặn trùng, trái CR-03 | Chặn phòng/danh mục active, report=null khi trùng; chưa gộp thiết bị/symptom/progress người báo sau |
| Provider/schema | Có chỗ nói chưa gọi model thật, schema ai-decision được gọi như schema runtime | Ghi Ollama thật là checkpoint cũ; chưa eval CR-13/model API thật. Provider dùng reply/draft/handoff_brief; action/guide schema là TARGET |
| Dữ liệu/quyền | room_id chỉ null sau xóa, chưa có soft delete/command quyền, nhầm resolved/closed | Phân biệt M1a/chat intake, xóa mềm CR-11, quyền chủ phiên/command và IT đọc qua ticket, không mở raw chat theo role |
| Vận hành | Không có bằng chứng migration local; hạn mức đề xuất dễ thành runtime, thiếu chi phí hard cap | Bốn migration support batch local 15/16/17, cách kiểm tra từng máy; timeout/quota thực, chưa có reservation/circuit/outbox/recovery |
| RTM/UC/BR | FR-004/NFR quota vẫn TARGET hoàn toàn, thiếu FR-019 trong ma trận, BASE-001 vẫn như lỗi mở | Ghi coverage từng lát cắt và TC-033–035, BR-43–45; BASE-001 đã sửa, gate còn thiếu giữ nguyên |
| Quyết định | Thiếu CR-04–13, quyết định M1a “chưa UI” như hiện hành | D-14–17 và nhật ký 1.2; giữ quyết định/lý do cũ làm lịch sử |
| Hướng dẫn nhận việc | Thiếu cách clone/setup/provider API, file sở hữu, thứ tự tiếp tục | plan mới và local-ai.md có migration/provider/test commands, giới hạn và phụ thuộc roadmap |
| Liên kết/sơ đồ | 4 link hướng dẫn SEC không có; SVG 19/11 dễ hiểu là hiện trạng mới | Liên kết tới báo cáo security thật kèm nhãn guide riêng còn thiếu; diagram/SVG baseline gắn nhãn TARGET/snapshot, task DOC-SYNC-02 và DOC-SEC-01 ở todo |

Không sửa evidence M1a/CR cũ thành số mới hoặc coi snapshot ID trong E2E cũ còn tồn tại sau DATA-CLEAN-01. Những checklist guide/Flutter/outbox/retention/pilot chưa đạt vẫn để TARGET. Sơ đồ baseline giữ nguyên graph/source code block, chưa render lại; có chú thích delta và phần việc cập nhật hình riêng, không báo render hiện trạng đã đạt.

## Kiểm chứng trong lượt này

- `python tasks/check-ai-docs.py`: 58 tệp Markdown, 17 file handoff và 19 FR trong RTM; không thiếu file đích hoặc FR. [Bản ghi kiểm tra](evidence/doc-sync01/link-audit.json). Không kiểm external URL, anchor Markdown hoặc renderer Mermaid.
- `php -l app/Support/OpenApiDocument.php`: không có lỗi cú pháp.
- `php artisan test --compact --filter='SwaggerDocumentationTest|SupportSessionTest|SupportSessionDeletionTest'`: **37 passed / 887 assertions**, 2.16s, SQLite :memory:/fake API. Sandbox Windows báo không đọc vendor/autoload.php, cùng lệnh ngoài sandbox được auto-review cho phép và đạt. Không sửa test để chữa lỗi môi trường.
- `git diff --check`: đạt; cảnh báo CRLF/LF ở working tree có sẵn không là lỗi whitespace.
- Không chạy lại full suite/model/browser vì thay đổi chỉ tài liệu và description Swagger. Kết quả full PHP 276/2892, JS 62 và Chrome của CR-13 là checkpoint trước lượt này, dẫn evidence thay vì ghi là vừa chạy mới.

## Cách tiếp tục

Thành viên bắt đầu [README AI](<../docs/tài liệu AI/README.md>) → [local/API](../docs/local-ai.md) → [plan](plan.md) → chọn task ở [todo](todo.md) → contract/BR/quyền liên quan. todo là nguồn checklist duy nhất; plan chỉ diễn giải trình tự/file sở hữu. Jira có thể liên kết ID task/evidence, không tạo bản trạng thái cạnh tranh trong tài liệu.

Trước push vẫn cần chọn file/stage/review source và secret/ảnh/evidence/tài liệu cá nhân; review này không tự cho phép đưa .env/.codegraph/backup private lên Git hoặc tuyên bố toàn dự án sẵn sàng production. Không cam kết every checkout có kết quả giống máy tác giả; thành viên cần chạy lệnh kiểm chứng trên môi trường của mình.
