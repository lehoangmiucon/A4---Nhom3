# 13 — Thiết kế tương tác web và Flutter

> Đây là đặc tả tương tác/wireframe, không phải giao diện đã được lập trình. Không thay framework/giao diện hiện có ngoài phạm vi task.
> Nghiệp vụ: 10/11. Hợp đồng dữ liệu: 03. M1b chỉ dùng bốn API CURRENT_CODE.

## 1. Danh mục màn hình

| ID | Màn hình/khu vực | Actor | Dữ liệu và trạng thái chính |
| --- | --- | --- | --- |
| UI-01 | Chọn phòng, mô tả | Customer | QR/chọn phòng, thiết bị tùy chọn, mô tả, xác thực |
| UI-02 | Danh sách phiên | Customer | Phân trang, status, lúc cập nhật, không có phiên/loading/error |
| UI-03 | Phiên hỗ trợ | Chủ phiên | Hội thoại server, action hiện có, pending/khôi phục |
| UI-04 | Thẻ bước kiểm tra | Chủ phiên | Nội dung đã duyệt, ba kết quả, hỗ trợ trực tiếp |
| UI-05 | Kết quả bàn giao | Chủ report | Ticket reference, report acknowledgement, notification state |
| UI-06 | Tiến độ và follow-up | Chủ report | Progress tối thiểu, còn lỗi/tái diễn |
| UI-07 | Queue quản trị | Admin | Chưa tiếp nhận/quá hạn/chưa phân công, report facts |
| UI-08 | Context công việc | KTV | Phiếu được giao, facts/bước đã thử, không chat ngoài quyền |
| UI-09 | Soạn/duyệt guide | Người được giao | Version, source, precondition, review, published/withdrawn |
| UI-10 | Vận hành | Operator | Queue/quota/circuit/cost, không raw chat mặc định |

Mỗi màn phải có empty/loading/error/success; không chỉ thiết kế happy path.

## 2. Wireframe phiên hỗ trợ

```text
Phòng A203 · Máy chiếu                 [Danh sách phiên]
Trạng thái: Đang thu thập thông tin

[Yêu cầu hỗ trợ trực tiếp]   <- luôn thấy, không hứa KTV đã tới

Giảng viên: Máy chiếu không có hình.
Trợ lý: Màn chiếu có hiện thông báo không?
[Trả lời bằng lựa chọn hợp lệ / nội dung mô tả]

Thẻ hướng dẫn đã duyệt (khi backend cấp):
  Tên bước và nội dung cố định
  [Đã được] [Chưa được] [Không thể thực hiện]

Tin nhắn / mô tả bổ sung
[Nhập nội dung...] [Gửi]

Trạng thái gửi: đã lưu / đang xử lý / chưa xác nhận
[Báo lỗi bằng form trực tiếp]
```

Không phát đồng thời hai thẻ đang hoạt động. Thẻ cũ là lịch sử, nút kết quả bị vô hiệu với giải thích khi đã chốt hoặc phiên bàn giao.

## 3. Tương tác chính

| Tình huống | UI làm | UI không làm |
| --- | --- | --- |
| Chưa biết phòng | Cho chọn/xác nhận vị trí và contact đã cấu hình | Không tự đoán QR/phòng hoặc gọi AI trước phiên |
| Nhấn gửi | Tạo ID/key một lần, disable gửi lặp cục bộ, giữ đường thoát | Không chỉ dựa vào disable để chống trùng server |
| Sau 5 giây | Báo chậm, nút trực tiếp còn hiện | Không tự nói provider bảo trì |
| Mất response | Ghi chưa xác nhận, cho tra/retry cùng key | Không tạo key mới cho cùng intent |
| 409 | Tải state mới, giữ dữ liệu chưa gửi và hỏi lại khi cần | Không ghi đè bằng state cũ |
| Đã chuyển cấp | Dừng controls hướng dẫn, lấy version/state mới | Không render AI muộn như câu trả lời đang hoạt động |
| Model lỗi/quota | Hiển thị fallback/receipt/phiếu thực | Không hiện hoàn thành chỉ vì hết spinner |
| Key/payload mismatch | Giải thích đây là intent khác, giữ nội dung | Không tự đổi key và gửi nhiều phiếu trong nền |
| Report liên kết ticket cũ | Tách tiến độ ticket và việc report mới đã được tiếp nhận chưa | Không dùng tên/người báo trước để trấn an nếu chưa có quyền |

## 4. M1b trước khi có agent

- UI-01/02/03 có thể lưu và đọc lại mô tả/tin đầu.
- Không có nút gửi tin tiếp tới endpoint TARGET chưa triển khai.
- Không lấy chat cũ gắn vào rồi nói tất cả hội thoại đã lưu.
- Có nhãn rõ “Đã lưu phiên” thay “AI đã xử lý”.
- Form báo sự cố cũ vẫn dùng được; tới M2 mới nối nút bàn giao mới có contract.
- Nếu dùng placeholder chức năng chưa bật, ghi rõ chưa khả dụng; không giả ticket ID hoặc tiến độ.

## 5. Sau khi bàn giao và khi bấm nhầm

UI-05 hiển thị các mốc riêng: ý định đã nhận; phiếu đã tạo/liên kết; thông báo pending/sent/failed; report đã/chưa tiếp nhận; assignment/progress của ticket.

Resolved hiện nút Sự cố vẫn còn. Shortcut hoàn tác 10 phút không được làm mất chức năng báo tiếp sau đó. Ticket đã đóng chỉ gửi yêu cầu xem xét xử lý lại, không hiển thị nút “tự mở lại phiếu” cho giảng viên.

## 6. Quản trị/KTV

Admin queue phải lọc report chưa tiếp nhận và ticket chưa phân công riêng. Mỗi report có nhãn tổng hợp AI và facts gốc. Không hiển thị phần AI như chẩn đoán đã xác minh.

KTV chỉ xem công việc được giao, các bước giảng viên đã thử, kết quả và ảnh có quyền. Giữ điều kiện xác nhận lịch/minh chứng/đóng phiếu hiện có. Không tự thay một click giải quyết mọi yêu cầu nghiệp vụ cũ.

## 7. Accessibility và mobile

- Các nút có nhãn chữ, không dựa riêng màu đỏ/xanh; hỗ trợ bàn phím và focus rõ.
- Khu vực cập nhật async dùng thông báo trạng thái phù hợp screen reader, không cướp focus ở mỗi tin.
- Không bắt giữ nút/gesture phức tạp để gọi hỗ trợ trực tiếp.
- Danh sách/thẻ vừa màn hình nhỏ, không yêu cầu cuộn ngang để bấm hỗ trợ.
- Không render HTML model; text và ảnh phải an toàn.
- Web/app cùng một chính sách action/state, không để mobile tự suy đoán trạng thái.
- Logout/đổi tài khoản xóa state và subscription; không lộ preview chat trên màn khóa.

## 8. Nghiệm thu UI

TC-004/018/019/020/025 cần bằng chứng trình duyệt/thiết bị thật. Chụp cả empty/loading/error, mất mạng, pending và trạng thái sau chuyển cấp; không chỉ screenshot màn chat thành công. UI của API chưa triển khai không được đánh dấu hoàn thành.
