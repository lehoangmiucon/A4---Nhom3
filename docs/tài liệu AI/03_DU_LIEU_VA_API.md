# 03 — Dữ liệu và hợp đồng API

## 1. Trạng thái hợp đồng

Phân biệt ba mức: CURRENT_CODE (đã có mã và test); TARGET (thiết kế chưa triển khai); DEPLOYED (phải có bằng chứng môi trường). Không có endpoint nào được coi DEPLOYED chỉ vì có route trong workspace.

Quy ước: prefix /api; JSON snake_case; ID bản ghi integer dương; command_id/client_message_id là UUID do client giữ ổn định qua retry. Web dùng session/CSRF theo Sanctum; Flutter dùng Bearer token. Không đặt token trong URL.

## 2. CURRENT_CODE — bốn endpoint M1a

| Method/path | Input | Kết quả |
| --- | --- | --- |
| POST /support-sessions | Header Idempotency-Key; room_id; equipment_id nullable; description | 201, data là snapshot khi tạo |
| GET /support-sessions | status nullable; page >= 1; per_page 1–50, mặc định 20 | 200, data là array, phiên mới trước |
| GET /support-sessions/{supportSession} | ID số | 200, trạng thái hiện tại; khác chủ phiên 404 |
| GET /support-sessions/{supportSession}/messages | after_id >= 0; page >= 1; per_page 1–50, mặc định 20 | 200, tin theo id tăng dần |

Quyền: student/teacher, active, email_verified_at khác null, chủ phiên. POST giới hạn 30 yêu cầu/phút/tài khoản trong code hiện có; đây không phải quota AI.

### Input tạo phiên

```json
{
  "room_id": 12,
  "equipment_id": 34,
  "description": "Máy chiếu không có hình."
}
```

description là chuỗi sau trim, 1–1.000 ký tự Unicode. room_id bắt buộc tồn tại cho intent mới; equipment_id nếu có phải thuộc phòng. user_id, status, version, messages bị cấm từ client. Key 1–128 ký tự ASCII thuộc chữ/số/dấu chấm/gạch dưới/hai chấm/gạch ngang; không dùng như thông tin xác thực.

### Output tạo/đọc phiên

```json
{
  "data": {
    "id": 123,
    "status": "collecting",
    "version": 1,
    "room_id": 12,
    "room_label": "Phòng A203",
    "room_code": "A203",
    "building_label": "Tòa A",
    "equipment_id": 34,
    "equipment_label": "Máy chiếu",
    "initial_message_id": 456,
    "permitted_actions": [],
    "handoff": null,
    "last_activity_at": "2026-11-19T03:00:00.000000Z",
    "created_at": "2026-11-19T03:00:00.000000Z",
    "updated_at": "2026-11-19T03:00:00.000000Z"
  },
  "meta": {
    "request_id": "6fd5c3ee-1111-4000-8000-123456789abc"
  }
}
```

Đây là ví dụ hợp đồng, không phải dữ liệu production. id là ID phiên; không thêm một trường session_id đồng nghĩa ở cùng DTO. room_id/equipment_id có thể null sau khi đối tượng bị xóa; nhãn snapshot còn nguyên. initial_message_id xác định tin đầu do server lưu. permitted_actions hiện rỗng, handoff hiện null, không hiển thị chức năng chưa có.

Tin nhắn gồm id, session_id, role, content_text, created_at. Danh sách có meta.pagination với current_page, per_page, total, last_page. meta.request_id mới cho từng HTTP response, không dùng làm key nghiệp vụ hay bằng chứng đã log liên kết request đó.

### Lỗi hiện có

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Dữ liệu chưa hợp lệ.",
    "details": {
      "description": ["Nội dung mô tả không hợp lệ."]
    }
  },
  "meta": {
    "request_id": "6fd5c3ee-2222-4000-8000-123456789abc"
  }
}
```

401 UNAUTHENTICATED; 403 FORBIDDEN; 404 NOT_FOUND; 422 VALIDATION_ERROR hoặc IDEMPOTENCY_KEY_REUSED; 429 RATE_LIMITED; lỗi nội bộ hiện trả 500 SERVICE_UNAVAILABLE. Không tự đổi HTTP 500 thành 503 trong tài liệu giả như đã có code. Tài liệu hóa rõ 503/409 khi triển khai các nhánh mới.

Phản hồi có Cache-Control: no-store. Lỗi rate limit giữ Retry-After nếu có. Mẫu response mới chỉ áp dụng phạm vi API support-sessions hiện có; không tự thay mọi API cũ.

### Idempotency hiện có

- Phạm vi khóa: actor_id + operation=session.create + SHA-256(key).
- Hash payload từ room_id/equipment_id đã chuẩn hóa và description sau validation; field không nằm trong hợp đồng không được thêm vào logic.
- Lock user row trong transaction ngắn, kiểm tra lại actor; sau đó đọc key và tạo phiên/tin/kết quả trong cùng transaction.
- Key/payload giống nhau: replay nguyên data ban đầu và HTTP 201; meta.request_id có thể khác.
- Key giống, payload khác: 422; không coi là một lần tạo mới.
- Replay kiểm tra trước khi kiểm tra lại phòng/thiết bị còn tồn tại.
- GET là trạng thái hiện tại; replay có thể chứa ID phòng của snapshot đã bị xóa sau đó.
- Unique constraint là lớp bảo vệ DB; test SQLite hiện chưa là bằng chứng load đồng thời MySQL.
- M1a chưa có cơ chế cleanup key; không viết task xóa sớm làm mất bảo vệ retry.

## 3. TARGET — endpoint mở rộng theo task

Không tạo frontend gọi các endpoint này trước khi có task API, schema và test.

| Endpoint | Input chính | Output và mốc |
| --- | --- | --- |
| POST /support-sessions/{supportSession}/turns | message_id, expected_version | 202 Command, xử lý tin đầu đã lưu — M3 |
| POST /support-sessions/{supportSession}/messages | content, client_message_id, expected_version | 202 Command, lưu tin trước rồi xử lý — M3 |
| GET /support-sessions/{supportSession}/commands/{command_id} | Không | Command thuộc đúng phiên — M2 cho bàn giao; M3 mở rộng lượt AI |
| POST /support-sessions/{supportSession}/step-results | attempt_id, result, expected_version | Session hoặc Command — M3 |
| POST /support-sessions/{supportSession}/escalations | reason, expected_version | 201 Handoff sau commit đồng bộ local; thông báo gửi bất đồng bộ — M2 |
| POST /support-sessions/{supportSession}/resolutions | confirmed=true, expected_version | Session, không đóng phiếu KTV — M4 |
| POST /support-sessions/{supportSession}/reopenings | note, expected_version | Session tự xử lý, giữ lịch sử — M4 |
| POST /support-sessions/{supportSession}/cancellations | reason nullable, expected_version | Session, không hủy phiếu đã bàn giao — M4 |
| POST /support-sessions/{supportSession}/attachments | file | Attachment private — M6 |
| GET /support-attachments/{attachment_id} | Không | Nội dung ảnh sau policy — M6 |
| GET /support-reports/{report_id}/progress | Không | Progress DTO tối thiểu — M2 |
| POST /support-reports/{report_id}/follow-ups | content, client_message_id | FollowUp/yêu cầu xử lý lại — M4 |
| POST /admin/support-handoffs/{handoff_id}/acknowledgements | expected_version | Handoff có acknowledged_by/at — M5 |

Mọi endpoint ghi có Idempotency-Key; thêm schema tương ứng vào Swagger trước khi frontend dùng. Endpoint mới ngoài support-sessions cần được đưa vào error renderer đúng scope; không giả định bootstrap hiện xử lý hết đường dẫn tương lai.

### Quy tắc input/response chung cho TARGET

- expected_version là integer >= 1; client không tự đặt version kết quả.
- content sau trim dài 1–1.000 ký tự Unicode; note nếu bắt buộc dài 1–2.000 ký tự; reason theo enum. Không nhận HTML thực thi. Thay giới hạn cần cập nhật contract/test trước client.
- result chỉ nhận resolved, not_fixed, cannot_perform; “không chắc” không tự coi resolved.
- reason chỉ nhận enum từ tài liệu 04; metadata hành động không do model tự thêm.
- Header idempotency dùng lại qua retry; client_message_id không đổi.
- Unique(session_id, input_message_id, purpose) ngăn turns và messages lên lịch xử lý cùng tin hai lần.
- 409 SESSION_VERSION_CONFLICT: tải lại, không tự ghi đè bằng dữ liệu cũ.
- 202 dành cho lượt AI có command_id và status_url dùng được; bàn giao M2 commit local đồng bộ và trả 201, không trả 202 mà thiếu endpoint tra cứu.
- 201 chỉ sau khi dữ liệu mới đã commit; replay trả status đã lưu.
- Command: id, status, result nullable, error nullable, created_at, completed_at nullable; status thuộc pending/processing/succeeded/failed/discarded.
- Command có purpose để phân biệt handoff với ai_turn; result của handoff là Handoff DTO, không phải quyết định model. 503 sau TX-A có error.code=HANDOFF_PENDING, error.details.command_id và error.details.status_url; không trả Handoff giả khi TX-B chưa commit. Nếu chưa lưu được receipt thì lỗi không kèm cam kết đã nhận.
- Bàn giao: fingerprint chứa actor/session/reason, không chứa expected_version vì đây là precondition. Replay intent đã thành công trước kiểm tra version; intent mới version cũ trả 409. Xem thứ tự khóa, retry và recovery ở tài liệu 11.
- Progress: public_reference, ticket_number, display_status, acknowledged_at, assigned_at, updated_at; không chứa dữ liệu người báo khác.
- Handoff: id, version, session_id, report_id, ticket_id, ticket_number, acknowledgement, notification_status theo quyền. Nhận response này không có nghĩa quản trị đã tiếp nhận; đọc Session để lấy version mới trước lệnh tiếp theo.
- URL trạng thái không phải capability token; vẫn phải xác thực và kiểm tra chủ phiên/report.

### Quản trị hướng dẫn — TARGET

Danh sách/chi tiết/soạn nháp ở /admin/troubleshooting-guides; version ở /admin/troubleshooting-guides/{guide_id}/versions; review-submissions/publications/withdrawals thuộc /admin/troubleshooting-guide-versions/{version_id}. Vòng đời draft → in_review → published → withdrawn; từ chối duyệt về draft, lưu người/lý do. Danh sách phân trang. Published bất biến, sửa tạo version mới. Mỗi thao tác có policy, validation, schema request/response và test riêng trước khi có route thật.

## 4. Dữ liệu

### CURRENT_CODE

| Bảng | Cột/ràng buộc chính |
| --- | --- |
| support_sessions | user_id restrictOnDelete; room_id/equipment_id nullable nullOnDelete; nhãn snapshot; status; version; last_activity_at; index user/status/id |
| support_messages | session_id cascadeOnDelete; role, content_text, client_message_id nullable; unique session/client_message_id; index session/id |
| support_idempotency_keys | actor_id restrictOnDelete; operation, key SHA-256 64 ký tự, request_hash, response_data JSON, response_status; unique actor/operation/key |

Không tự sửa migration đã chạy ở môi trường dùng chung. Thay đổi schema tiếp theo dùng migration bổ sung.

### TARGET

| Bảng | Mục đích/ràng buộc |
| --- | --- |
| support_commands | Có từ M2: receipt intent bàn giao, actor/session/operation/purpose, intent_key_hash, request_hash, response nullable; unique actor/operation/intent_key_hash; M3 thêm snapshot/deadline và unique tin/purpose cho AI |
| support_step_attempts | Guide version, snapshot phát cho người dùng, kết quả và thời điểm |
| troubleshooting_guides / versions | Phạm vi thiết bị, câu hỏi/bước, điều kiện, trạng thái và người duyệt; unique guide/version |
| support_reports | session_id unique, user qua session, ticket_id, lý do, dữ liệu gốc và tổng hợp |
| support_handoffs | report_id unique, version, người/thời điểm tiếp nhận, deadline |
| support_outbox | event_id unique, payload tối thiểu, trạng thái, lịch thử lại |
| support_deliveries | unique event/recipient/channel, kết quả từng kênh |
| support_follow_ups | Report, actor, nội dung, client_message_id, thời điểm; không trực tiếp reopen ticket |
| support_attachments | Session/report, chủ sở hữu, storage key private, MIME/size và kết quả kiểm tra; không URL công khai |
| quota/usage store | Reservation nguyên tử dùng chung worker, actual usage; chọn DB/cache sau kiểm tra môi trường |

Equipment hiện có room_id, category_id, name, status. Dữ liệu model/vị trí lắp/tiếp cận cần task migration và quản trị riêng. Thiếu metadata không được suy đoán để bỏ qua precondition.

## 5. Nhất quán gộp sự cố

Mỗi report giữ riêng; ticket có thể dùng chung. Không dùng unique(room_id, category_id) để khẳng định mọi lỗi là một sự cố. Với thiết bị xác định, khóa thiết bị ổn định trước tìm/tạo phiếu ở cả form và agent. Không có thiết bị thì không tự merge đồng thời trong bản đầu.

Bàn giao thành công, log, report, ticket và outbox phải commit/rollback cùng TX-B; receipt pending và handoff_pending đã commit riêng ở TX-A để phục hồi. Không gọi mạng trong transaction. Retry không được gửi nhiều notification DB; push có event_id để loại trùng.

Từ điển field/constraint: [14](14_TU_DIEN_DU_LIEU.md). Quy tắc xử lý và bảng quyết định: [11](11_QUY_TAC_NGHIEP_VU.md). Ma trận quyền: [12](12_MA_TRAN_PHAN_QUYEN.md). ERD CURRENT/TARGET: [sơ đồ dữ liệu](so-do/04_ERD.md).

## 6. Quản lý hợp đồng

Hợp đồng mục này là baseline schema, Swagger mô tả phần đã triển khai. Trước mỗi endpoint mới: khóa field/enum/status/error, thêm contract test, triển khai rồi cập nhật trạng thái CURRENT_CODE. Không thêm trường response bằng cách trả toàn bộ model. Thay đổi breaking cần CR và phương án tương thích client; thêm field cũng phải kiểm tra consumer.
