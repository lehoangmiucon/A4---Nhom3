# 03 — Dữ liệu và hợp đồng API

## 1. Trạng thái hợp đồng

Phân biệt ba mức: CURRENT_CODE (đã có mã và test); TARGET (thiết kế chưa triển khai); DEPLOYED (phải có bằng chứng môi trường). Không có endpoint nào được coi DEPLOYED chỉ vì có route trong workspace.

Quy ước: prefix /api; JSON snake_case; ID bản ghi integer dương; command_id là UUID do server tạo. M1a không nhận client_message_id; CR-01 tự tạo UUID cho message ở server. client_message_id ổn định do client gửi là TARGET M3; CURRENT_CODE giữ Idempotency-Key qua retry. Web dùng session/CSRF theo Sanctum; mobile consumer tương lai dùng Bearer token, app Flutter chưa có. Không đặt token trong URL.

Đọc mục 2 cho M1a/DTO/xóa mềm, mục 2.1 cho chat CR-01–13 và mục 3 cho hợp đồng tương lai. Hiện có **10 method/path hỗ trợ** (4 M1a + 5 conversation + 1 DELETE), không phải tất cả endpoint bảng TARGET đã có. API document được nhúng tại `/api`, không có `/api/openapi.json`. Schema `schemas/ai-decision.schema.json` là quyết định guide TARGET; schema provider hiện tại được tạo trong HelpdeskAiService::responseFormat và validation ConversationBrief.

## 2. CURRENT_CODE — bốn endpoint M1a

### Delta CR-07 — giới hạn phiên chat

CR-07 (20/11/2026): giới hạn CURRENT_CODE của chat đổi từ 5 lượt/phiên và 5 lượt/phút thành mặc định 40 lượt/phiên, 12 lượt/phút/tài khoản; trần tin tăng từ 100 thành 200 (tính cả user/assistant). Các mức cấu hình qua HELPDESK_AI_MAX_TURNS_PER_SESSION, HELPDESK_AI_MAX_TURNS_PER_MINUTE, HELPDESK_AI_MAX_MESSAGES_PER_SESSION; tối thiểu hiệu lực 1. Đây là số command chat, không phải giới hạn thời gian phiên hoặc số token. Giới hạn phút có thông báo chờ riêng và hồi phục theo cửa sổ thời gian; gửi report không bị quota/trần tin chặn. History model gồm hai lời báo đầu và tối đa chín tin gần nhất, bỏ tin giữa cũ khi vượt 6000 ký tự, giữ hai lời báo đầu và tin hiện tại. Không thay raw transcript hoặc cơ chế source hash. [Evidence](../../tasks/verification-cr07.md).

### Delta CR-03 — chặn báo trùng

CR-03 (20/11/2026): luồng chat AI đã dùng cùng kiểm tra chống trùng của form: cùng room_id/category_id, ticket còn new/reopened/assigned/in_progress thì không tạo thêm ticket/report/log/notification. Kết quả HTTP 200, `data.report=null`, session trở về collecting và report_requested=false; tin trợ lý giải thích sự cố đã ghi nhận nhưng chưa xử lý xong. Receipt lưu kết quả để replay không lặp tin nhắn; không trả mã/link/nội dung của ticket người khác. Không gộp transcript hoặc tự cấp quyền xem ticket cũ. resolved/closed cho phép báo lại; đây là kiểm tra phòng/danh mục, chưa có phân biệt symptom/thiết bị cụ thể. Thay thế ngoại lệ bỏ chống trùng của CR-01, không thay hợp đồng form. [Evidence](../../tasks/verification-cr03.md).

### Delta CR-02 — brief cho KTV

CR-06 (20/11/2026): fallback ở backend trích sự cố có nội dung đầu tiên và tối đa hai tin bổ sung gần nhất (tóm tắt tối đa 600 ký tự), bỏ câu chào/nhờ giúp thuần khi chọn tiêu đề và nội dung tổng hợp; CR-06 lúc triển khai chưa đổi facts; CR-12 sau đó loại lời chào/ngữ cảnh thuần khỏi incidentFacts và hash. Lời báo gốc/transcript vẫn giữ nguyên. Phần kiểm tra dự phòng lấy tối đa bốn trích đoạn lời người dùng khớp mẫu kiểm tra, không lấy lời khuyên AI làm việc đã làm. Gợi ý máy chiếu không bật/không sáng đèn là mẫu hệ thống cho KTV kiểm chứng, không phải chẩn đoán AI. Khi lời báo chỉ đề cập đúng một danh mục trong catalog khác danh mục đang chọn, thêm nhãn CẦN ĐỐI CHIẾU DANH MỤC; không tự đổi category_id. Đối chiếu reported_checks chuẩn hóa khoảng trắng ở cả nguồn và trích đoạn, giữ nguyên từ ngữ; raw transcript không đổi. [Evidence](../../tasks/verification-cr06.md).

CURRENT_CODE CR-02 đã kiểm chứng local; [evidence](../../tasks/verification-cr02.md). Yêu cầu ngày 20/11/2026: khi bàn giao, mô tả phiếu cần có tóm tắt vấn đề và gợi ý kiểm tra nhanh. AI chuẩn bị `handoff_brief` trong cùng response của lượt chat: `summary` (tối đa 600 ký tự), `reported_checks` (model chọn tối đa 3 mã U1/U2… trong enum user_reports; backend giải thành trích đoạn nguyên văn từ lời người dùng, tối đa 300 ký tự/mục), `suggestions` (tối đa 3 gợi ý, mỗi gợi ý tối đa 220 ký tự). Đây là dữ liệu suy ra có nhãn, chưa phải kết luận KTV. Backend kiểm tra schema; mã nguồn/trích đoạn không khớp làm brief bị loại toàn bộ. Brief hợp lệ lưu trong JSON nullable support_messages.handoff_brief với fingerprint của facts/phòng/danh mục nguồn và provider/model; không nhận brief từ client làm sự thật.

Khi gửi báo cáo, chỉ dùng brief có fingerprint khớp facts hiện tại; brief cũ/lỗi/không có dùng mẫu xác định từ nguồn. CR-02D bổ sung metadata backend `source_context: {room_id, category_id}` tại thời điểm tạo brief: cho phép bổ sung giá trị null nhưng không đổi giá trị đã biết. Backend kiểm tra fingerprint với ngữ cảnh nguồn này và facts hiện tại; brief cũ thiếu metadata vẫn kiểm tra hash nghiêm ngặt như trước. Câu lệnh gửi thuần không thuộc facts; câu vừa yêu cầu gửi vừa bổ sung dấu hiệu lỗi vẫn giữ nguyên trong facts. Description phiếu có các phần tóm tắt AI (hoặc tổng hợp hệ thống khi fallback), kiểm tra/kết quả người dùng đã nêu, gợi ý chưa xác minh và lời báo gốc. Transcript đầy đủ vẫn lưu trong report; description có thể rút gọn phần nguồn để trong giới hạn. Không gọi model tại handoff, không làm mất đường gửi khi model lỗi/quota. Không cập nhật phiếu đã gửi hoặc mở rộng quyền KTV xem phiên.

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
    "initial_description": "Máy chiếu không có hình.",
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

Đây là ví dụ hợp đồng, không phải dữ liệu production. id là ID phiên; không thêm một trường session_id đồng nghĩa ở cùng DTO. room_id/equipment_id có thể null sau khi đối tượng bị xóa; nhãn snapshot còn nguyên. initial_message_id xác định tin đầu do server lưu. M1b bổ sung initial_description (string nullable), lấy nội dung tin đầu từ server cho danh sách, chi tiết và snapshot tạo phiên mới. Replay key M1a cũ vẫn giữ nguyên response_data đã lưu, có thể chưa có trường này; GET trả DTO hiện tại. permitted_actions hiện rỗng, handoff hiện null, không hiển thị chức năng chưa có.

Tin nhắn gồm id, session_id, role, content_text, created_at. Danh sách có meta.pagination với current_page, per_page, total, last_page. meta.request_id mới cho từng HTTP response, không dùng làm key nghiệp vụ hay bằng chứng đã log liên kết request đó.

CURRENT_CODE CR-11 (20/11/2026): bổ sung DTO phiên `ticket_status` (nullable), `is_completed` và `can_delete` (boolean). Hai cờ true chỉ khi phiên `handed_off` có report và ticket `closed`; `resolved` của ticket còn chờ quản lý xác nhận, `resolved` của phiên chưa báo cáo đều false. `DELETE /api/support-sessions/{supportSession}` không có body/Idempotency-Key, auth/account.active/role student,teacher và chủ phiên; backend kiểm tra lại ticket trong transaction. Thành công 200 `{data:{id,deleted:true},meta:{request_id}}`, xóa mềm qua deleted_at; gọi lại của đúng chủ trả 200. Chưa đủ điều kiện trả 422 VALIDATION_ERROR với details.session; khác chủ/không tồn tại 404. Phiên đã xóa không còn trong GET/list/messages/conversation/command và không replay được qua POST support-conversations hoặc key tạo phiên M1a. Không xóa ticket, report, transcript hoặc command. Giữ nguyên contract cũ ngoài các field bổ sung; receipt cũ của phiên chưa xóa vẫn là snapshot gốc, GET trả cờ hiện tại. [Evidence GREEN/migration local](../../tasks/verification-cr11.md); chưa RELEASED.

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

401 UNAUTHENTICATED; 403 FORBIDDEN; 404 NOT_FOUND; 409 STATE_CONFLICT; 422 VALIDATION_ERROR hoặc IDEMPOTENCY_KEY_REUSED; 429 RATE_LIMITED. Lỗi không xử lý trong phạm vi renderer support trả 500 SERVICE_UNAVAILABLE. Riêng TX-B bàn giao thất bại đã có biến thể 503 data receipt/session ở mục 2.1; không ép biến thể đó thành error HANDOFF_PENDING TARGET.

Phản hồi có Cache-Control: no-store. Lỗi rate limit giữ Retry-After nếu có. Mẫu response mới chỉ áp dụng phạm vi API support-sessions hiện có; không tự thay mọi API cũ.

### Idempotency hiện có

- Phạm vi khóa: actor_id + operation=session.create + SHA-256(key).
- Hash payload từ room_id/equipment_id đã chuẩn hóa và description sau validation; field không nằm trong hợp đồng không được thêm vào logic.
- Lock user row trong transaction ngắn, kiểm tra lại actor; sau đó đọc key và tạo phiên/tin/kết quả trong cùng transaction.
- Key/payload giống nhau: replay nguyên data ban đầu và HTTP 201; meta.request_id có thể khác. CR-11 bổ sung guard phiên còn tồn tại trước replay; phiên đã xóa mềm trả 404, không khôi phục snapshot.
- Key giống, payload khác: 422; không coi là một lần tạo mới.
- Replay kiểm tra trước khi kiểm tra lại phòng/thiết bị còn tồn tại.
- GET là trạng thái hiện tại; replay có thể chứa ID phòng của snapshot đã bị xóa sau đó.
- Unique constraint là lớp bảo vệ DB; test SQLite hiện chưa là bằng chứng load đồng thời MySQL.
- M1a chưa có cơ chế cleanup key; không viết task xóa sớm làm mất bảo vệ retry.

## 2.1. CURRENT_CODE — CR-01: hội thoại và gửi báo cáo trong chat

CR-13 (20/11/2026): response hội thoại bổ sung `diagnosis`, projection từ messages/receipt server trong cùng ngữ cảnh phòng/danh mục/thiết bị. Fields: `branch` nullable (computer_power/projector_power/computer_display/projector_display/network_connection/audio_output), `label` nullable, `stage` (unclassified/collecting_observations/ready_for_it/safety_stop/handed_off/resolved), `observations` [{id,label,status,evidence}], `asked_steps` [id], `unknowns` [label], `next_question` {id,text} nullable, `technical_suggestions` [text]. Status quan sát: reported_ok/reported_absent/reported/unknown; tất cả là lời người dùng báo, chưa KTV xác minh, không phải kết luận nguyên nhân. Projection không cấp quyền ghi hoặc thay category. Hoàn tất hỗ trợ vẫn do hành động hiện có; ready_for_it không tự tạo phiếu. Bỏ câu hỏi nhánh đã có kết quả/đã hỏi, chưa biết giữ unknown; sau tập câu hỏi hữu hạn đề nghị KTV. Không gọi thêm model cho phân nhánh, cùng lượt model nhận diagnostic_context để chuẩn bị brief. Backend ưu tiên ý định bàn giao trước model và giữ idempotency. Câu yêu cầu tự nhiên như “hãy báo cáo lên KTV cho mình” được nhận diện; câu phủ định, giả định, hỏi trạng thái/cách làm không tự gửi. CURRENT_CODE đã có evidence GREEN tại tasks/verification-cr13.md; không đổi schema request hoặc endpoint, chưa UAT/model thật hoặc RELEASED.

CURRENT_CODE CR-12 (20/11/2026): POST chat chưa gọi model cho tới khi có room_id hợp lệ, category_id và nội dung công việc/vấn đề cụ thể. Có thể chọn phòng/danh mục hoặc suy ra duy nhất từ lời nhắn; phòng từ QR được giữ. Thiếu thông tin trả câu hỏi xác định trong messages, vẫn lưu/replay và trả 201/200 như cũ; needs_context/needs_description phản ánh phần còn thiếu. Receipt operation=clarify không tính vào quota command chat/model; throttle chung và giới hạn tin vẫn áp dụng. Lời chào/yêu cầu hỗ trợ chung/câu chỉ nêu phòng hoặc danh mục không đủ mô tả sự cố. Khi bổ sung đủ, lượt kế tiếp gọi AI với lịch sử đã lưu. Report vẫn không cần model, nhưng phải có dữ kiện thực; report từ phiên chưa từng được hướng dẫn dùng tóm tắt dự phòng. Giữ lời người dùng nguyên bản trong report.transcript và phần trích LỜI BÁO GỐC. Không đổi endpoint/schema request/quyền. [Evidence](../../tasks/verification-cr12.md).

Yêu cầu cập nhật ngày 19/11/2026 triển khai lát cắt web sau. M1a giữ hợp đồng cũ; frontend portal mới dùng CR-01. Không gọi endpoint TARGET chỉ vì trùng tên: body/trạng thái CR-01 dưới đây là hợp đồng thực tế đang triển khai.

| Method/path | Input | Kết quả |
| --- | --- | --- |
| POST /support-conversations | content 1–1000; room_id/category_id nullable; action chat/report/resolve, mặc định chat; Idempotency-Key | 201 sau lưu phiên/tin và trả lời hoặc phiếu; phòng chưa rõ được hỏi trong chat |
| POST /support-sessions/{supportSession}/messages | content; expected_version integer >=0; room_id/category_id nullable; action; Idempotency-Key | 200 lượt tiếp, 201 khi report commit; 202 khi cùng intent đang chạy |
| POST /support-sessions/{supportSession}/escalations | expected_version; room_id/category_id nullable; Idempotency-Key | Tương đương yêu cầu rõ ràng “Gửi báo cáo giúp tôi”; thiếu facts thì hỏi, đủ thì tạo phiếu không cần gọi model |
| GET /support-sessions/{supportSession}/conversation | Không | Snapshot hiện tại có toàn bộ tin đã lưu, report của chủ phiên và context |
| GET /support-sessions/{supportSession}/commands/{command} | UUID command | Receipt đúng parent/chủ phiên; URL không cấp quyền |

Ví dụ request CURRENT_CODE (ID phòng/danh mục minh họa, phải thay bằng dữ liệu tồn tại trên máy bạn):

```http
POST /api/support-conversations
Accept: application/json
Content-Type: application/json
Idempotency-Key: demo-intent-unique-001
Authorization: Bearer <access_token>

{"content":"Máy tính không mở lên","room_id":12,"category_id":3,"action":"chat"}
```

Web session gửi CSRF thay Bearer. Dùng `data.session.id`/`data.session.version` nhận từ server cho POST messages tiếp theo; giữ nguyên key/payload của **chính lần gửi đó** khi mất response, không tái dùng key tạo phiên cho tin mới. Không dự đoán version=1 của M1a cho chat: service có thể tăng version ở cả lượt ghi user và reply. Khi report còn cần thông tin, `report_requested=true`, `report=null` và status collecting; chỉ receipt chứa report/ticket thật mới là đã tạo phiếu.

Quyền: chỉ student/teacher active và verified, cùng chủ phiên; khác chủ/parent trả 404. Không nhận messages, user_id, priority, title, description, report từ client; backend lấy facts/tin gốc đã lưu. Room/category chỉ nhận ID tồn tại từ lựa chọn người dùng hoặc khớp duy nhất mã/tên trong tin người dùng; không nhận facts do model bịa. Báo cáo giữ transcript đầy đủ riêng; ticket.description chứa tóm tắt/gợi ý có nhãn chưa xác minh, projection quan sát CR-13 và phần trích LỜI BÁO GỐC. Tập facts dùng tóm tắt/hash loại câu lệnh thuần/chào/ngữ cảnh; phần trích nguồn lấy tin user gốc và có thể rút gọn để giới hạn 5000 ký tự. Transcript đầy đủ ở report giữ nguyên, không biến lời AI thành kết luận KTV.

Response: `{data: {session, messages, needs_context, needs_description, diagnosis, category_label, report, command}, meta: {request_id}}`. session bổ sung category_id và report_requested. report null hoặc `{id, ticket_id, ticket_number, status, url}` của chính người báo. messages có id/role/content_text/created_at, GET conversation trả toàn bộ tin đã lưu, không phân trang. Trần chặn chat mặc định 200 tin trước ghi, không phải LIMIT của GET và không phải hard cap số hàng: report bypass trần và response có thể nhiều hơn 200 tin. GET messages M1a vẫn phân trang riêng. command null hoặc `{id,status,status_url}`; status processing/handoff_pending/completed.

Khóa CR-01 được băm SHA-256, unique actor_id/key_hash trong support_commands. Fingerprint gồm session ban đầu (null khi khởi tạo), content, room_id/category_id, action; expected_version là precondition, không nằm trong fingerprint. Replay kiểm tra trước version; completed trả nguyên snapshot/status đã lưu; processing trả 202 và URL tra cứu, không gọi lại provider. Sau 30 giây, retry kết thúc lượt AI chưa xác nhận bằng fallback và không gọi provider lần hai. Không tự động chuyển snapshot replay thành state mới; GET conversation là nguồn trạng thái hiện tại.

TX-A khóa actor/phiên, lưu tin người dùng + receipt + version; đủ facts và có yêu cầu gửi thì lưu handoff_pending. Gọi provider ngoài transaction. Kết quả AI chỉ được ghi nếu version còn khớp; kết quả về sau khi gửi phiếu bị bỏ. Mỗi actor một lượt AI processing trong cửa sổ 30 giây; giới hạn cấu hình mặc định 40 command chat/phiên và 12 command chat/phút/actor (CR-07, có tính lượt fallback). clarify/resolve/report không tính quota chat; đây chưa phải reservation tiền/token phân tán. POST chung có throttle chống spam 30/phút; yêu cầu report không tiêu số lần gọi model.

TX-B tạo phiếu, report, log, audit và **DB notification** cho quản trị, rồi lưu handed_off/command completed cùng commit. Notification hiện chỉ có database channel nên không gọi mạng trong transaction; worker/outbox cho mail/push còn TARGET. Phiếu lấy ưu tiên teacher=high/student=normal từ backend. CR-03 thay ngoại lệ CR-01: form/chat cùng chặn trùng phòng/danh mục active như mục 2. Chat không auto-merge, không tạo report mới khi bị chặn; resolved/closed cho phép báo mới. unique session_id ngăn tạo nhiều report cho cùng phiên.

503 sau TX-B lỗi trả **data chứa receipt và session handoff_pending**, không có report giả. Client retry cùng key; TX-A vẫn còn, TX-B rollback không còn ticket/notification. Đây là biến thể CR-01, khác error.details.HANDOFF_PENDING của hợp đồng M2 đầy đủ còn TARGET. 409 STATE_CONFLICT yêu cầu tải lại; 422 VALIDATION_ERROR hoặc IDEMPOTENCY_KEY_REUSED; no-store trên response. Swagger giữ quy ước repo chỉ khai báo response thành công, mô tả các trạng thái khác trong operation description.

Người dùng xác nhận “Đã khắc phục được” (action resolve) chỉ đổi phiên thành resolved, không tạo/đóng phiếu KTV. User có thể tiếp tục hoặc yêu cầu gửi sau đó. Không tự coi câu trả lời AI là đã giải quyết. Câu “đừng/không gửi báo cáo” hủy ý định còn chờ; câu điều kiện không tự gửi. Backend chặn các mẫu model tự tuyên bố đã gửi phiếu; thẻ mã phiếu chỉ lấy từ DB.

## 3. TARGET — endpoint mở rộng theo task

Không tạo frontend gọi phần TARGET trước khi có task API, schema và test. Các đường dẫn messages/escalations/commands đã có biến thể CR-01 ở mục 2.1; bảng dưới giữ thiết kế mở rộng M2/M3 với hợp đồng khác, không được gửi body tương lai vào endpoint CR-01.

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
| support_sessions | user_id restrictOnDelete; room_id/equipment_id nullable nullOnDelete; category_id nullable nullOnDelete (CR-01); report_requested boolean (CR-01); nhãn snapshot; status; version; last_activity_at; deleted_at nullable (CR-11); index user/status/id |
| support_messages | session_id cascadeOnDelete; role, content_text, client_message_id nullable; handoff_brief JSON nullable (CR-02); unique session/client_message_id; index session/id |
| support_idempotency_keys | actor_id restrictOnDelete; operation, key SHA-256 64 ký tự, request_hash, response_data JSON, response_status; unique actor/operation/key (M1a) |
| support_commands | id UUID; session_id restrictOnDelete; actor_id restrictOnDelete; key_hash, request_hash; operation; status (processing/handoff_pending/completed); version; response_data JSON nullable, response_status nullable; unique actor_id/key_hash (CR-01) |
| support_reports | id; session_id unique restrictOnDelete; ticket_id restrictOnDelete; transcript JSON (CR-01) |

Không tự sửa migration đã chạy ở môi trường dùng chung. Thay đổi schema tiếp theo dùng migration bổ sung.

### TARGET

| Bảng | Mục đích/ràng buộc |
| --- | --- |
| support_commands (mở rộng) | M2-08 chuẩn hóa receipt bàn giao độc lập; M3 thêm purpose, snapshot/deadline và unique tin/purpose cho AI |
| support_step_attempts | Guide version, snapshot phát cho người dùng, kết quả và thời điểm — M3 |
| troubleshooting_guides / versions | Phạm vi thiết bị, câu hỏi/bước, điều kiện, trạng thái và người duyệt; unique guide/version — M3 |
| support_reports (mở rộng) | Bổ sung reason, source_snapshot, ai_summary nullable riêng biệt khi hoàn tất cẩm nang |
| support_handoffs | report_id unique, version, người/thời điểm tiếp nhận, deadline — M5 |
| support_outbox | event_id unique, payload tối thiểu, trạng thái, lịch thử lại — M2-09 |
| support_deliveries | unique event/recipient/channel, kết quả từng kênh — M2-04 |
| support_follow_ups | Report, actor, nội dung, client_message_id, thời điểm; không trực tiếp reopen ticket — M4 |
| support_attachments | Session/report, chủ sở hữu, storage key private, MIME/size và kết quả kiểm tra; không URL công khai — M6 |
| quota/usage store | Reservation nguyên tử dùng chung worker, actual usage; chọn DB/cache sau kiểm tra môi trường — M4 |

Equipment hiện có room_id, category_id, name, status. Dữ liệu model/vị trí lắp/tiếp cận cần task migration và quản trị riêng. Thiếu metadata không được suy đoán để bỏ qua precondition.

## 5. Nhất quán gộp sự cố

Mỗi report giữ riêng; ticket có thể dùng chung. Không dùng unique(room_id, category_id) để khẳng định mọi lỗi là một sự cố. Với thiết bị xác định, khóa thiết bị ổn định trước tìm/tạo phiếu ở cả form và agent. Không có thiết bị thì không tự merge đồng thời trong bản đầu.

Bàn giao thành công, log, report, ticket và outbox phải commit/rollback cùng TX-B; receipt pending và handoff_pending đã commit riêng ở TX-A để phục hồi. Không gọi mạng trong transaction. Retry không được gửi nhiều notification DB; push có event_id để loại trùng.

Từ điển field/constraint: [14](14_TU_DIEN_DU_LIEU.md). Quy tắc xử lý và bảng quyết định: [11](11_QUY_TAC_NGHIEP_VU.md). Ma trận quyền: [12](12_MA_TRAN_PHAN_QUYEN.md). ERD CURRENT/TARGET: [sơ đồ dữ liệu](so-do/04_ERD.md).

## 6. Quản lý hợp đồng

Hợp đồng mục này là baseline schema, Swagger mô tả phần đã triển khai. Trước mỗi endpoint mới: khóa field/enum/status/error, thêm contract test, triển khai rồi cập nhật trạng thái CURRENT_CODE. Không thêm trường response bằng cách trả toàn bộ model. Thay đổi breaking cần CR và phương án tương thích client; thêm field cũng phải kiểm tra consumer.
