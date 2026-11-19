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
| room_id | bigint nullable | Bắt buộc lúc tạo; null khi room bị xóa, không xóa session |
| equipment_id | bigint nullable | Thiết bị trong room nếu xác định; null khi bị xóa |
| room_label/room_code | string, không null | Snapshot lúc tạo |
| building_label/equipment_label | string nullable | Snapshot; không suy từ model AI |
| status | string | collecting/awaiting_result/handoff_pending/handed_off/resolved/cancelled |
| version | unsigned integer | M1a tạo bằng 1; cơ chế cập nhật lệnh còn TARGET |
| last_activity_at/created_at/updated_at | timestamp | Lưu theo quy ước server, API ISO 8601 |
| support_messages.session_id | bigint | Parent phiên; FK cascade nếu phiên bị xóa theo quy trình được phép |
| role | string | M1a chỉ service tạo user; không nhận role từ client |
| content_text | text | Nội dung đã lưu, không phải HTML để thực thi |
| client_message_id | UUID nullable | Chuẩn bị cho retry tin, unique(session_id, client_message_id) |

Không thêm physical column initial_message_id vì resource hiện tính từ quan hệ initialMessage. Không coi room_id nullable sau xóa là quyền tạo phiên không có phòng.

## 3. Idempotency — CURRENT_CODE và delta

Hiện có support_idempotency_keys: actor_id, operation, key (SHA-256 64), request_hash, response_data JSON, response_status và timestamps. Unique actor/operation/key. M1a chỉ lưu kết quả tạo thành công trong transaction, không có pending state.

TARGET bàn giao cần receipt ở support_commands với operation, actor_id, session_id, intent_key_hash, request_hash, response_snapshot/status nullable và execution status. Unique actor/operation/intent_key_hash; report có unique(session_id). Không giả row M1a đủ xử lý pending hoặc sửa migration cũ để diễn đạt sai.

Pending command không được xóa khi final transaction thất bại. Chi tiết update schema ở task M2-08/02/03; M3 tái sử dụng command store, không xây bảng command cạnh tranh.

## 4. Dữ liệu TARGET

| Thực thể | Trường chính | Nguồn/ràng buộc |
| --- | --- | --- |
| support_commands | id UUID, actor_id, session_id, operation, purpose, input_message_id nullable, snapshot_version, status, deadline_at, lease, intent/key hash, request_hash, response/result/error | Một intent/lượt; unique tin/purpose khi có input; handoff receipt không cần input AI |
| support_step_attempts | id, session_id, guide_version_id, step_code, content_snapshot, presented_at, result, responded_at | Nguồn bước đã phát; kết quả resolved/not_fixed/cannot_perform |
| troubleshooting_guides | id, code, device_scope, owner | Không tự gắn model không có metadata |
| troubleshooting_guide_versions | guide_id, version, state, questions/steps/preconditions, source, reviewed_by/at, published_at, withdrawn_reason | Unique guide/version; bất biến published |
| support_reports | id, session_id unique, ticket_id, reason, source_snapshot, ai_summary nullable, summary_version, created_at | Facts gốc và summary riêng, chủ qua session |
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
