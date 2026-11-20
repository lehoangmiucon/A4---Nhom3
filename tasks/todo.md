# Công việc AI Agent

Nguồn yêu cầu: [Tài liệu AI](<../docs/tài liệu AI/README.md>). Đây là bảng task chính duy nhất. Checkbox hoàn thành nghĩa là DONE_CODE với evidence, không có nghĩa đã deployed. Các tên test mới dưới đây là tên dự kiến; chỉ có giá trị kiểm chứng sau khi file/test thực sự tồn tại và có test được chạy.

- [x] DATA-CLEAN-01 (20/11/2026) — Theo yêu cầu dọn dữ liệu test và xác nhận cả 6 phiếu thủ công #6–11 đều là test: sao lưu các bản ghi liên quan, xóa 21 phiên AI/21 phiếu cùng dữ liệu phụ thuộc bằng transaction local; giữ 14 tài khoản, phòng/thiết bị/danh mục/phân quyền. Đã kiểm tra số lượng sau xóa. Đây là thao tác quản trị dữ liệu test, không đổi điều kiện xóa phiên của người dùng. [Evidence](verification-data-cleanup.md).

## M1a — lưu và đọc lại phiên bằng API

### DOC-SYNC-01 — bàn giao tài liệu trước push (20/11/2026)

- [x] Đối chiếu bộ tài liệu AI, contract/provider/migration và task với code đến CR-13; sửa mâu thuẫn CURRENT_CODE/TARGET, giữ evidence lịch sử, bổ sung cách chạy/file sở hữu/thứ tự nhận việc. Checker đạt 58 Markdown, 17 file handoff, 19 FR có trong RTM; 0 link/file đích thiếu. Swagger/phiên/xóa phiên 37 tests passed / 887 assertions. Chỉ tài liệu và chuỗi mô tả Swagger, không đổi behavior/DB/secret/commit/push. [Evidence](verification-doc-sync01.md).

Các việc tài liệu còn lại được phát hiện qua review (không là blocker cho đọc/tiếp tục code theo contract):

- [ ] DOC-SYNC-02 — Vẽ/render sơ đồ CURRENT_CODE CR-01–13 riêng với luồng đồng bộ, provider local/API, delete/diagnosis; giữ ID/bản SVG baseline TARGET có nhãn lịch sử. Verify Mermaid/render + đối chiếu 02/03; không coi SVG 19/11 là runtime hiện tại.
- [ ] DOC-SEC-01 — Bổ sung runbook SEC-01/02 độc lập khi có task xác minh security tương ứng; hai đường dẫn guide cũ không có trong checkout đã được thay bằng báo cáo security hiện có kèm nhãn thiếu. Không tự khẳng định bước chuyển dữ liệu/restore đã được kiểm chứng.

Truy vết phần đã có: AI-FR-001/002/003, AI-NFR-001/003/008; phạm vi kiểm chứng cụ thể ở verification-m1a.md, không bao phủ các nhánh agent còn TARGET.

- [x] Kiểm tra kiến trúc, dữ liệu, test và ghi baseline.
- [x] T1: Viết test tạo/đọc phiên, quyền, validation và idempotency; xác nhận RED do endpoint chưa có. File: tests/Feature/Support/SupportSessionTest.php.
- [x] T2: Thêm migration và model phiên/tin/khóa idempotency. Nghiệm thu: FK, unique và rollback giao dịch đúng; verify: feature test. Tối đa 4 file nguồn.
- [x] T3: Thêm request, policy và service tạo phiên. Nghiệm thu: chỉ chủ phiên hợp lệ; cùng key không nhân đôi; khác payload lỗi. Verify: feature test.
- [x] T4: Thêm controller, route và lỗi JSON riêng. Nghiệm thu: 4 endpoint tạo/danh sách/chi tiết/tin nhắn; phiên người khác trả 404. Verify: feature test session và Bearer.
- [x] T5: Tài liệu Swagger và hướng dẫn API. Nghiệm thu: có header idempotency, body, filter, phân trang và mô tả scope. Verify: SwaggerDocumentationTest + test hợp đồng mới.
- [x] Checkpoint: test module đạt, không thêm lỗi toàn suite, review và cập nhật báo cáo. Không migrate database thực. Toàn suite còn 1 lỗi nền; xem verification-m1a.md.

## M1b — UI dùng bốn API đã có

- [x] M1B-01 — Danh sách và đọc lại phiên trên web. Module support-clients; FR-002/014, TC-003/004/018. Phụ thuộc M1a. AC: chỉ hiển thị phiên của người dùng; phân trang; phiên lỗi/không quyền có trạng thái rõ. File: resources/views/customer/partials/support-session.blade.php, public/customer/assets/js/support-session.js, public/customer/assets/css/support-session.css, tests/Feature/Support/SupportSessionUiTest.php. Verify: SupportSessionUiTest đạt 6/6 test, không gọi API trả phí.
- [x] M1B-02 — Tạo phiên từ phòng/mô tả. Module support-clients; FR-001/003/014, TC-001/002/005/018. Phụ thuộc M1B-01. AC: dùng POST đã có; key giữ qua retry; lỗi không giả thành công; form báo lỗi cũ vẫn dùng. File: resources/views/customer/partials/support-session.blade.php, public/customer/assets/js/support-session.js. Verify: SupportSessionUiTest (idempotency retry và validation error).
- [x] M1B-03 — Tiếp tục xem phiên sau reload/logout. Module support-clients; FR-002, NFR-001, TC-004. Phụ thuộc M1B-02. AC: lấy lịch sử server; xóa trạng thái giao diện khi đổi tài khoản; không giả có gửi tin tiếp/agent. File: public/customer/assets/js/support-session.js. Verify: SupportSessionUiTest, storage scoped by user_id và cleanup khi logout.
- [x] M1B-R1 — Review lifecycle client (M1B-01/02/03): tái hiện RED 7/8 ca JavaScript; sửa lỗi mạng/timeout, retry key+payload, lỗi 422, phản hồi muộn, đọc đủ trang tin và logout/storage bị chặn. File nguồn: support-session.js, partial, stylesheet; test: tests/js/support-session.test.mjs. Verify cuối: 15 ca M1b đạt, bao gồm các ca R2; chưa kiểm chứng screen reader thực.
- [x] M1B-R2 — Review tích hợp (M1B-02/03): RED test thiếu thiết bị và hai ca điền phòng; chọn equipment_id theo phòng, eager load rooms.equipment, đồng bộ form bằng handler phòng hiện có kể cả QR/loading; cập nhật asset version và contract initial_description. File nguồn: routes/web.php, portal-qr.js, portal.blade.php, partial; tests: SupportSessionUiTest và JS. Verify: 6 test UI / 44 assertions, Chrome headless 4 viewport với API giả.
- [x] M1B-R3a — Gộp giao diện theo yêu cầu người dùng: chỉ một launcher Hỗ trợ và một popover; nhúng chat cũ trong bảng phiên, giữ ba mục Phiên đã lưu/Tạo phiên/Hỏi AI, ghi rõ chat chưa lưu vào phiên. File: portal.blade.php, partial support-session, partial portal-chat, stylesheet. RED: test DOM đếm 2 launcher thay vì 1; GREEN: 7 UI test / 50 assertions.
- [x] M1B-R3b — Điều phối bảng chung: chat đóng cùng host panel khi điền nháp, chuyển mục và mở lại giữ view, fallback Escape, dọn chat/abort request khi logout, bump asset version. File: portal-chat.js, support-session.js, portal.blade.php; tests: SupportSessionUiTest và JS. Verify: JavaScript toàn repo 30 passed; Chrome 4 viewport với API/chat fake; không gọi model thật.
- [x] Checkpoint M1b — DONE_CODE sau gộp UI: PHP 143 passed / 1956 assertions; JavaScript toàn repo 30 passed; Chrome headless 320/768/1024/1440px đạt với API giả. Xem verification-m1b.md. Chưa xác nhận deployment, migration thật hoặc UAT đăng nhập/session/CSRF trên staging.

## M2 — Bàn giao không phụ thuộc AI

### CR-13 — phân nhánh quan sát và chuyển KTV đúng ý định (20/11/2026)

Yêu cầu từ hội thoại/ảnh: ghi nhận cảm nhận/dấu hiệu người dùng, không lặp kiểm tra nguồn đã xác nhận; hỏi quan sát tiếp theo theo nhánh; nhận “hãy báo cáo lên KTV cho mình” và chuyển trực tiếp; IT có quan sát đã xác nhận/chưa biết và gợi ý kiểm tra chưa xác minh. Giữ intake CR-12, quyền, provider và chống trùng/replay.

- [x] CR-13A — TDD nhận diện bàn giao tự nhiên + phủ định/giả định/câu hỗn hợp; report ưu tiên trước model, giữ dữ kiện trong câu hỗn hợp và không nhét câu lệnh thuần vào facts. Tối đa 3 file nguồn/test.
- [x] CR-13B — Projection phân nhánh từ messages server và context đã lưu: máy tính/máy chiếu không khởi động, mất hình, mạng, âm thanh. Mỗi lượt một câu hỏi quan sát, theo dõi mã câu hỏi qua receipt/kết quả; không lấy lời AI thành kết quả người dùng. Hết câu hỏi đề nghị KTV, không tự tạo phiếu. Đổi phòng/danh mục/loại thiết bị bỏ kết quả khác ngữ cảnh. 41 diagnostic tests passed; chưa phân biệt hai thiết bị cùng loại/cùng phòng nếu người dùng không nêu rõ.
- [x] CR-13C — IT brief có phân nhánh sơ bộ, quan sát nguyên văn, thông tin còn thiếu và hướng kiểm tra chưa xác minh; không kết luận hỏng linh kiện. PHP 276 passed / 2892 assertions, JS 62 passed, Chrome 1440/390px với phản hồi service SQLite :memory:/model fake đạt; Pint/diff check đạt. AI-FR-004/007/008/014, AI-NFR-002/003/008; UC-02/04, BR-45, TC-035. DONE_CODE, chưa UAT/model thật/RELEASED; [evidence](verification-cr13.md).

### CR-12 — xác định phòng và công việc trước khi hướng dẫn (20/11/2026)

Yêu cầu: trước hướng dẫn AI phải biết phòng hợp lệ, danh mục thiết bị/công việc và mô tả vấn đề. Nhận từ lựa chọn/QR hoặc suy ra duy nhất từ chat; thiếu gì hỏi đó, không gọi model hoặc tính lượt AI khi chỉ hỏi thông tin. Lưu hội thoại/replay như cũ; không coi lời chào/yêu cầu hỗ trợ chung hoặc câu chỉ nêu phòng/danh mục là dấu hiệu lỗi. Không đổi quyền, provider hoặc nghiệp vụ phiếu.

- [x] CR-12A — TDD backend chặn hướng dẫn thiếu ngữ cảnh/mô tả, giữ thông tin qua lượt tiếp và QR; test SQLite :memory:/fake provider, cập nhật acceptance cũ có chủ đích do yêu cầu mới. AI-FR-001/004/014, AI-NFR-003/005/008; UC-02, BR-44, TC-007/018 và TC-034. RED 13 fail / 1 pass; GREEN 14 intake tests.
- [x] CR-12B — Mở phần thông tin cần biết ngay từ đầu, câu chào/hỏi rõ phòng và công việc, giữ picker mở nếu còn thiếu và giữ dữ liệu đã biết. JS RED 2 fail; GREEN toàn repo 62 passed. Chrome API fake đạt 4 ca: thiếu/đủ thông tin × bố cục 1440/390px.
- [x] CR-12C — Đồng bộ contract/quy tắc/UI và evidence, review và hồi quy: PHP 235 passed / 2715 assertions, Pint đạt. Không migration/model thật hoặc tạo dữ liệu test trong DB local vừa dọn. DONE_CODE, chưa UAT/staging. [Evidence](verification-cr12.md).

### CR-11 — xóa phiên đã báo cáo và hoàn tất xử lý (20/11/2026)

Yêu cầu: chủ phiên được xóa mềm chỉ khi phiên handed_off có support_report gắn với phiếu closed (quản lý đã xác nhận). Phiếu resolved còn chờ xác nhận nên chưa được xóa; phiên tự resolved không có report cũng không được xóa. Giữ nguyên phiếu/report/transcript/receipt; phiên xóa không xuất hiện trong list/detail/messages/conversation hoặc replay lệnh cũ. Cờ is_completed/can_delete lấy từ backend, không tin client.

- [x] CR-11A — API: migration deleted_at, điều kiện backend theo ticket hiện tại, DELETE /api/support-sessions/{id}, quyền chủ phiên, DTO cờ và ticket_status; TDD SQLite :memory: + fake API/notification. RED 13 fail do thiếu field/route, GREEN 16 tests; giữ cả hai kho receipt/key và chặn replay phiên đã xóa. AI-FR-019/002/014, AI-NFR-001/006/008, BR-43; TC-033.
- [x] CR-11B — UI: nhãn kết quả trong lịch sử, nút Xóa phiên chỉ khi backend cho phép, xác nhận, khóa thao tác khi xóa/tải lịch sử; thành công dọn phiên đang mở và tải lại đúng trang, lỗi giữ phiên và cho thử lại. 60 JS passed; Chrome headless DOM/timer/API fake đạt.
- [x] CR-11C — Contract/Swagger/quyền/UI và evidence đã cập nhật. Toàn PHP 221 passed / 2626 assertions; migration local Ran batch 17. DONE_CODE + migration local, chưa UAT/staging/RELEASED. [Evidence](verification-cr11.md).

### CR-10 — sửa phân trang hội thoại xoay mãi (20/11/2026)

- [x] CR-10 — Script feedback chung tự xóa role=status sau 5 giây, khiến tải trang kế tiếp bật spinner rồi lỗi khi truy cập trạng thái đã mất. Đánh dấu vùng chat data-auto-dismiss=false và tôn trọng marker trong feedback; giữ toast tạm tự ẩn, đổi version asset customer. AI-FR-002/014, AI-NFR-008; TC-003/004/018. Test tích hợp hai script với fake clock/API: RED 1 failed / 21 passed, GREEN và giới hạn trong [evidence](verification-cr10.md). DONE_CODE; chưa kiểm chứng browser thật/deployment.

### CR-02 — bản bàn giao dễ đọc và gợi ý nhanh cho KTV (20/11/2026)

DONE_CODE + smoke Ollama thật local; [evidence CR-02](verification-cr02.md): 168 PHP tests / 2175 assertions; smoke riêng 1 test / 15 assertions. Đã kiểm chứng hiển thị thực tế trên giao diện KTV staff/ticket-detail (`white-space: pre-line`, đầy đủ 4 phần) qua Chrome thật ([verification-e2e-web.md](verification-e2e-web.md)). Migration bổ sung Ran batch 16; chưa UAT/staging hoặc eval đầy đủ.

Phạm vi: AI chuẩn bị tóm tắt/gợi ý cùng lượt chat, lưu kèm tin trợ lý trên server; khi gửi phiếu, đưa vào mô tả để admin/KTV đã có quyền xem đọc được. Tách nhãn AI chưa xác minh, kiểm tra người dùng đã nêu và lời báo gốc. Không gọi thêm model trong giao dịch/gửi báo cáo; lỗi/thiếu/stale brief vẫn tạo phiếu bằng mẫu xác định. Không sửa phiếu cũ hoặc mở rộng quyền đọc hội thoại.

- [x] CR-02A — Test RED/GREEN schema brief, lời xác nhận từ người dùng, không tin nội dung giả, tóm tắt stale, fallback và replay; thêm adapter/formatter và lưu brief theo fingerprint nguồn. Tách AI+formatter và persistence+handoff thành hai bước.
- [x] CR-02B — Kiểm chứng KTV được phân công thấy mô tả có tóm tắt/gợi ý, người khác không xem được; hồi quy và smoke Ollama thật trong DB bộ nhớ.
- [x] CR-02C — Migration bổ sung local, cập nhật contract/giới hạn và evidence. Không backfill/gọi lại AI cho các phiếu cũ.
- [x] CR-02D — Sửa mất brief khi gửi báo cáo: nhận diện câu “bạn hãy báo cáo giúp mình nhá”, chỉ loại câu lệnh thuần khỏi facts; giữ brief khi bổ sung phòng/danh mục chưa biết, loại khi đổi ngữ cảnh đã biết hoặc có facts mới. Truy vết AI-FR-008, AI-NFR-003/008, BR-41; SQLite :memory: + fake HTTP, toàn suite 180 passed / 2235 assertions; evidence bổ sung ở verification-cr02.md. Không sửa lại phiếu đã gửi.

### CR-09 — hiệu ứng hiện dần câu trả lời AI (20/11/2026)

- [x] CR-09 — Câu trả lời mới hiện dần chữ/con trỏ, nút Hiện toàn bộ, lịch sử/reduced-motion hiển thị ngay. Hủy timer khi đổi phiên/tạo mới/logout, không ép cuộn khi đọc tin cũ; screen reader nhận full text một lần. UI-03, AI-FR-014, AI-NFR-008; 46 JS passed, SupportSessionUiTest 7 passed / 49 assertions. Đây là hiệu ứng client sau phản hồi JSON, chưa streaming model. [Evidence](verification-cr09.md).

### CR-08 — phản hồi nút bấm và đặt lại khung chat (20/11/2026)

- [x] CR-08 — Hiệu ứng hover/nhấn/viền phản hồi cho nút chat và chuyển chế độ, busy label/spinner/aria-busy khi gửi hoặc tải, khóa hành động xung đột. Cuộc trò chuyện mới dọn receipt/retry/context/storage, xác nhận đã mở khung trống và bỏ tải phiên cũ đến muộn. UI-03, AI-FR-002/014/017, AI-NFR-008; 42 JS passed, SupportSessionUiTest 7 passed / 49 assertions. [Evidence CR-08](verification-cr08.md). Đã kiểm chứng E2E toàn diện trên trình duyệt Chrome thật (13/13 tiêu chí đạt, xem [verification-e2e-web.md](verification-e2e-web.md)).

### CR-07 — kéo dài phiên chat AI (20/11/2026)

- [x] CR-07 — Kéo dài phiên chat theo yêu cầu: mặc định 40 lượt chat/phiên, 12 lượt/phút/tài khoản, 200 tin/phiên; cấu hình qua env. AI nhận hai lời báo đầu và các tin gần nhất, tối đa 11 tin/6000 ký tự; giữ raw history để bàn giao. Giới hạn phút có thông báo chờ riêng; report vẫn bypass quota/trần tin. AI-FR-002/004/017, AI-NFR-005/008; toàn suite checkpoint 204 passed / 2524 assertions, focused cuối 32 passed / 343 assertions. [Evidence](verification-cr07.md).

### CR-06 — tổng hợp sự cố cho KTV khi AI không sẵn sàng (20/11/2026)

- [x] CR-06 — Bản bàn giao dự phòng hữu ích khi brief stale/AI chạm quota: bỏ lời chào khi chọn tiêu đề/tóm tắt, nêu sự cố đầu và thông tin mới nhất, trích lời kiểm tra đã nêu, gợi ý mẫu theo dấu hiệu máy chiếu không lên và cảnh báo lệch danh mục. Sửa revalidation trích đoạn khác whitespace; giữ raw transcript, không gọi thêm model lúc bàn giao. AI-FR-008, AI-NFR-003/008, BR-40/41; 201 passed / 2457 assertions, focused cuối 21/135; xem [evidence](verification-cr06.md) và bản xem trước phiên #7 (chỉ đọc, không cập nhật phiếu).

### CR-05 — trạng thái gửi tin và chờ phản hồi (20/11/2026)

- [x] CR-05 — Tin nhắn hiện ngay sau bấm gửi, nhãn Đang gửi/Chưa xác nhận, bong bóng AI đang suy nghĩ với ba chấm động; dừng hiệu ứng khi phản hồi/lỗi, retry không nhân đôi, phục hồi bản nháp khi validation lỗi, logout bỏ phản hồi muộn. UI-03, AI-FR-002/014/017, AI-NFR-008; JavaScript 39 passed, SupportSessionUiTest 7 passed / 49 assertions, smoke Chrome/API giả đạt cả reduced motion. [Evidence](verification-cr05.md).

### CR-04 — đổi danh mục và câu trả lời cho người dùng (20/11/2026)

- [x] CR-04 — Đồng bộ danh mục hiện tại vào ngữ cảnh model mỗi lượt chat và chặn lộ mã nguồn U1/U2/nhãn nội bộ trong reply. Giữ lịch sử phiên, brief và quyền gửi ở backend; không đổi danh mục theo output model. Truy vết AI-FR-004/008, AI-NFR-003/008; 198 passed / 2427 assertions, Pint đạt. [Evidence CR-04](verification-cr04.md). Đã kiểm chứng đổi danh mục đồng bộ context trên Chrome thật + model Ollama local ([verification-e2e-web.md](verification-e2e-web.md)).

### CR-03 — chặn báo trùng sự cố chưa xử lý (20/11/2026)

- [x] CR-03 — Đồng bộ chặn phiếu trùng giữa chat AI và form theo yêu cầu 20/11/2026: dùng TicketCreationService kiểm tra phòng/danh mục với phiếu new/reopened/assigned/in_progress; không tạo phiếu/report/log/notification mới, không tiết lộ phiếu người khác, giữ hội thoại và receipt replay. Phiếu resolved/closed không chặn lần báo mới; khác phòng/danh mục vẫn tạo được. Đây là chống trùng theo quy tắc hiện có, chưa phải so khớp symptom/thiết bị M2-05. Verify: 192 passed / 2396 assertions; [evidence](verification-cr03.md).

### LOCAL-AI-01 — chạy model offline theo yêu cầu 20/11/2026

DONE_CODE + smoke model thật local: [evidence](verification-local-ai.md), PHP 162/2130 assertions; smoke API riêng 1 test/10 assertions. Model qwen2.5:7b đã trả lời và bàn giao/replay đạt trong SQLite bộ nhớ. Cách chạy: [local-ai.md](../docs/local-ai.md).

- [x] Thêm cấu hình provider Ollama và adapter giữ nguyên contract reply/draft; test RED 3 fail do chỉ có OpenAI, GREEN 4 test/11 assertions. Giữ mặc định OpenAI cho môi trường chưa chọn local và test suite.
- [x] Cấu hình .env local dùng qwen2.5:7b đã có, khởi động Ollama, kiểm tra câu trả lời model thật và hồi quy. Không gửi dữ liệu lên cloud, không tạo phiếu/notification thật khi smoke.
- [x] Cập nhật quyết định D-01 và hướng dẫn/evidence offline; chỉ thay provider cho local test, không tự đổi provider production hoặc coi đạt safety/eval.

### CR-01 — Hai cách báo sự cố theo yêu cầu người dùng 19/11/2026

DONE_CODE ngày 20/11/2026; [evidence CR-01](verification-cr01.md): PHP 158/2119 assertions, JS 38 passed, Chrome fake bốn viewport. Hai migration local batch 15 đã chạy; chưa có khóa AI, chưa UAT staging. M2-01 được hoàn thành trong CR-01A; các task M2/M3/M4 còn lại giữ TARGET vì acceptance criteria rộng hơn lát cắt CR-01.

Yêu cầu mới ưu tiên: báo thủ công như cũ; chat AI hỏi/hướng dẫn, lưu hội thoại và gửi phiếu ngay trong chat khi người dùng yêu cầu. Không bắt điền lại form. Triển khai lát cắt web trước; không đánh dấu toàn M2/M3/M4 hoàn thành.

- [x] CR-01A — Tách TicketCreationService giữ nguyên hành vi form cũ; tách client AI cũ thành service dùng chung. Verify: HelpdeskChatTest, MobileApiTest và suite phiếu. Tối đa 4 file nguồn.
- [x] CR-01B — Migration commands/reports và context phiên; lượt chat lưu trên server, receipt chống lặp/version, không giữ DB transaction khi gọi AI. Verify: SupportConversationTest với SQLite/fake. Tối đa 5 file nguồn.
- [x] CR-01C — Backend tạo phiếu từ facts server sau yêu cầu rõ ràng; hỏi phòng/danh mục thiếu; report và DB notification cùng transaction; retry không lặp, không lộ ticket khác chủ. Đường gửi không cần AI. Verify: quyền, lỗi provider, retry, rollback và phiếu thật trong DB test. Tối đa 5 file nguồn.
- [x] CR-01D — Portal có hai lựa chọn Báo cáo thủ công / Nhờ AI hỗ trợ; AI là hội thoại có composer, lịch sử và nút gửi báo cáo trong chat; bỏ luồng điền nháp làm hành động chính. Verify: JavaScript, PHP và Chrome fake API. Tách markup/styles và JS thành hai bước khi triển khai.
- [x] CR-01E — Cập nhật contract, delta kế hoạch, Swagger và evidence; nêu migration/provider/staging đã/chưa kiểm chứng.

- [x] M2-01 — Tách nghiệp vụ tạo phiếu dùng chung. Module ticket-handoff; FR-008, NFR-008, TC-030. Phụ thuộc M1a. AC: form cũ giữ hành vi; controller và service không gọi vòng; tests cũ đạt như baseline. File: TicketController, TicketCreationService, test (3). Verify: HelpdeskChatTest, MobileApiTest và test nghiệp vụ phiếu liên quan. Không đồng thời đổi quy tắc gộp trong task này.
- [ ] M2-08 — Receipt bàn giao bền vững. Module support-session/ticket-handoff; FR-007/008, NFR-003/004, UC-04/12, TC-006/011/026. Phụ thuộc M2-01. AC: support_commands có intent unique; TX-A lưu receipt và handoff_pending, tăng version; finalized key M1a giữ nguyên; crash/retry không mất intent. File: migration/model/command service/test (4–5). Verify: SupportHandoffReceiptTest. Mã 08 bổ sung sau baseline 1.0, thực hiện trước M2-02 theo phụ thuộc.
- [ ] M2-09 — Schema và ghi outbox trong transaction. Module support-delivery; FR-008, NFR-004, TC-026. Phụ thuộc M2-01. AC: support_outbox và writer nhận transaction hiện tại; event unique; rollback không còn event; chưa gửi mạng. File: migration/model/writer/test (4). Verify: SupportOutboxPersistenceTest. Thực hiện trước M2-02 để TX-B không phụ thuộc bảng tương lai.
- [ ] M2-02 — Report và bàn giao đồng bộ. Module ticket-handoff; FR-007/008, NFR-003, UC-04/05/12, TC-011/012/026. Phụ thuộc M2-08/M2-09. AC: TX-B lưu report/phiếu/liên kết/log/outbox và command succeeded cùng commit; lỗi giữ receipt TX-A; không cần AI; key retry không nhân đôi; có recovery hữu hạn. File: report migration/model, EscalationService, test; tách task con trước làm nếu vượt 5 file. Verify: SupportHandoffTest, rollback/crash/recovery và constraint SQLite; concurrency MySQL còn riêng.
- [ ] M2-03 — API chuyển trực tiếp và đọc command. Module ticket-handoff; FR-007/008, UC-04/12, TC-011/023/030. Phụ thuộc M2-02. AC: reason/version/policy; 201 sau TX-B; TX-B lỗi trả 503 HANDOFF_PENDING kèm status_url dùng được khi TX-A đã lưu; GET command kiểm tra chủ phiên; không gọi model. Tách ghi và đọc thành task con trước làm nếu vượt 5 file nguồn. Verify: SupportHandoffApiTest và SwaggerDocumentationTest.
- [ ] M2-04 — Gửi DB notification từ outbox idempotent. Module support-delivery; FR-008, NFR-004, TC-026. Phụ thuộc M2-02/M2-09. AC: dùng outbox writer/schema đã có, thêm delivery ledger; worker restart không mất; event-recipient-channel không trùng. File: delivery migration/model, job, test (4–5). Verify: SupportDeliveryTest với retry/crash; không gửi push/mail thật.
- [ ] M2-05 — Quy tắc gộp có căn cứ cho cả form và agent. Module ticket-handoff; FR-009, TC-006/013. Phụ thuộc M2-01/02. AC: thiết bị xác định khóa ổn định; hai lỗi khác nhau không tự gộp; người báo sau có report riêng. Đây là thay đổi có chủ đích của quy tắc gộp cũ theo baseline mới, cần mô tả delta và tests tương thích trước sửa. File: service tạo/gộp, controller nếu cần, test (3–5). Verify: SupportDuplicateReportTest; test MySQL ở M8.
- [ ] M2-06 — Progress DTO và policy người báo sau. Module ticket-handoff; FR-010, NFR-001, TC-014/023. Phụ thuộc M2-02/05. AC: chỉ chủ report xem; không lộ ticket gốc/chat/ảnh riêng; lỗi 404 đúng. File: policy, controller/DTO, routes, test (4–5). Verify: SupportProgressTest + hợp đồng Swagger.
- [ ] M2-07 — Nút hỗ trợ trực tiếp trên web. Module support-clients; FR-007/014/018, TC-011/018/022. Phụ thuộc M1b/M2-03/04/06. AC: luôn có từ đầu, tách quota chat; trạng thái tạo phiếu/thông báo thật; retry cùng key. File: view, JS, test (3–4). Verify: browser và feature test; chưa bật model.
- [ ] Checkpoint M2 — Bàn giao đầy đủ không cần model; report/quyền/notification đều có evidence. Không đánh dấu nhận thông báo là quản trị đã tiếp nhận.

## M3 — Một tình huống qua guide, command và provider fake

- [ ] M3-01 — Schema guide và phiên bản. Module guide-catalog; FR-005/013, UC-10, BR-37/38/39, TC-008/009. Phụ thuộc M2. AC: draft/in_review/published/withdrawn, từ chối về draft; metadata/precondition và snapshot version; không seed hướng dẫn thật chưa duyệt. File: migration, models, test (4–5). Verify: SupportGuideCatalogTest.
- [ ] M3-02 — Backend eligibility và một bộ câu hỏi/bước giả lập. Module guide-catalog; FR-004/005, NFR-002, TC-007/008. Phụ thuộc M3-01. AC: lọc đúng thiết bị/model/điều kiện; thiếu facts không phát bước; nội dung cố định. File: eligibility service, fixture/test (3–4). Verify: SupportGuideEligibilityTest.
- [ ] M3-03 — Mở rộng command M2 cho lượt AI. Module support-session; NFR-003/004, UC-02/12, TC-006/025. Phụ thuộc M2-08/M2-03. AC: không tạo kho command cạnh tranh; thêm unique tin/purpose, version/deadline, lease và dispatch intent bền vững; transaction ngắn, kết quả cũ không áp dụng; không hồi sinh AI hết deadline. File: migration bổ sung, model/service/job, test; tách trước nếu vượt 5. Verify: SupportCommandTest.
- [ ] M3-04 — Gateway và validator quyết định AI. Module ai-gateway; FR-004/005/017, NFR-002, TC-021/024. Phụ thuộc M3-02/03. AC: đúng schema/field/action, quyền không do model cấp; lỗi output chuyển fallback; tests chỉ fake. File: AiClient, validator, config, test (4–5). Verify: SupportAiContractTest bằng schema trong docs; không tự chọn provider mới hoặc gọi thật.
- [ ] M3-05 — API tin/turn/command và kết quả bước. Module support-orchestration; FR-002/004/006, TC-003/007/010/030. Phụ thuộc M3-03/04. AC: lưu trước xử lý; poll có policy; bước thuộc đúng phiên; Swagger khớp. Tách task con nếu vượt 5 file nguồn; khóa contract trước sửa. Verify: SupportTurnApiTest + Swagger.
- [ ] M3-06 — Một luồng máy chiếu end-to-end với fake. Module support-clients; FR-004/005/006/014, TC-007/008/010/018. Phụ thuộc M3-05/M2-07. AC: mỗi lượt một bước; chờ kết quả; thất bại chuyển phiếu; chưa mở AI thật. File: web JS/view, integration test (3–4). Verify: browser và feature test.
- [ ] Checkpoint M3 — Có vertical slice chạy bằng fake; không bật cho giảng viên thật khi thiếu M4, guide được duyệt và cấu hình vận hành.

## M4 — Safety, quota, chịu lỗi và quay lại

- [ ] M4-01 — Guard thu hồi guide và hành động sau chuyển cấp. Module support-orchestration; FR-005/007, NFR-002/003, TC-008/009/024/025. Phụ thuộc M3. AC: kiểm tra lại published/version; không hướng dẫn nguy hiểm; stale result discarded. File: eligibility/orchestrator, tests (3–4). Verify: SupportSafetyTest.
- [ ] M4-02 — Quota/reservation dùng chung. Module ai-gateway; NFR-005, TC-027. Phụ thuộc M3-04. AC: cùng worker/store, quota đếm retry, unknown cost không giải phóng mù; bàn giao vẫn dùng. File: quota service, config, migration/store nếu cần, test (4–5). Verify: SupportQuotaTest; chưa tuyên bố hard cap provider nếu chưa xác nhận.
- [ ] M4-03 — Deadline, circuit breaker và fallback. Module ai-gateway; FR-017, NFR-004, TC-021/026. Phụ thuộc M4-02/M2. AC: hết thời gian không spinner vô hạn; retry hữu hạn; circuit chỉ ngắt AI; phiếu/trạng thái thật. File: gateway/job, circuit service, config/test (4–5). Verify: SupportAiResilienceTest và fake clock.
- [ ] M4-04 — Hoàn tất, mở lại và follow-up. Module support-session/ticket-handoff; FR-012, TC-017. Phụ thuộc M2/M3-03. AC: người dùng xác nhận; sau 10 phút vẫn báo tiếp được; không xóa ảnh hoặc reopen phiếu trái quyền. File: session command/controller, request, tests (4–5). Verify: SupportResolutionTest; cập nhật OpenAPI trong task nhỏ kế tiếp nếu cần.
- [ ] M4-05 — UI trạng thái chậm/lỗi/hoàn tác. Module support-clients; FR-014/017, TC-017/018/021/025. Phụ thuộc M4-01/03/04. AC: deadline/state đồng bộ; Escape Hatch còn hoạt động; screen reader/focus; form cũ không hỏng. File: view, JS, styles/test (4–5). Verify: browser lỗi mạng/quota/stale response.
- [ ] Checkpoint M4 — Safety và fallback đạt test; Q-01/02/03 phải chốt trước bất kỳ pilot AI thật.

## M5 — Quản trị và KTV

- [ ] M5-01 — Acknowledgement và phân quyền bàn giao. Module ticket-handoff; FR-011, NFR-001, TC-015/023. Phụ thuộc M2. AC: lệnh riêng; phân công ghi nhận tiếp nhận; đổi KTV thu hồi quyền; không cấp xem mọi chat. File: handoff policy/service/controller/test (4–5). Verify: SupportAcknowledgementTest.
- [ ] M5-02 — Deadline và nhắc người trực. Module support-delivery; FR-016, TC-016/026. Phụ thuộc M5-01, Q-04 khi dùng thực. AC: phân biệt tiếp nhận/phân công; ngoài giờ hiển thị đúng; nhắc không trùng. File: scheduler/job/config/test (4–5). Verify: SupportEscalationScheduleTest với fake clock.
- [ ] M5-03 — UI quản trị/KTV xem report và facts gốc. Module support-clients; FR-008/011, TC-012/015/023. Phụ thuộc M5-01. AC: tổng hợp có nhãn; dữ liệu gốc không mất; không đổi quy trình đóng phiếu hiện có. File: views/controller presenter/test (3–5). Verify: feature + browser role matrix.
- [ ] M5-04 — API/UI duyệt và thu hồi hướng dẫn. Module guide-catalog; FR-013, TC-009/023/030. Phụ thuộc M3-01/M4-01. AC: draft khác published; có người duyệt; published bất biến; audit và withdrawal có lý do. Tách API và UI thành hai task con trước khi làm nếu vượt 5 file. Verify: SupportGuideAdministrationTest và browser; không publish nội dung mẫu như thật.

## M6 — Flutter Android

- [ ] M6-01 — Khởi tạo app, auth và API client. Module support-clients; FR-015, NFR-001, TC-019/023/030. Phụ thuộc hợp đồng API ổn định. AC: dùng Sanctum; token bảo vệ theo nền tảng; base URL thật cấu hình; không key AI. Chia task con trước nếu cần >5 file. Verify: flutter analyze/test và login staging.
- [ ] M6-02 — QR, tạo/xem phiên và retry. Module support-clients; FR-001/002/003/015, TC-004/019/022. Phụ thuộc M6-01. AC: QR không cấp quyền; dùng cùng DTO/key; không báo gửi thành công khi chưa nhận. Verify: widget/integration và thiết bị thật, lưu evidence.
- [ ] M6-03 — Hội thoại theo bước và chuyển trực tiếp. Module support-clients; FR-004/006/007/015, TC-010/011/019/025. Phụ thuộc M6-02/M4. AC: action theo permitted_actions; cùng state web; lỗi mạng không lặp side effect. Verify: integration web+app đồng thời.
- [ ] M6-04 — Attachment private và progress. Module support-clients; FR-010/015, NFR-001/006, TC-014/019/023/028. Phụ thuộc API attachment được đặc tả và triển khai ở task con trước UI. AC: MIME/size/private/policy; không lộ ảnh người khác. Verify: backend feature, flutter test và thiết bị.
- [ ] M6-05 — Push và dedup. Module support-delivery/clients; FR-015/016, TC-020/026. Phụ thuộc M6-03, Q-06 cho dịch vụ thật. AC: đúng actor, event_id chống lặp; preview tối thiểu; không coi push delivered là acknowledged. Verify: fake sender rồi staging với thiết bị được phép test.

## M7 — Nội dung và chất lượng

- [ ] M7-01 — Khoảng 10 tình huống được chuyên môn duyệt. Module guide-catalog; FR-005/013/018, TC-008/009/022. Phụ thuộc Q-03/M5-04. AC: nguồn, precondition, chống chỉ dẫn nguy hiểm, model metadata; không tự publish. Verify: review nội dung và tests eligibility.
- [ ] M7-02 — Bộ eval ít nhất 100 ca và rubric. Module support-operations; NFR-010, TC-032. Phụ thuộc M7-01. AC: đa lượt, độc lập với tập chỉnh prompt, nhóm an toàn/injection/failure riêng; có đáp án. Verify: reviewer duyệt và kiểm tra coverage requirement.
- [ ] M7-03 — So sánh/cấu hình model bằng eval. Module ai-gateway; NFR-010, TC-032. Phụ thuộc Q-01/02/M7-02. AC: cùng điều kiện, ghi version/usage/latency, không bật thật nếu safety fail. Verify: chạy API thật trong phạm vi ngân sách được giao, báo kết quả theo nhóm.

## M8 — Kiểm chứng vận hành và phát hành

- [ ] M8-01 — MySQL concurrency và tải. Module support-operations; NFR-003/007, TC-006/025/027/029. Phụ thuộc M2–M6, Q-06. AC: phiếu/command không trùng, quota phân tán, số đo p95 rõ; database test riêng. Verify: báo cáo tải và lỗi, không dùng SQLite thay bằng chứng này.
- [ ] M8-02 — Retention, riêng tư, backup và restore. Module support-operations; NFR-006/009, TC-028/031. Phụ thuộc Q-05/06. AC: policy được duyệt, snapshot/key không phá retry, thử restore có evidence. Chia task dữ liệu và diễn tập restore trước khi làm. Verify: staging có dữ liệu giả/đã được phép dùng.
- [ ] M8-03 — Feature flag, monitoring và runbook. Module support-operations; NFR-004/009, TC-021/026/031. Phụ thuộc M4/M5. AC: AI off vẫn báo trực tiếp; worker/scheduler/cost/deadline có owner; rollback không drop dữ liệu. Verify: fault injection staging.
- [ ] M8-04 — Pilot có người trực và nghiệm thu. Module support-operations; mọi FR/NFR trong scope. Phụ thuộc các gate và Q liên quan đã giải quyết. AC: checklist 06/07, lỗi high/critical xử lý, ngân sách và app bản phát hành xác định. Verify: UAT/pilot report, không tuyên bố production từ test local.
- [ ] Checkpoint sản phẩm — Chỉ RELEASED khi có version/môi trường/người xác nhận, không chỉ đánh dấu checkbox code.

Các mã FR/NFR viết ngắn trong task tương ứng tiền tố AI- ở tài liệu 01, ví dụ FR-007 = AI-FR-007. Evidence phải ghi đủ ID để truy vết tự động.

Baseline tài liệu 1.1: đọc [use case](<../docs/tài liệu AI/10_USE_CASE_CHI_TIET.md>), [quy tắc](<../docs/tài liệu AI/11_QUY_TAC_NGHIEP_VU.md>), [quyền](<../docs/tài liệu AI/12_MA_TRAN_PHAN_QUYEN.md>) và [sơ đồ](<../docs/tài liệu AI/so-do/README.md>) theo task. M2-05 chỉ auto-match đúng một ticket active có cùng thiết bị và symptom_code được xác nhận. M5-01 không auto-ack report mới chỉ vì ticket đã assigned. M5-04 cần in_review và lý do từ chối/thu hồi. M8-04 dùng UAT ở tài liệu 15 và gate ở 16; Q-07 chặn thay policy ưu tiên/SLA, không chặn M1b.

## Vấn đề nền cần xử lý trước release

- [x] BASE-001 — Điều tra và sửa lỗi UiPerformanceTest::test_incident_summary_does_not_repeat_the_pending_count_query. Đã xác định 2 call path: UiController::show() tính pendingTicketCount và addPageData() tính lại lần 2. Sửa bằng cách tái sử dụng pendingTicketCount đã tính. Verify: UiPerformanceTest đạt 5/5 passed, toàn suite 142 passed, 0 failed.
