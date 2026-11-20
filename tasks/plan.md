# Kế hoạch nhận việc AI Helpdesk

Cập nhật 20/11/2026, đến CR-13; AI-SDLC-1.2 đồng bộ tài liệu, không phải release ứng dụng. [README AI](<../docs/tài liệu AI/README.md>) sở hữu tổng quan; [todo.md](todo.md) là nguồn task/trạng thái duy nhất. M1a/M1b và CR-01–13 đã có các lát cắt DONE_CODE, không đồng nghĩa hoàn tất M2–M8.

## Người mới bắt đầu ở đây

1. Đọc README AI → contract 03 mục 2/2.1 (CURRENT_CODE) → BR/quyền 11/12 theo task.
2. Làm theo [hướng dẫn local/API](../docs/local-ai.md), kiểm tra migration/config trên máy mình. Không sao chép .env, tài khoản hoặc snapshot DB của người khác.
3. Đọc [evidence gần nhất CR-13](verification-cr13.md): checkpoint PHP 276/2892 assertions, JS 62, Chrome SQLite :memory:/model fake. Chạy kiểm chứng cần thiết trên checkout của bạn và ghi giới hạn.
4. Chọn một task trong todo theo phụ thuộc/đề nghị thứ tự dưới đây; trước đổi behavior đọc code bằng CodeGraph nếu .codegraph đã có. Không khởi tạo index thay người dùng.
5. TDD cho logic, fake API/notification + SQLite :memory:. Không dùng việc thiếu key làm lý do tạo provider mới hoặc seed DB đang dùng.

## Bản đồ file sở hữu CURRENT_CODE

| Phần cần sửa | File chính | Kiểm chứng gần nhất/guard |
| --- | --- | --- |
| M1a create/list/detail/messages và xóa phiên | SupportSessionController, StoreSupportSessionRequest, SessionCreationService, SupportSessionPolicy, SupportSession/SupportMessage | SupportSessionTest, SupportSessionDeletionTest |
| POST hội thoại, tin, escalation và GET command | SupportConversationController, SupportConversationService, routes/api.php, SupportApiResponse | SupportConversationTest, SupportIntakeTest |
| Intake, hiểu ý định, nhận phòng/danh mục | ConversationContext | SupportIntakeTest, SupportDiagnosticFlowTest; không để model tự quyết quyền ghi |
| Nhánh quan sát, đã hỏi/kết quả/chưa biết | SupportDiagnosticFlow | SupportDiagnosticFlowTest; receipt giữ mã câu hỏi, chưa định danh hai thiết bị cùng loại |
| OpenAI/Ollama, JSON reply/draft/brief | app/Services/HelpdeskAiService.php, config/services.php, .env.example | HelpdeskChatTest, LocalHelpdeskAiTest, SupportHandoffBriefTest |
| Tổng hợp/gợi ý IT và tạo phiếu | ConversationBrief, ConversationFallback, ConversationReportService, TicketCreationService | SupportHandoffBriefTest, SupportConversationTest; không gọi provider trong TX-B |
| Portal hiện tại | customer/portal.blade.php, partial support-conversation, support-conversation.js/.css, portal-qr.js | SupportSessionUiTest, tests/js/support-conversation.test.mjs, tests/browser/*smoke.mjs |
| Legacy reply/draft | HelpdeskChatController, portal-chat.js, support-session.js/partial | Không coi là đường portal chính hoặc đồng nhất schema với guide TARGET |
| API docs phát sinh | app/Support/OpenApiDocument.php | SwaggerDocumentationTest, SupportSessionTest; không gọi /api/openapi.json vì không có route |

Tên service ở app/Services/Support/ trừ khi ghi khác. Khi thêm capability mới mở rộng module sở hữu, không tạo receipt/provider/handoff store song song chỉ vì tên mẫu TARGET khác.

## Thứ tự đề nghị để tiếp tục

Đây là trình tự, không phải checklist task thứ hai; acceptance và trạng thái nằm trong todo.

1. Đóng gói source/migration/test/docs thành commit review được; kiểm tra secret/artefact, giữ .codegraph/, .env, backup private và dữ liệu cá nhân khỏi commit ngoài phạm vi. Không tự push nếu chỉ được giao rà tài liệu.
2. Kiểm chứng môi trường đích theo M8-03/04: migration/config/auth/CSRF/receipt với DB staging và provider thật khi Q-01/02/Q-06 đủ điều kiện. Ghi model/prompt/version; chưa gọi trả phí khi chưa có phạm vi ngân sách. Test CR-13 đang dùng fake không thay eval.
3. Tiếp tục M2-08/M2-09 → M2-02/03/04: review và mở rộng command/report đã có, thêm outbox/recovery; không triển khai lại create ticket hoặc GET command từ đầu. Các AC bền vững đầy đủ vẫn chưa đạt dù lát cắt CR-01 đã chạy.
4. M2-05/06 và M5-01: thiết bị/symptom chính xác, report của người báo sau, progress/acknowledgement riêng; thay chống trùng phòng/danh mục phải có CR và test tương thích.
5. M3/M4/M5-04: guide catalog/published/eligibility/attempt, deadline/circuit, token/cost reservation và lifecycle đầy đủ; projection quan sát CR-13 không thay các module này.
6. M7 eval/người duyệt, rồi M6 Flutter/push theo contract ổn định; M8 tải/restore/runbook/UAT trước RELEASED.

## Điểm dễ hiểu nhầm cần giữ khi sửa

- M1a tạo phiên bắt buộc room_id; POST support-conversations được bắt đầu chưa biết phòng. CR-12 chỉ cho hướng dẫn khi đủ phòng/danh mục/mô tả, ngoại lệ safety notice ưu tiên trước intake.
- Session resolved là người dùng xác nhận; ticket resolved còn chờ quản lý; closed mới đủ điều kiện xóa mềm phiên handed_off. Xóa phiên không xóa phiếu/hồ sơ hoặc giả hoàn thành.
- Hiện provider gọi đồng bộ ngoài transaction, không có worker/circuit/outbox hoặc ledger tiền/token. Quota command chat không là hard cap tài chính.
- Mẫu câu hỏi CR-13 do backend chọn; đổi model API/local không tự thay cây hỏi/quyền. Ngoài các nhánh đã hỗ trợ còn model/fallback, chưa có guide được chuyên môn duyệt.
- Snapshot replay giữ nguyên; GET là state hiện tại, phiên đã xóa không đọc/replay lại. Không xóa receipt/key để né lỗi retry.
- DATA-CLEAN-01 đã dọn dữ liệu thử theo yêu cầu, không có script reset DB để thành viên chạy lại. ID/ảnh bằng chứng lịch sử không phải dữ liệu còn tồn tại trên máy mới.

## Phạm vi kiểm chứng và lịch sử

M1a từng có BASE-001 (2 query pending count), đã sửa ở M1b; không giữ nó thành blocker hiện tại. Evidence từng CR giữ nguyên số liệu của checkpoint tương ứng. Migration đã ghi local batch 15/16/17; từng máy phải migrate:status, không coi là deploy production.

Không cấp admin/KTV quyền GET raw session. Nội dung bàn giao hiện nằm trong ticket.description và report.transcript lưu server; quyền ticket cũ kiểm soát phần IT đọc được. App Android, report acknowledgement, guide được duyệt, policy retention và vận hành còn TARGET. Hợp đồng tương lai dùng client_message_id/turns/attempt không gửi vào endpoint CR-01 hiện hành.
