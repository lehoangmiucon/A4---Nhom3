# 01 — Yêu cầu và phạm vi

## 1. Mục tiêu

Giảng viên mô tả sự cố phòng học bằng tiếng Việt. Hệ thống hỗ trợ tuyến đầu, chỉ đưa thao tác được duyệt, ghi kết quả và bàn giao cho người thật khi cần. Mục tiêu là giảm hỏi lại và gián đoạn giờ học, đo bằng dữ liệu pilot.

Một ca có kết quả rõ ràng: tự xử lý được do giảng viên xác nhận; đã có phiếu/lượt báo để theo dõi; hoặc người dùng hủy/chưa có kết quả. Im lặng không phải xác nhận đã sửa xong.

## 2. Phạm vi và giới hạn

Bản phát hành đầu hoàn chỉnh gồm web và Flutter Android, Laravel dùng chung, API AI, máy chiếu/micro, khoảng 10 tình huống được duyệt, theo dõi bàn giao đến quản trị/KTV. Phát triển theo mốc không được cắt bỏ safety hoặc phân quyền để demo nhanh.

Ngoài phạm vi: fine-tuning, GPU hosting, AI đọc ảnh/giọng nói, tự sửa thiết bị, tự mua vật tư, tự phân công KTV hoặc dự đoán xác suất hỏng. Ảnh gửi để KTV xem không có nghĩa model đã hiểu ảnh. iOS là hướng mở rộng cần hợp đồng build/push và nghiệm thu riêng.

## 3. Vai trò

| Actor | Phạm vi |
| --- | --- |
| Giảng viên | Chủ phiên, báo lỗi, trả lời, bỏ qua hướng dẫn, xem tiến độ được phép |
| Sinh viên/lớp trưởng | Dùng các khả năng customer được bật; không tự nhận vai trò teacher |
| Quản trị | Tiếp nhận, phân công, theo dõi quá hạn, quản lý nội dung theo quyền |
| KTV | Xem dữ liệu phục vụ phiếu đang được giao, cập nhật kết quả |
| Người duyệt hướng dẫn | Phê duyệt/xuất bản/thu hồi theo phân công |
| Vận hành | Giám sát dịch vụ, quota, queue, dữ liệu và sự cố; không mặc định xem mọi chat |

Quản trị/KTV chỉ được xem phiên theo policy bàn giao khi policy đó đã triển khai. M1a chỉ cho chủ phiên student/teacher đang hoạt động, đã xác thực email. Khóa tài khoản phải chặn cả API web/token.

## 4. Yêu cầu chức năng

Mỗi AC dùng làm oracle cho test; không thay bằng nhận xét “trông có vẻ đúng”.

| ID | Yêu cầu | Tiêu chí chấp nhận |
| --- | --- | --- |
| AI-FR-001 | Tạo phiên đúng phòng/thiết bị | Phòng tồn tại; thiết bị nếu có thuộc phòng; server xác định người báo; mô tả rỗng/quá dài bị từ chối |
| AI-FR-002 | Lưu hội thoại và đọc lại | Tin đã commit đọc được sau tải lại/đổi client; lịch sử có thứ tự và phân trang; không lấy lịch sử client làm bằng chứng |
| AI-FR-003 | Chống gửi lại | Cùng key/payload trả kết quả ban đầu; key khác payload lỗi; không nhân đôi tin/phiếu/tác dụng phụ |
| AI-FR-004 | Hỏi làm rõ | Chọn câu hỏi hợp lệ, không hỏi lại thông tin đã biết; dừng khi hết giới hạn hoặc người dùng muốn gặp người thật |
| AI-FR-005 | Hướng dẫn có điều kiện | Chỉ bước published đủ điều kiện; mỗi lần một bước; nội dung thao tác lấy nguyên bản đã duyệt |
| AI-FR-006 | Ghi kết quả bước | Chỉ kết quả của bước đã phát cho đúng phiên; phân biệt resolved, not_fixed, cannot_perform |
| AI-FR-007 | Hỗ trợ trực tiếp | Nút luôn có; không bắt thử trước; dùng được khi AI lỗi/hết quota; chỉ xác nhận tạo phiếu sau commit |
| AI-FR-008 | Bàn giao đầy đủ | Báo cáo có dữ liệu gốc, bước và kết quả, lý do; AI lỗi vẫn có mẫu xác định; không thêm kết luận như sự thật |
| AI-FR-009 | Gộp sự cố có căn cứ | Lưu từng lượt báo; không gộp chỉ vì cùng phòng/danh mục; chưa chắc thì không tự gộp |
| AI-FR-010 | Tiến độ và quyền người báo sau | Xem được tiến độ tối thiểu qua lượt báo; không xem dữ liệu riêng của người báo trước |
| AI-FR-011 | Tiếp nhận và phân công | Tách tiếp nhận, gửi thông báo và phân công; quản trị/KTV theo nghiệp vụ phiếu hiện có |
| AI-FR-012 | Hoàn tất/mở lại/tái diễn | Người dùng xác nhận; có hoàn tác và báo còn lỗi; không sửa/xóa phiếu hoặc minh chứng trái quyền |
| AI-FR-013 | Quản trị hướng dẫn | Draft, review, published, withdrawn; sửa published tạo bản mới; lưu người duyệt và snapshot đã phát |
| AI-FR-014 | Web | Dùng API chung; trạng thái mạng/đang xử lý rõ; form báo lỗi trực tiếp hoạt động trong suốt quá trình chuyển đổi |
| AI-FR-015 | Flutter | Đăng nhập, QR, phiên, ảnh, tiến độ, push; retry giữ ID; không chứa API key của model |
| AI-FR-016 | Nhắc tiếp nhận | Lịch trực, deadline, nhắc và người thay thế; đọc notification không tự đánh dấu tiếp nhận |
| AI-FR-017 | Khi model không dùng được | Deadline hữu hạn; chuyển đường bàn giao không cần model; client không có server acknowledgement phải ghi chưa xác nhận |
| AI-FR-018 | Thiết bị ngoài phạm vi | Vẫn tiếp nhận báo cáo trực tiếp; không ép model bịa hướng dẫn hoặc cấm báo lỗi |

### Các tình huống bắt buộc

- Đang đứng lớp, muốn gọi hỗ trợ ngay từ đầu.
- “Tôi không biết làm” sau một câu hỏi hoặc thao tác.
- “Đã được” rồi phát hiện chưa được, cả sau thời gian hoàn tác nhanh.
- Hai giảng viên báo cùng thiết bị, hoặc hai thiết bị hỏng khác nhau cùng phòng.
- Web/app cùng gửi lệnh, phản hồi AI cũ đến sau yêu cầu chuyển cấp.
- API timeout/quota, queue dừng, điện thoại mất mạng, server chưa xác nhận.
- Người dùng đổi ID để đọc phiên/ảnh hoặc nội dung cố thao túng agent.

## 5. Yêu cầu phi chức năng

| ID | Yêu cầu | Tiêu chí kiểm chứng |
| --- | --- | --- |
| AI-NFR-001 | Phân quyền mọi tài nguyên | Policy trên phiên/tin/command/ảnh/progress; không có dữ liệu chéo chủ phiên; route riêng cho admin/KTV |
| AI-NFR-002 | An toàn và chống lạm quyền model | Không thực thi hành động chỉ vì JSON hợp lệ; kiểm tra điều kiện và quyền tại backend |
| AI-NFR-003 | Nhất quán đồng thời | Version, snapshot, transaction ngắn, unique constraint; kết quả cũ không làm sống lại phiên đã chuyển cấp |
| AI-NFR-004 | Chịu lỗi | Outbox, retry hữu hạn và deadline; lỗi không mất yêu cầu đã commit; trạng thái phản ánh thực tế |
| AI-NFR-005 | Kiểm soát chi phí | Giới hạn phân tán, reservation, token và số lần thử; đường bàn giao có hạn mức riêng |
| AI-NFR-006 | Riêng tư và truy vết | Log đủ mã/phiên bản/usage, không log bí mật/raw chat mặc định; dữ liệu gửi API tối thiểu |
| AI-NFR-007 | Hiệu năng có số đo | Đo cả API/queue; mục tiêu cấu hình pilot ở tài liệu 07; không lấy tốc độ máy dev làm cam kết |
| AI-NFR-008 | Khả năng bảo trì | Hợp đồng có phiên bản; module rõ; schema/OpenAPI/test được cập nhật cùng thay đổi |
| AI-NFR-009 | Phát hành và khôi phục | Feature flag, tương thích migration, backup/restore thực nghiệm và runbook |
| AI-NFR-010 | Chất lượng AI có đánh giá | Bộ tình huống độc lập được duyệt; thay model/prompt/guide chạy lại eval liên quan |

## 6. Luật nghiệp vụ không được tự thay đổi

- Session resolved khác Ticket resolved; AI không có quyền đóng phiếu KTV.
- Ưu tiên teacher theo backend hiện có; nút “hỗ trợ trực tiếp” không mặc định urgent.
- QR là dữ liệu phòng, không phải quyền truy cập hay bằng chứng có mặt.
- Nhãn snapshot phải còn đọc được khi phòng/thiết bị bị xóa; room_id chỉ nullable sau thay đổi dữ liệu.
- Không tự gọi reopen phiếu khi người dùng mở lại phiên: nghiệp vụ cũ có thể xóa ảnh sửa chữa.
- Không hiện người báo/KTV/ghi chú nội bộ cho người báo sau khi chưa có chính sách cho phép.

## 7. Đo thành công

Đo thời gian tiếp nhận, hỏi lại, tỷ lệ tự giải quyết có xác nhận, chất lượng bàn giao, độ trễ, lỗi và chi phí/ca. Không cam kết first-time-fix 85% hoặc MTTR dưới 5 phút khi chưa có baseline. Một bộ test đạt không chứng minh không còn rủi ro ngoài thực tế.

Truy vết yêu cầu đến test nằm tại [06](06_KIEM_THU_VA_TRUY_VET.md).
