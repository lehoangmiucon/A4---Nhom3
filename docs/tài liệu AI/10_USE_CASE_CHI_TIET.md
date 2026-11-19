# 10 — Đặc tả use case

> Baseline AI-SDLC-1.1. Các luồng là TARGET, trừ nhánh M1a được ghi CURRENT_CODE. Chưa phải bằng chứng đã triển khai.
> Luật và thứ tự ưu tiên: [11](11_QUY_TAC_NGHIEP_VU.md). Quyền: [12](12_MA_TRAN_PHAN_QUYEN.md). API: [03](03_DU_LIEU_VA_API.md).

## 1. Quy ước dùng chung

Actor chính là người hoặc dịch vụ khởi tạo use case; provider AI là dịch vụ ngoài, không có quyền nghiệp vụ. Người dùng phải xác thực và tài khoản hợp lệ, trừ màn hình chọn phòng/thông tin liên hệ trước phiên.

“Đã lưu” nghĩa transaction commit. “Đã gửi thông báo” không đồng nghĩa đã tiếp nhận. Các mã event trong mục này là thiết kế audit TARGET, không khẳng định M1a đã có những event đó.

Các use case thay đổi dữ liệu tuân thủ idempotency, version và lỗi tại tài liệu 03. Thông tin trả cho người dùng không chứa dữ liệu người khác.

## 2. Danh mục truy vết

| UC | Tên | FR/NFR chính | TC |
| --- | --- | --- | --- |
| UC-01 | Tạo và đọc lại phiên | AI-FR-001/002/003 | TC-001–006 |
| UC-02 | Làm rõ sự cố | AI-FR-004 | TC-007/022 |
| UC-03 | Hướng dẫn và nhận kết quả | AI-FR-005/006 | TC-008–010 |
| UC-04 | Chuyển hỗ trợ trực tiếp | AI-FR-007/008/017/018 | TC-011/012/021/022 |
| UC-05 | Liên kết sự cố đã báo | AI-FR-009/010 | TC-013/014 |
| UC-06 | Xác nhận hoàn tất và quay lại | AI-FR-012 | TC-017 |
| UC-07 | Tiếp nhận và phân công | AI-FR-011/016 | TC-015/016 |
| UC-08 | KTV xử lý và quản trị đóng phiếu | AI-FR-011/012 | TC-015/017/023 |
| UC-09 | Bổ sung và báo tái diễn | AI-FR-010/012 | TC-014/017 |
| UC-10 | Quản lý hướng dẫn | AI-FR-013 | TC-008/009/023 |
| UC-11 | Theo dõi và thông báo | AI-FR-010/014/015 | TC-014/018–020 |
| UC-12 | Xử lý AI lỗi/quota/mất mạng | AI-FR-017, AI-NFR-004/005 | TC-021/026/027 |
| UC-13 | Sử dụng qua Flutter | AI-FR-015 | TC-019/020/023 |
| UC-14 | Giám sát và xử lý vận hành | AI-NFR-006/007/009/010 | TC-028–032 |

Trong bảng, danh sách rút gọn AI-FR-001/002/003 tương ứng ba ID đầy đủ tại 01. Khi ghi evidence phải dùng đầy đủ từng ID.

## UC-01 — Tạo và đọc lại phiên

- Actor: chủ phiên student/teacher.
- Trigger: chọn phòng và gửi mô tả hoặc mở lại một phiên trong danh sách.
- Tiền điều kiện: email đã xác thực, active; phòng tồn tại; thiết bị nếu chọn thuộc phòng; mô tả hợp lệ.
- CURRENT_CODE: API tạo/list/detail/messages M1a. TARGET: giao diện thao tác và tiếp tục hội thoại.
- Luồng chính: (1) xác nhận phòng; (2) nhập mô tả; (3) client tạo key cho intent; (4) backend kiểm tra quyền/input; (5) khóa theo actor, tìm replay; (6) ghi phiên, tin đầu và kết quả idempotency; (7) commit; (8) trả 201; (9) đọc lại bằng API lịch sử.
- Nhánh A: key/payload trùng → replay snapshot, không thêm phiên/tin.
- Nhánh B: key khác payload → 422; key thiếu/sai định dạng → 422.
- Nhánh C: phòng/thiết bị bị xóa sau tạo → đọc nhãn snapshot; không giả thiết bị vẫn tồn tại.
- Ngoại lệ: lưu tin lỗi → rollback toàn bộ; chưa nhận response → client giữ key và ghi chưa xác nhận.
- Hậu điều kiện thành công: một phiên collecting/version=1 và một tin đầu; chưa có phiếu hoặc gọi AI.
- Bằng chứng: SupportSessionTest hiện có; UI cần TC-004/018. Event TARGET: support.session_created.

## UC-02 — Làm rõ sự cố

- Actor: chủ phiên; actor phụ provider qua gateway.
- Trigger: chọn tiếp tục hỗ trợ hoặc trả lời câu hỏi.
- Tiền điều kiện: collecting, phòng/phiên hợp lệ, chưa hết quota/deadline, không có yêu cầu chuyển người thật hoặc dấu hiệu dừng.
- Luồng chính: lưu tin/lệnh; xác định facts còn thiếu; backend tạo tập câu hỏi; model chọn mã; kiểm tra schema/state; gửi một câu hỏi đã duyệt; lưu lịch sử.
- Nhánh A: facts đã đủ → xét bước hợp lệ, không hỏi thừa.
- Nhánh B: “không biết/không thể” hoặc hết giới hạn làm rõ → UC-04.
- Nhánh C: không có mã câu hỏi hợp lệ → fallback xác định; không để AI tự bịa quy trình.
- Ngoại lệ: model lỗi/đến muộn → UC-12; phiên đổi version → bỏ kết quả cũ.
- Hậu điều kiện: facts/câu hỏi được lưu; không có bước thao tác chưa đủ điều kiện.
- Bằng chứng: TC-007/021/025. Event TARGET: support.question_presented.

## UC-03 — Hướng dẫn và nhận kết quả

- Actor: chủ phiên.
- Trigger: đủ facts để chọn bước, hoặc người dùng trả lời một bước đang chờ.
- Tiền điều kiện khi phát bước: guide published, metadata và precondition hợp lệ; không có thao tác nguy hiểm hoặc bước khác đang chờ. Khi nhận kết quả: phải có đúng attempt đang chờ của phiên và version hợp lệ.
- Luồng chính: backend lọc ứng viên → model chọn guide_step_id → kiểm tra lại → tạo attempt/snapshot → hiện thẻ nội dung cố định → awaiting_result → người dùng chọn kết quả → ghi kết quả một lần.
- Nhánh resolved: người dùng xác nhận rõ đã hoạt động → UC-06.
- Nhánh not_fixed: còn bước hợp lệ và giới hạn → về collecting; hết bước → UC-04.
- Nhánh cannot_perform: chuyển UC-04, không ép làm lại.
- Nhánh “không chắc”: không tự ánh xạ resolved; cho chọn chưa được hoặc gặp hỗ trợ.
- Ngoại lệ: attempt không thuộc phiên/đã có kết quả khác → lỗi theo contract; guide thu hồi trước phát → từ chối; kết quả cũ → discarded.
- Hậu điều kiện: biết chính xác nội dung đã phát và kết quả do người dùng xác nhận.
- Bằng chứng: TC-008–010/025. Event TARGET: support.step_presented, support.step_result_recorded.

## UC-04 — Chuyển hỗ trợ trực tiếp

- Actor: chủ phiên hoặc backend khi AI không thể hỗ trợ.
- Trigger: nút hỗ trợ, không thể thao tác, nguy hiểm, ngoài phạm vi, hết giới hạn hoặc provider lỗi.
- Tiền điều kiện: actor hợp lệ; phòng đã xác nhận và mô tả gốc có trên server. Không cần model/quota chat.
- Luồng chính: kiểm tra idempotency/state → ghi nhận ý định chuyển cấp và vô hiệu lượt AI liên quan → dùng facts/snapshot để lập báo cáo → xét UC-05 → commit report/ticket/log/outbox → handed_off → trả mã theo dõi sau commit.
- Nhánh A: tóm tắt AI không có/không đáng tin → mẫu xác định từ facts; không gọi thêm model bắt buộc.
- Nhánh B: phòng đã bị xóa → xác nhận phòng còn hoạt động và tạo phiên mới theo UC-01 với mô tả người dùng xác nhận; không sửa ngầm context phiên cũ. Chỉ thiết bị bị xóa nhưng phòng còn hợp lệ → thiết bị chưa xác định, không auto-merge; không ép tự sửa.
- Nhánh C: report đã có → trả liên kết/kết quả tương ứng; không thêm phiếu.
- Nhánh D: version xung đột → tải lại, giữ intent/key và giải thích trạng thái; không tự ghi đè. Replay thành công được xét trước guard version.
- Ngoại lệ: DB lỗi → không nói đã tạo phiếu; receipt đã lưu thì hiện pending/retry, chưa lưu thì chưa xác nhận.
- Hậu điều kiện thành công: một report của phiên, một ticket chính liên kết, event thông báo bền vững. Chưa coi quản trị đã tiếp nhận.
- Bằng chứng: TC-006/011/012/021/025/026. Event TARGET: support.handoff_requested, support.report_committed.

Chi tiết hai giao dịch nhận intent và bàn giao cuối nằm ở 11; không gọi mạng trong hai giao dịch đó.

## UC-05 — Liên kết sự cố đã báo

- Actor: backend; quản trị khi kết quả không rõ.
- Trigger: bàn giao có thiết bị xác định.
- Tiền điều kiện: dữ liệu thiết bị hợp lệ, khóa thiết bị được áp dụng cho tất cả luồng liên quan.
- Luồng chính: tìm phiếu active của đúng thiết bị → đối chiếu mã triệu chứng đã xác nhận → nếu đúng một ứng viên đủ điều kiện thì liên kết → lưu report riêng → trả tiến độ qua quyền report.
- Nhánh A: không có ứng viên hoặc triệu chứng khác → tạo phiếu mới.
- Nhánh B: không biết thiết bị/triệu chứng; nhiều ứng viên; nghi tái diễn → không auto-merge, đưa quản trị đối chiếu.
- Nhánh C: chỉ có phiếu resolved/closed → không gộp như đang xử lý; UC-09.
- Ngoại lệ: conflict/retry → kiểm tra report đã tạo trước; không tạo tác dụng phụ thứ hai.
- Hậu điều kiện: không mất lượt báo; không lộ người báo trước; không dùng đồng nhất phòng làm bằng chứng cùng lỗi.
- Bằng chứng: TC-006/013/014. Event TARGET: support.report_linked hoặc support.merge_review_required.

## UC-06 — Hoàn tất và quay lại

- Actor: chủ phiên.
- Trigger: xác nhận đã được hoặc chọn sự cố vẫn còn.
- Tiền điều kiện: chỉ chủ phiên; lệnh có expected_version, không đóng ticket bằng lệnh session.
- Luồng chính: xác nhận → session resolved → lưu kết quả → hiện cách quay lại; nếu chưa có report thì mở lại session bằng lệnh rõ ràng, giữ history/counters.
- Nhánh A: quá 10 phút → vẫn báo tiếp được; chỉ hết tiện ích hoàn tác nhanh.
- Nhánh B: đã handed_off → UC-09, không mở lại pipeline hướng dẫn của phiên.
- Ngoại lệ: AI tự đề xuất đã xong nhưng chưa có xác nhận → chỉ hiện câu hỏi xác nhận, không resolved.
- Hậu điều kiện: không mất history, phân công hay ảnh phiếu.
- Bằng chứng: TC-017/025. Event TARGET: support.session_resolved, support.session_reopened.

## UC-07 — Tiếp nhận và phân công

- Actor: quản trị; actor phụ scheduler.
- Trigger: report mới/chờ tiếp nhận hoặc đến hạn nhắc.
- Tiền điều kiện: quyền xem report và quản trị phiếu; KTV active có chuyên môn phù hợp.
- Luồng chính: xem facts và trạng thái → xác nhận tiếp nhận → chọn KTV/lịch/ghi chú theo nghiệp vụ cũ → commit phân công → thông báo.
- Nhánh A: phân công ngay → thao tác đó ghi nhận tiếp nhận các report được nêu trong hành động, không tự đánh dấu mọi report tương lai.
- Nhánh B: chưa có KTV phù hợp → giữ hàng đợi đã tiếp nhận/chưa phân công và cảnh báo deadline, không chọn người bằng model.
- Nhánh C: đổi KTV khi lịch đã được xác nhận → theo quy trình hủy/đổi lịch hiện tại, không vượt điều kiện cũ.
- Nhánh D: quá hạn/ngoài giờ → áp dụng lịch trực và người thay thế đã cấu hình.
- Hậu điều kiện: acknowledgement có actor/time; assignment và log theo ticket; không đồng nhất hai mốc.
- Bằng chứng: TC-015/016/023. Event TARGET: support.report_acknowledged; giữ event phiếu hiện có khi phù hợp.

## UC-08 — KTV xử lý và quản trị đóng phiếu

- Actor: KTV đang được giao; quản trị.
- Trigger: KTV xác nhận lịch, cập nhật tiến độ/kết quả.
- Tiền điều kiện: đúng assignment, lịch và quyền hiện có.
- Luồng chính: KTV xem context cần thiết → xác nhận lịch → in_progress → cập nhật resolution_note/minh chứng → resolved → quản trị kiểm tra điều kiện → closed.
- Nhánh A: chưa có minh chứng/lịch xác nhận theo quy tắc cũ → không đóng.
- Nhánh B: thay assignment → KTV cũ mất quyền context nhạy cảm theo policy.
- Nhánh C: quản trị yêu cầu xử lý lại → dùng quy trình ticket reopen, không dùng session reopening.
- Hậu điều kiện: facts thực tế của KTV tách khỏi phỏng đoán AI; report theo dõi tiến độ.
- Bằng chứng: TC-015/017/023/030 và test ticket hiện có.
- Lưu ý: code reopen hiện có xóa minh chứng cũ; module AI không được tự gọi thay người có quyền hoặc coi đó là cơ chế giữ lịch sử.

## UC-09 — Bổ sung và báo tái diễn

- Actor: chủ report; quản trị xác định xử lý.
- Trigger: sự cố vẫn còn, mô tả mới hoặc lỗi xuất hiện lại.
- Tiền điều kiện: report thuộc actor; payload không tự đổi trạng thái ticket.
- Luồng chính: lưu follow-up idempotent → phân biệt thông tin bổ sung với báo tái diễn → thông báo người phụ trách → quản trị quyết định giữ ticket, reopen theo quyền hoặc tạo ticket mới liên kết lịch sử.
- Nhánh A: ticket active và vẫn cùng sự cố → thêm follow-up.
- Nhánh B: ticket resolved/closed → tạo yêu cầu xem xét xử lý lại, không tự reopen.
- Nhánh C: thiết bị/lỗi khác → phiên/report mới; chỉ gắn recurrence khi có căn cứ.
- Hậu điều kiện: mọi thông tin được lưu, không sửa ngược lịch sử lần trước.
- Bằng chứng: TC-014/017/026. Event TARGET: support.follow_up_created, support.recurrence_review_requested.

## UC-10 — Quản lý hướng dẫn

- Actor: người soạn/người duyệt được phân công.
- Trigger: thêm, sửa, phát hành hoặc thu hồi cẩm nang.
- Tiền điều kiện: policy theo thao tác; nội dung và nguồn thiết bị có căn cứ.
- Luồng chính: draft → kiểm tra precondition/rủi ro → review → published với actor/time → dùng version ID bất biến.
- Nhánh A: nội dung sai → trả về draft với lý do.
- Nhánh B: sửa published → tạo version mới.
- Nhánh C: phát hiện nguy hiểm → withdrawn ngay theo quyền; lượt đang chạy phải kiểm tra lại trước phát.
- Hậu điều kiện: biết ai đã duyệt bản nào; dữ liệu từ chat/KTV không tự trở thành published.
- Bằng chứng: TC-008/009/023/024. Event TARGET: guide.version_published/withdrawn.

## UC-11 — Theo dõi và thông báo

- Actor: chủ report, quản trị/KTV theo quyền.
- Trigger: xem tiến độ, đọc notification hoặc nhận push.
- Tiền điều kiện: đúng đối tượng; không dùng event_id/URL như quyền truy cập.
- Luồng chính: API trả progress tối thiểu; client cập nhật trạng thái từ server; notification/push chỉ báo có cập nhật.
- Nhánh A: push lặp → loại trùng event_id.
- Nhánh B: report mới liên kết ticket đã assigned → hiển thị assignment của ticket và acknowledgement của report riêng, không giả report mới đã được xem.
- Nhánh C: push lỗi → polling/list vẫn đọc DB; không làm mất phiếu.
- Hậu điều kiện: đúng người thấy đúng thông tin, không auto-ack chỉ vì đọc push.
- Bằng chứng: TC-014/018–020/023/026.

## UC-12 — AI lỗi, quota hoặc mất mạng

- Actor: hệ thống; chủ phiên.
- Trigger: deadline, provider/circuit/quota, output sai hoặc client không nhận response.
- Tiền điều kiện: yêu cầu đã/chưa được server lưu phải được phân biệt.
- Luồng chính: hiển thị trạng thái thật → ngừng retry không hợp lệ → dùng dữ liệu đã lưu để bàn giao nếu đủ → cập nhật usage/reservation và log sạch.
- Nhánh A: chưa có server acknowledgement → retry cùng key; không nói đã tạo.
- Nhánh B: receipt đã lưu nhưng final transaction lỗi → pending và retry hữu hạn; không tiếp tục hướng dẫn.
- Nhánh C: thiếu phòng → thu thập vị trí/contact đã cấu hình, không đoán.
- Hậu điều kiện: không spinner vô hạn, không nói bảo trì khi chỉ biết timeout; công việc đã commit không mất.
- Bằng chứng: TC-021/025–028.

## UC-13 — Sử dụng qua Flutter

- Actor: giảng viên trên Android.
- Trigger: đăng nhập, QR, tạo/tiếp tục phiên, xem tiến độ và nhận push.
- Tiền điều kiện: backend/API đã triển khai tương ứng; URL server không phải localhost của điện thoại.
- Luồng chính: auth/token bảo vệ → QR chỉ xác định phòng → cùng DTO/idempotency/state với web → đọc lại dữ liệu server.
- Nhánh A: app nền/bị đóng → mở lại từ server, không phát lại side effect bằng key mới.
- Nhánh B: logout/đổi tài khoản → xóa state/token cũ và hủy subscription phù hợp.
- Nhánh C: không có mạng → ghi chưa gửi/chưa xác nhận, cho retry; không giả offline agent.
- Hậu điều kiện: không lộ key model hoặc chat qua màn khóa; không thêm backend thứ hai cho app.
- Bằng chứng: TC-004/019/020/023/030.

## UC-14 — Giám sát và xử lý vận hành

- Actor: vận hành và chủ quản dữ liệu theo quyền.
- Trigger: cảnh báo lỗi/chi phí/queue/quá hạn, lịch retention hoặc phát hành.
- Tiền điều kiện: owner, ngân sách, policy và môi trường đã xác nhận.
- Luồng chính: xem metadata → xác định phạm vi → tắt feature/khôi phục worker/thu hồi guide theo runbook → xác minh trạng thái dữ liệu → ghi evidence.
- Nhánh A: cần raw data để điều tra → quyền truy cập có lý do/thời hạn; không cấp mặc định.
- Nhánh B: restore → môi trường riêng, kiểm tra RPO/RTO và tính toàn vẹn.
- Nhánh C: thiếu quyết định Q → không mở chức năng thật liên quan, vẫn làm phần độc lập.
- Hậu điều kiện: có người chịu trách nhiệm, kết quả kiểm chứng và lịch sử thay đổi.
- Bằng chứng: TC-028–032; không coi log chưa ai xử lý là hoàn thành vận hành.
