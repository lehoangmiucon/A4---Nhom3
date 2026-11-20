# 12 — Ma trận quyền và phạm vi dữ liệu

> Các cột TARGET không mô tả quyền đã có. CURRENT_CODE M1a/CR-01–13: customer student/teacher active/verified chỉ đọc/ghi phiên, command, report qua phiên của mình; CR-11 xóa theo điều kiện 03. KTV/admin đọc phần bàn giao trong mô tả ticket theo quyền ticket hiện có, không được cấp GET raw session theo role.
> Các tên capability là hợp đồng logic, không khẳng định repository đã cài hệ thống RBAC/capability mới.

## 1. Nguyên tắc

Identity từ session/Sanctum. Token có abilities=* không đồng nghĩa được vượt policy. Room QR, session ID, event ID, report ID và status_url không phải bằng chứng quyền.

Mọi đọc/ghi phải kiểm tra actor, tài khoản, role, quan hệ đối tượng và trạng thái phù hợp. Backend không nhận user_id hoặc role từ model/client để quyết định.

## 2. Ma trận TARGET

CURRENT_CODE CR-11: xóa phiên qua DELETE support-sessions chỉ dành cho chủ phiên student/teacher active, verified. Backend kiểm tra phiên handed_off có report và ticket closed tại lúc xóa; UI can_delete không cấp quyền. Customer khác kể cả với phiên đã xóa nhận 404; admin/staff không dùng endpoint này để xóa thay chủ. Xóa mềm không thay quyền hoặc dữ liệu phiếu/report.

C = chủ phiên/report customer active, đã xác thực. O = customer khác. A = quản trị phụ trách. K = KTV đang được giao ticket liên quan. G = người được phân công duyệt guide. V = vận hành. Một người có thể giữ nhiều trách nhiệm; quyền không tự lan sang chức năng khác.

| Hành động/tài nguyên | C | O | A | K | G | V |
| --- | --- | --- | --- | --- | --- | --- |
| Tạo phiên | Có | Tạo phiên riêng | Không qua endpoint customer | Không qua endpoint customer | Theo vai trò customer nếu có | Không mặc định |
| List/detail/messages trước bàn giao | Chỉ của mình | Không | Không theo role mặc định | Không | Không | Không |
| Tin/turn/result/resolve/cancel | Chỉ của mình, đúng state | Không | Không giả danh customer | Không | Không | Không |
| Yêu cầu chuyển trực tiếp | Chỉ của mình | Không | Tiếp nhận qua nghiệp vụ riêng | Không giả danh | Không | Không |
| Facts report đã bàn giao | Report của mình | Không | Khi phụ trách triage | Khi được giao và cần xử lý | Không | Không mặc định |
| Raw chat để điều tra xử lý | Của mình | Không | Quyền context riêng + lý do/audit | Quyền context riêng + đúng assignment + lý do/audit | Không | Không mặc định |
| Progress phiếu chung qua report | Tối thiểu qua report mình | Không nếu chưa có report riêng | Theo quyền ticket | Theo quyền ticket | Không | Chỉ metadata vận hành |
| Ảnh support riêng | Ảnh trong session/report mình | Không | Khi có quyền xử lý report | Đúng assignment/quyền context | Không | Không mặc định |
| Acknowledge report/phân công/đóng/reopen ticket | Không | Không | Theo quy trình tương ứng | Không | Không | Không |
| Xác nhận lịch/cập nhật kết quả ticket | Không | Không | Theo quyền ticket hiện có | Đúng assignment/lịch/điều kiện hiện có | Không | Không |
| Follow-up/reopen request | Report mình | Không | Xử lý đề nghị | Ghi kết quả theo quyền ticket | Không | Không |
| Soạn guide | Không mặc định | Không | Khi được giao | Khi được giao | Khi được giao | Không |
| Publish/withdraw guide | Không | Không | Khi được giao duyệt | Không tự có từ role staff | Có theo phạm vi | Chỉ tắt feature nếu quyền vận hành |
| Metrics/failed queue/budget | Không | Không | Theo trách nhiệm | Không mặc định | Guide metrics được cấp | Có, không raw chat mặc định |
| Retention/backup/restore | Không | Không | Theo chính sách được giao | Không | Không | Theo runbook và quyền hạ tầng |

Không dùng endpoint detail ticket cũ để phục vụ người báo sau nếu endpoint đó trả dữ liệu vượt Progress DTO.

## 3. Các kiểm tra bắt buộc theo resource

| Resource | Điều kiện |
| --- | --- |
| Session | user_id=actor cho customer; role/active/email; đối tượng tồn tại |
| Message/attempt | Trước hết kiểm tra parent session; sau đó đúng message/attempt thuộc parent |
| Command/status_url | Endpoint customer chỉ đúng parent/chủ phiên; quyền report không tự cấp raw command; vận hành xem metadata qua giao diện riêng có policy |
| Report/progress | Report qua session thuộc actor; chỉ serializer tối thiểu; không include ticket.relations toàn bộ |
| Attachment | Đúng parent và loại dữ liệu; file private; không token URL/public path |
| Handoff | Admin phụ trách; policy riêng; acknowledgement có actor/time |
| Guide version | Quyền soạn khác quyền publish; version thuộc guide; state hợp lệ |
| Outbox/delivery | Worker/service identity; không nhận recipient tùy ý từ customer/model |

ID của người khác và ID không tồn tại cùng trả 404 ở endpoint object customer. Sai role cho một chức năng chung trả 403. Không phản hồi lỗi có dữ liệu tồn tại của người khác. M1a nhóm admin/staff bị chặn role từ trước policy, đúng hiện trạng.

## 4. Thu hồi quyền và dữ liệu trên client

- Khóa tài khoản chặn các request tiếp; đăng xuất xóa state/token/subscription tương ứng trên client.
- Chuyển assignment làm KTV cũ mất quyền nội dung xử lý nhạy cảm; audit về các thao tác cũ được giữ.
- Push tới thiết bị đã đăng xuất không được chứa nội dung chat, deep link phải xác thực lại.
- Link ảnh và status_url đều được kiểm tra quyền ở request mới; không sinh URL public vĩnh viễn.
- Không cache raw chat dùng chung tài khoản hoặc trong service worker không có chính sách.
- Chặn truy cập không được chỉ ẩn nút trên UI.

## 5. Audit truy cập nhạy cảm

TARGET context access cần event actor_id, resource_id, purpose/reason, thời điểm và kết quả. Không tự ghi raw chat vào event. Xem metrics không cấp quyền đọc payload; “cần debug” không tự biến người vận hành thành admin nghiệp vụ.

Nếu hệ thống chưa có capability store, triển khai policy hẹp theo cấu hình người được phân công trong task phù hợp; không đưa gói RBAC mới vào chỉ để làm một test xanh.

## 6. Test bắt buộc

TC-023 phải chạy cho session, message, attempt, command, attachment, report và guide API liên quan. Tối thiểu: đổi path ID, thay parent ID, ID hợp lệ của actor khác, role sai, tài khoản khóa, đổi KTV, đổi tài khoản trên cùng thiết bị và push deep link.

CURRENT_CODE đã kiểm chứng session/messages, conversation/command parent-scoped và xóa phiên (SupportSessionTest, SupportConversationTest, SupportSessionDeletionTest); KTV chỉ thấy brief trong ticket được giao (SupportHandoffBriefTest). Attempt/guide/report progress/support attachment/outbox và quyền context riêng còn TARGET; không suy quyền từ bảng thiết kế.
