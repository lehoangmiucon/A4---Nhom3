# 14 — Từ điển dữ liệu và provenance

> File này giải thích ý nghĩa dữ liệu, không thay migration CURRENT_CODE hoặc contract tại 03.
> ERD: [so-do/04_ERD.md](so-do/04_ERD.md). Mọi bảng/cột TARGET cần migration và test khi triển khai.

## 1. Thuật ngữ

| Thuật ngữ | Ý nghĩa |
| --- | --- |
| Session | Một quá trình hỗ trợ cho actor, phòng và thiết bị/context đã xác định |
| Message | Nội dung đã được server ghi; client không tự cấp role assistant/system |
| Command | Một ý định/lượt xử lý có trạng thái và quyền tra cứu |
| Attempt | Một bước cụ thể đã phát, có snapshot và kết quả |
| Guide | Định danh cẩm nang nghiệp vụ |
| Guide version | Nội dung và điều kiện bất biến của một bản; published mới được dùng |
| Report | Lượt báo riêng của một session/người báo, kể cả cùng ticket |
| Handoff | Hồ sơ bàn giao và tiếp nhận của report, không phải toàn bộ ticket |
| Ticket | Công việc kỹ thuật hiện có; có thể nhận nhiều report |
| Follow-up | Thông tin bổ sung/yêu cầu xem xét xử lý lại, không tự đổi ticket status |
| Recurrence | Quan hệ tái diễn được xác minh; không đồng nghĩa cùng nguyên nhân |
| Receipt | Command bàn giao đã được TX-A lưu nhưng final report có thể chưa commit |
| Outbox | Ý định gửi sự kiện bền vững cùng dữ liệu nghiệp vụ |
| Delivery | Kết quả gửi theo event, người nhận và kênh |
| Quota reservation | Ngân sách/lượt được giữ trước gọi upstream, chưa phải chi phí thực |
| Unknown | Chưa biết; không được đổi thành false/đã xác nhận một cách ngầm định |

## 2. Session/message — CURRENT_CODE

| Trường | Kiểu/nullable | Ý nghĩa/ràng buộc |
| --- | --- | --- |
| support_sessions.id | bigint | ID phiên, không phải UUID command |
| user_id | bigint, không null | Chủ phiên; FK restrictOnDelete |
| room_id | bigint nullable | M1a bắt buộc lúc tạo; chat CR-01 được chưa xác định. CR-12 yêu cầu phòng trước hướng dẫn; null khi room bị xóa, không xóa session |
| equipment_id | bigint nullable | Thiết bị trong room nếu xác định; null khi bị xóa |
| category_id | bigint nullable | Danh mục sự cố được gán hoặc chọn từ hội thoại (CR-01); nullOnDelete |
| report_requested | boolean | Đánh dấu người dùng đã có yêu cầu gửi báo cáo (CR-01) |
| room_label/room_code | string, không null | Snapshot lúc tạo |
| building_label/equipment_label | string nullable | Snapshot; không suy từ model AI |
| status | string | collecting/awaiting_result/handoff_pending/handed_off/resolved/cancelled |
| deleted_at | timestamp nullable | CR-11: xóa mềm phiên handed_off có ticket closed bởi chủ phiên. Không đổi status, không xóa messages/report/command/ticket; mặc định loại khỏi query/route binding |
| ticket_status/is_completed/can_delete | DTO, không phải cột | CR-11: trạng thái ticket hiện tại và cờ hoàn tất/xóa được tính từ report/ticket closed + phiên handed_off; không lưu boolean có thể lệch khi ticket mở lại |
| version | unsigned integer | Tăng dần theo lượt thao tác; M1a tạo bằng 1 |
| last_activity_at/created_at/updated_at | timestamp | Lưu theo quy ước server, API ISO 8601 |
| support_messages.session_id | bigint | Parent phiên; FK cascade nếu phiên bị xóa theo quy trình được phép |
| role | string | user/assistant; không nhận role từ client |
| content_text | text | Nội dung đã lưu, không phải HTML để thực thi |
| client_message_id | UUID nullable | Chuẩn bị cho retry tin, unique(session_id, client_message_id) |
| handoff_brief | JSON nullable | Tóm tắt AI có nhãn, reported_checks và suggestions kèm fingerprint nguồn (CR-02) |

Không thêm physical column initial_message_id vì resource hiện tính từ quan hệ initialMessage. M1a vẫn không cho tạo phiên thiếu phòng; POST support-conversations có thể thu thập phòng sau đó (CR-01/12).

## 3. Idempotency và Command — CURRENT_CODE và delta

M1a dùng `support_idempotency_keys`: actor_id, operation, key (SHA-256 64), request_hash, response_data JSON, response_status và timestamps. Unique actor/operation/key. Lưu kết quả tạo phiên thành công trong transaction ngắn.

CR-01 bổ sung `support_commands` (id UUID, session_id, actor_id, key_hash 64, request_hash 64, operation, status, version, response_data, response_status; unique actor_id/key_hash) để lưu receipt intent bàn giao và lượt xử lý, cho phép replay và giữ pending receipt khi TX-B lỗi; và `support_reports` (id, session_id unique, ticket_id, transcript JSON).

CR-12: operation `clarify` là lượt hỏi thông tin phòng/công việc còn thiếu do backend tạo, không gọi provider và không đếm trong quota operation=chat. Không cần migration vì operation là string. incidentFacts bỏ lời chào/yêu cầu hỗ trợ chung/câu chỉ nêu ngữ cảnh khi xét đủ mô tả; messages/transcript và phần trích lời gốc vẫn giữ nguyên nội dung người dùng.

TARGET bàn giao đầy đủ (M2-08/M2-02/M3) mở rộng `support_commands` thêm purpose, deadline_at, lease, snapshot_version và unique(session_id, input_message_id, purpose) cho AI. Không xây bảng command cạnh tranh.

Pending command không được xóa khi final transaction thất bại. Chi tiết update schema ở task M2-08/02/03; M3 tái sử dụng command store.

### Projection quan sát — CURRENT_CODE CR-13

CR-13 CURRENT_CODE: diagnosis là DTO tính từ messages và response_data của support_commands, không thêm bảng/cột. Receipt lưu mã next_question cùng message ID để gắn câu “có/không” đúng bước ngay cả khi sửa lời câu hỏi. Đổi phòng/danh mục đã biết lấy ranh giới message từ receipt gần nhất có ngữ cảnh khác; thay loại thiết bị/nhánh triệu chứng trong lời báo bắt đầu lại phạm vi quan sát. evidence chỉ là trích lời người dùng tối đa 300 ký tự; unknown không được trình bày như kiểm tra đã hoàn tất. Raw messages/report transcript giữ nguyên.

## 4. Dữ liệu TARGET

| Thực thể | Trường chính | Nguồn/ràng buộc |
| --- | --- | --- |
| support_commands (mở rộng) | purpose, input_message_id nullable, snapshot_version, deadline_at, lease, error | Mở rộng bảng support_commands hiện có cho lượt AI M3 và receipt M2 |
| support_step_attempts | id, session_id, guide_version_id, step_code, content_snapshot, presented_at, result, responded_at | Nguồn bước đã phát; kết quả resolved/not_fixed/cannot_perform |
| troubleshooting_guides | id, code, device_scope, owner | Không tự gắn model không có metadata |
| troubleshooting_guide_versions | guide_id, version, state, questions/steps/preconditions, source, reviewed_by/at, published_at, withdrawn_reason | Unique guide/version; bất biến published |
| support_reports (mở rộng) | reason, source_snapshot, ai_summary nullable, summary_version | Mở rộng support_reports hiện có khi có cẩm nang và phân loại chi tiết |
| support_handoffs | id, report_id unique, version, acknowledged_by/at, response deadlines, escalation level | Mỗi report có acknowledgement riêng |
| support_follow_ups | id, report_id, author_id, client_message_id, type, content, created_at | Unique report/client_message_id; type bổ sung/đề nghị xem xét |
| support_outbox | event_id UUID unique, aggregate_type/id, payload tối thiểu, status, attempts, next_attempt_at | Commit cùng report/ticket; không raw chat mặc định |
| support_deliveries | event_id, recipient_id, channel, status, provider_reference nullable | Unique event/recipient/channel |
| support_attachments | id, session_id, uploaded_by, private_path, actual_mime, size, created_at | Policy parent; không public URL |
| Usage/reservation | command_id, model/provider, token limits/actuals, estimated/settled/unknown cost | Shared atomic store, task quota xác định physical schema |

Các trường JSON không thay thế FK, unique và state guard. Không dựng full relational schema chỉ vì bảng trên liệt kê nếu task chưa cần.

## 5. Taxonomy triệu chứng và metadata

symptom_code là mã nghiệp vụ ổn định của hiện tượng đã xác nhận, không phải nguyên nhân chắc chắn. Danh mục draft đề xuất ở 15; chuyên môn duyệt trước auto-match/published guide.

Thiết bị cần metadata khi luật đòi hỏi: loại, model, vị trí, khả năng tiếp cận, kết nối được hỗ trợ. Metadata chưa biết phải là unknown/null phù hợp schema, không mặc định là an toàn. Code hiện chỉ có room_id/category_id/name/status; các trường thêm còn TARGET.

## 6. Provenance và phân loại dữ liệu

| Dữ liệu | Nguồn có thẩm quyền | Cách sử dụng |
| --- | --- | --- |
| Actor/role/quyền | Hệ thống auth/policy | Không lấy từ model hoặc payload |
| Phòng/thiết bị | DB + xác nhận người dùng | Lưu snapshot và ID; QR không chứng minh có mặt |
| Triệu chứng | Người báo, có message/answer tham chiếu | Facts báo cáo, không tự thành chẩn đoán |
| Bước đã thử | Attempt server + lệnh kết quả | Không tin câu assistant tự nhận đã thử |
| Summary AI | Model/version + nguồn message/attempt | Có nhãn, không thay facts |
| Nguyên nhân thực tế | KTV sau kiểm tra | Ghi là kết luận đã xác minh, không sửa ngược symptom gốc |
| Tiếp nhận/phân công | Command đã commit có actor/time | Không suy từ notification read |
| Model usage | Provider response và reservation ledger | Unknown không coi là 0 |

Chat, ảnh và liên hệ là dữ liệu cần bảo vệ; log kỹ thuật mặc định chỉ metadata. Retention, ẩn danh và backup theo 07/Q-05; không dùng dữ liệu thực để điền ví dụ tài liệu.

## 7. Các ràng buộc không được bỏ

Một session có tối đa một report/handoff chính; một report thuộc một ticket, nhiều report có thể cùng ticket. Một command kết quả cũ không áp dụng vào version mới. Một bước có kết quả chốt không bị lệnh retry sửa lại. Event delivery không nhân đôi notification DB. Không có auto-delete theo room/equipment làm mất history.
