# 13 — Thiết kế tương tác web và Flutter

> Tài liệu gồm CURRENT_CODE CR-01–13 và wireframe TARGET được ghi riêng. Portal web UI-01/02/03 và một phần UI-05/08 đã có; Flutter/guide/acknowledgement/progress riêng còn TARGET.
> Nghiệp vụ: 10/11. Hợp đồng dữ liệu: 03; M1b chỉ dùng bốn API là checkpoint lịch sử, không phải giới hạn portal hiện tại.

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

CURRENT_CODE CR-05 (20/11/2026), UI-03 web: khi bấm gửi, tin người dùng hiện ngay trong log với nhãn “Đang gửi…”, xóa composer và cuộn đến cuối. Hiện bong bóng trợ lý “AI đang suy nghĩ” với ba chấm động trong lúc chờ; thao tác report/resolve dùng “Đang gửi báo cáo”/“Đang cập nhật”. Chỉ thay bằng lịch sử thật sau phản hồi server. Lỗi mạng/timeout dừng ba chấm, giữ tin với nhãn “Chưa xác nhận • Bấm Thử lại”; retry giữ cùng key/payload và không thêm bản sao. Lỗi 409/422 bỏ tin chưa nhận, phục hồi bản nháp nếu composer rỗng, đồng bộ lại phiên đã có. Đăng xuất dọn nội dung và bỏ phản hồi đến muộn. Nhãn chờ có role=status; các chấm aria-hidden, tắt animation khi prefers-reduced-motion. Không hiển thị tin tại client như bằng chứng server đã lưu. [Evidence](../../tasks/verification-cr05.md).

## 2. Wireframe phiên hỗ trợ

CURRENT_CODE CR-13: câu hỏi quan sát trong chat được backend chọn theo nhánh, không lặp kết quả đã trả lời. Ví dụ máy tính không mở lên → người dùng báo nguồn điện bình thường → hỏi đèn báo → tiếng quạt. Câu “hãy báo cáo lên KTV cho mình” chuyển sang luồng tạo phiếu; biên nhận chỉ xuất hiện khi server thực sự commit. Hết câu hỏi hoặc không thể kiểm tra thì đề nghị KTV; người dùng vẫn có thể yêu cầu chuyển ngay từ đầu. IT đọc phần PHÂN NHÁNH SƠ BỘ, kết quả người dùng nêu và CHƯA XÁC ĐỊNH trong mô tả phiếu hiện có, kèm hướng kiểm tra chưa xác minh. Chưa bổ sung panel IT tương tác riêng. [Evidence](../../tasks/verification-cr13.md).

CURRENT_CODE CR-12 (20/11/2026): mở Phòng và công việc cần hỗ trợ ngay từ đầu và khi tạo mới; nhãn chọn Loại công việc / thiết bị, kèm mô tả vấn đề trong composer. Người dùng có thể nêu trực tiếp trong chat thay lựa chọn. Khôi phục/tin trả về còn needs_context hoặc needs_description giữ picker mở dù chưa yêu cầu report; đủ thông tin tự thu gọn. Phòng từ QR/form đang chọn được đưa vào phiên mới, không hỏi lại khi đã có; danh mục mới vẫn cần xác định cho công việc mới. Backend chặn hướng dẫn thiếu thông tin, không chỉ kiểm tra UI. [Evidence](../../tasks/verification-cr12.md).

CURRENT_CODE CR-11 (20/11/2026): lịch sử hiện nhãn kết quả từng phiên; ticket resolved hiện Chờ xác nhận hoàn thành, ticket closed + session handed_off hiện Đã xử lý xong và nút Xóa phiên. Hộp xác nhận nêu phiếu/kết quả vẫn giữ; trong lúc DELETE khóa thao tác ghi/xóa xung đột. Lỗi giữ phiên và cho thử lại; thành công tải lại danh sách, lùi trang nếu trang cuối hết dữ liệu, dọn khung đang mở nếu chính phiên đó bị xóa. Không tự xóa phiên tự khắc phục chưa báo cáo. Backend kiểm tra lại điều kiện, không tin cờ client. [Evidence](../../tasks/verification-cr11.md).

CURRENT_CODE CR-10 (20/11/2026): vùng hội thoại có `data-auto-dismiss="false"`; script feedback chung không tự xóa trạng thái lịch sử, gửi tin, receipt hoặc chỉ báo chờ trong vùng này. Trạng thái được client hội thoại cập nhật/dọn theo request và lifecycle. Thông báo tạm ngoài vùng chat vẫn tự ẩn. Sửa lỗi phân trang sau khi trang mở hơn 5 giây; không đổi API/quyền. [Evidence](../../tasks/verification-cr10.md).

CURRENT_CODE CR-09 (20/11/2026): sau phản hồi JSON thành công cho lượt chat, câu trả lời trợ lý cuối hiện dần chữ, tối đa khoảng 80 nhịp × 20ms, có con trỏ và nút Hiện toàn bộ. Lịch sử đã lưu, báo cáo/resolve và reduced motion hiển thị ngay. Đổi phiên, tạo mới, gửi tiếp, chuyển sang thủ công hoặc logout kết thúc/hủy timer; không ép cuộn khi người dùng đang đọc tin cũ. Bản đầy đủ dành cho screen reader được công bố một lần, phần chữ động aria-hidden. Đây là hiệu ứng trình bày sau nhận phản hồi đầy đủ, không phải streaming token/model; không đổi backend, bản lưu hoặc độ trễ provider. [Evidence](../../tasks/verification-cr09.md).

CURRENT_CODE CR-08 (20/11/2026): các nút chat/chuyển chế độ có phản hồi hover/nhấn và viền lóe ngắn khi click; hỗ trợ click do bàn phím. Khi gửi chat/report/resolve hoặc tải lịch sử, nhãn nút đổi sang Đang… kèm spinner và aria-busy. Khóa nút gửi/report/resolve trong lúc gửi hoặc đọc phiên; nút tạo mới khóa trong lúc request ghi dữ liệu đang chạy để không bỏ ý định chưa xác nhận. Cuộc trò chuyện mới xóa receipt/retry/nội dung cũ, context và tham chiếu phiên trên client, hiện “Đã mở cuộc trò chuyện mới. Bạn có thể nhập tin nhắn.”; không xóa lịch sử server. Phản hồi đọc phiên cũ đến muộn không được ghi đè khung mới. prefers-reduced-motion tắt animation/biến đổi vị trí nhưng vẫn giữ viền phản hồi và nhãn xử lý. [Evidence](../../tasks/verification-cr08.md).

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

## 4. M1b trước khi có agent — checkpoint lịch sử

Các bullet M1b bên dưới áp dụng bản UI cũ; portal hiện dùng CR-01–13 và endpoint CURRENT_CODE ở 03. Không quay lại popup/chat điền nháp để làm theo hướng dẫn lịch sử.

### Cập nhật CR-01 — yêu cầu làm lại ngày 19/11/2026

Trang báo sự cố có hai cách rõ ràng: **Báo cáo thủ công** giữ form hiện tại; **Nhờ AI hỗ trợ** mở hội thoại lưu trên server. Người dùng mô tả, bổ sung phòng/danh mục bằng lời hoặc lựa chọn trong chat, thử hướng dẫn, và nói “Gửi báo cáo giúp tôi” hoặc bấm nút gửi ngay trong chat. Backend dùng dữ liệu gốc đã lưu, hỏi phần còn thiếu, tạo phiếu và trả mã thật; không chuyển sang form thủ công. Lịch sử phiên là chức năng phụ trong luồng AI, không còn ba chức năng cạnh tranh ở cấp trang.

CR-01 tận dụng provider/prompt chat đang có; chưa đồng nghĩa triển khai kho guide published, eval hoặc toàn bộ agent M3/M4. Model chỉ đề xuất câu trả lời; quyền gửi, người báo, ưu tiên và dữ liệu phiếu do backend quyết định. Notification hiện chỉ là kênh database nên CR-01 lưu notification cùng transaction phiếu/report, chưa cần gọi mạng/outbox worker. Receipt handoff vẫn lưu trước transaction hoàn tất để retry phục hồi. Outbox đa kênh, guide và vận hành còn theo roadmap.

- Bố cục từng gộp một nút nổi/ba mục là lịch sử trước CR-01; portal hiện dùng hai cách báo ngay trên trang theo mục CR-01 phía trên, không còn launcher nổi.
- Hội thoại CR-01 được lưu trên server và gửi báo cáo trực tiếp; không còn luồng “điền nháp rồi bắt gửi form” ở giao diện chính. Endpoint chat cũ vẫn được giữ tương thích, không được nhầm dữ liệu chat cũ trong trình duyệt là lịch sử server mới.
- UI-01/02/03 có thể lưu và đọc lại mô tả/tin đầu.
- Không có nút gửi tin tiếp tới endpoint TARGET chưa triển khai.
- Không lấy chat cũ gắn vào rồi nói tất cả hội thoại đã lưu.
- Có nhãn rõ “Đã lưu phiên” thay “AI đã xử lý”.
- Form báo sự cố cũ vẫn dùng được; tới M2 mới nối nút bàn giao mới có contract.
- Nếu dùng placeholder chức năng chưa bật, ghi rõ chưa khả dụng; không giả ticket ID hoặc tiến độ.

## 5. Sau khi bàn giao và khi bấm nhầm — TARGET mở rộng

CURRENT_CODE hiển thị receipt ticket_number/link và khóa chat handed_off, chưa có các mốc report acknowledgement/delivery/follow-up riêng. CR-01 có action resolve và cho chat tiếp khi chưa bàn giao; chưa có shortcut hoàn tác 10 phút hoặc endpoint reopenings. CR-11 thêm xóa mềm phiên có ticket closed. Không vẽ nút theo TARGET như đã có chức năng.

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
