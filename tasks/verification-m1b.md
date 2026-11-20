# Kiểm chứng M1b và BASE-001 — sau review

Ngày: 19/11/2026 (Asia/Bangkok). Trạng thái: DONE_CODE, kiểm chứng local; chưa xác nhận DEPLOYED hoặc RELEASED.

Phạm vi: BASE-001 và M1B-01/02/03, gồm hai nhóm sửa review M1B-R1/R2 trong todo.md. Truy vết: AI-FR-001/002/003/014, AI-NFR-001; UC-01, BR-01/02; TC-001/002/003/004/005/018/023 trong phạm vi phiên và form web. Không triển khai M2/M3, gọi AI trả phí hoặc migrate database đang dùng.

## Baseline thực tế

- Báo cáo triển khai ban đầu nêu 142 test / 1948 assertions. Lần review này chạy lại `php artisan test --compact` và xác nhận đúng kết quả đó trước khi sửa.
- BASE-001: diff cho thấy UiController tái sử dụng pendingTicketCount; test hiệu năng đạt. RED trước bản sửa BASE-001 và baseline 135 passed / 1 failed là evidence lịch sử của triển khai trước, không được tái tạo bằng cách hoàn nguyên code người dùng trong lượt review này.
- Sáu test SupportSessionUiTest ban đầu chủ yếu kiểm tra HTML và API; chúng không thực thi JavaScript và chưa chứng minh retry mạng, storage/logout hay đọc hết trang tin trên trình duyệt. Ca form cũ kiểm tra cấu trúc HTML, không tự chứng minh gửi thông báo kỹ thuật viên thật.

## RED → GREEN của lần review

1. `node --test tests/js/support-session.test.mjs`: ban đầu 1 passed / 7 failed. Các failure tái hiện: nút tạo bị khóa khi fetch reject, gửi payload mới bằng intent chưa xác nhận, danh sách lỗi mạng bị treo, chỉ đọc trang tin đầu, phản hồi detail muộn vẫn chạy sau chuyển view/logout, và storage bị chặn làm dừng script.
2. Bổ sung ca đồng bộ form với QR/phòng đang tải: 11 passed / 2 failed; room-id vẫn là phòng QR cũ. Sau sửa handler portal-qr, cả hai ca đạt.
3. Bổ sung assertion thiết bị trong SupportSessionUiTest: 1 failed / 5 passed vì thiết bị chưa được đưa ra giao diện. Sau eager load và select equipment_id theo phòng, test đạt. Tên thiết bị trong data-equipment dùng Unicode và escape HTML bởi Blade.
4. Kết quả cuối riêng M1b JavaScript: 15 passed / 0 failed. Có thêm validation theo error.details, chống submit kép, chọn thiết bị/reset khi đổi phòng, nút điền form không tạo phiếu và list response trả sai thứ tự.

## Các sửa đổi đã kiểm chứng

- Fetch có xử lý reject và deadline 15 giây; lỗi tạo phiên mở lại nút gửi, giữ key và payload cho retry cùng nội dung trong vòng đời trang. Timeout có trong code; chưa chạy fault injection 15 giây thực trên trình duyệt.
- Payload chưa xác nhận mà bị thay đổi sẽ được giải thích trên form, không tự gửi intent mới. Người dùng có thể kiểm tra danh sách rồi bấm Hủy để bắt đầu intent mới. Lỗi validation 422 thông thường cho sửa nội dung; IDEMPOTENCY_KEY_REUSED giữ trạng thái để xử lý rõ ràng.
- Đọc lỗi đúng envelope error.message/error.details. Đọc đủ các trang messages, có nút thử lại khi không tải đầy đủ; không coi HTML/JSON thiếu data là thành công.
- Revision của view/list ngăn phản hồi cũ ghi đè lựa chọn mới. Logout xóa state và dữ liệu phiên đang hiển thị, bỏ qua kết quả còn đang chờ; storage không truy cập được không làm hỏng điều hướng.
- Thay ô thiết bị tự do vốn không được gửi bằng select thiết bị thuộc phòng, gửi equipment_id theo hợp đồng sẵn có. routes/web.php eager load rooms.equipment.
- Hỗ trợ trực tiếp điền mô tả/tiêu đề và yêu cầu bộ chọn phòng cũ đồng bộ tòa/phòng/mã QR. Hoạt động cả trước và sau khi danh sách phòng tải xong, thay context QR cũ; phòng bị xóa yêu cầu chọn lại. Chỉ điền form, chưa tạo phiếu hoặc thông báo KTV.
- Tách vị trí hai nút mở chat; sửa CSS bị .portal-chat button ghi đè, giữ panel trong viewport và nội dung cuộn được. Nhãn đã lưu chỉ bật sau khi GET phiên thành công, không hứa KTV xử lý ngay.
- Tài liệu 03 và mobile-api bổ sung initial_description nullable từ tin đầu. Key cũ replay nguyên response_data đã lưu, có thể thiếu trường mới; GET trả DTO hiện tại. README/plan được đồng bộ với evidence này.

## Lệnh và kết quả cuối

| Lệnh | Kết quả thực tế |
| --- | --- |
| `php artisan test --compact` | 142 passed, 0 failed; 1950 assertions |
| `php artisan test --compact --filter='SupportSessionUiTest\|UiPerformanceTest'` | 11 passed; 61 assertions (6 UI / 44 assertions và 5 performance / 17 assertions) |
| `node --test tests/js/*.test.mjs tests/JavaScript/*.test.cjs` | 28 passed, 0 failed; trong đó 15 ca M1b |
| `node --check public/customer/assets/js/support-session.js` | Đạt |
| `node --check public/customer/assets/js/portal-qr.js` | Đạt |
| `php -l` cho UiController.php, SupportSession.php, routes/web.php, SupportSessionUiTest.php | Đạt |
| `php vendor/bin/pint --test app/Models/SupportSession.php tests/Feature/Support/SupportSessionUiTest.php` | Đạt; không format toàn repo |
| `git diff --check` | Đạt trên diff tracked; các file mới được kiểm tra riêng bằng syntax/test |

PHP dùng SQLite :memory: theo phpunit.xml; không chạy migration trên database thật. Không có API model/notification thật được gọi trong các kiểm chứng này.

## Smoke trên trình duyệt thật, API giả

Chrome headless với profile tạm riêng, fixture render từ Blade hiện tại bằng model/collection giả trong RAM, chạy tại localhost:8124. Mock fetch toàn bộ request API, không sử dụng tài khoản thật hoặc database. Các script render/fixture/harness local được giữ ở storage/framework/testing/m1b-review (thư mục ignored); đây là smoke review, không phải bộ browser integration đã nối CI.

Lệnh local: `php storage/framework/testing/m1b-review/render.php`; `php -S 127.0.0.1:8124 -t public storage/framework/testing/m1b-review/router.php`; `node storage/framework/testing/m1b-review/browser-check.cjs`. Server đã dừng sau kiểm tra. Lần khởi chạy Chrome trong sandbox ban đầu không chạy được GPU; lần chạy được cấp quyền ngoài sandbox dùng profile tạm riêng, không dùng profile cá nhân.

Bốn viewport 320x900, 768x900, 1024x900, 1440x900 đều PASS; không có error/unhandled rejection trong page. Kiểm tra DOM và tương tác thực: list → detail → hai trang tin → điền đúng form → tạo phiên mất mạng → retry cùng key/payload và equipment_id → empty → error → retry; hai launcher không đè nhau; tab active/inactive khác kiểu; panel nằm trong viewport. Fixture xóa storage giữa các viewport để các ca độc lập. Đã xem ảnh tại 320 và 1440px.

Bằng chứng lưu cùng repository:

- [Kết quả bốn viewport](evidence/m1b-review/browser-results.json).
- [Ảnh 320px](evidence/m1b-review/viewport-320.png).
- [Ảnh 1440px](evidence/m1b-review/viewport-1440.png).

## Giới hạn và gate còn lại

- Chưa xác nhận deployment/migration của môi trường người dùng; không đổi trạng thái RELEASED.
- Browser smoke dùng API giả; chưa UAT đăng nhập, cookie/Sanctum/CSRF và gửi form thật trên staging. PHP feature test xác nhận contract/quyền, không thay bằng chứng trình duyệt kết nối backend thật.
- Chưa chạy screen reader thực hoặc kiểm tra đầy đủ thứ tự focus/bàn phím; không tuyên bố WCAG đã nghiệm thu. Logout/reload/race được kiểm tra bằng JavaScript harness, không phải toàn bộ tình huống đa tab/BFCache trên trình duyệt thật.
- Chưa kiểm chứng MySQL concurrency, provider thật, notification delivery, thiết bị mobile hay M2/M3. Không coi việc điền form là đã bàn giao bền vững.
- Key tạo phiên được giữ trong bộ nhớ trang cho retry, chưa có recovery intent đang gửi qua reload/tab crash. sessionStorage chỉ lưu userId/sessionId, không lưu raw chat.

Bước tiếp theo theo todo.md: M2-01. Các gate staging/UAT ở trên cần kiểm chứng trước nghiệm thu phát hành.

## Cập nhật theo yêu cầu gộp một nút Hỗ trợ — 19/11/2026

Phần này thay thế bố cục hai launcher trong ảnh/evidence review ban đầu. M1B-R3a/R3b đã gộp thành một nút nổi “Hỗ trợ”, một popover với ba mục Phiên đã lưu / Tạo phiên / Hỏi AI. Chat cũ được nhúng vào bảng chung và ghi rõ chưa lưu vào phiên; không thay contract M1a hoặc giả có hội thoại agent M3. Nút điền nháp đóng bảng chung và đưa về form. Đóng/mở lại giữ mục đang xem; logout dọn cả chat và phiên, hủy request chat đang chờ.

RED: test DOM mới thất bại đúng vì có 2 launcher. GREEN: `php artisan test --compact --filter=SupportSessionUiTest` đạt 7 test / 50 assertions. Hai ca JS mới kiểm tra chuyển mục không tự gửi AI và mở lại giữ mục.

Kết quả cuối sau thay đổi:

- `php artisan test --compact`: **143 passed / 1956 assertions**.
- `node --test tests/js/*.test.mjs tests/JavaScript/*.test.cjs`: **30 passed**, gồm 17 ca M1b.
- `node --check` cho portal-chat.js và support-session.js: đạt; Pint test file PHP UI đạt; `git diff --check` đạt (chỉ có cảnh báo chuẩn hóa CRLF/LF).
- Chrome headless 320/768/1024/1440px: cả bốn đạt, không có lỗi page JavaScript. Ngoài luồng phiên cũ, smoke xác nhận đúng một launcher/popover, chuyển sang chat không gọi model, gửi chat qua API giả trả reply/draft, điền nháp đóng bảng chung, mở lại giữ chat, chuyển về danh sách phiên vẫn đọc được. Các API đều fake; chưa nghiệm thu provider thật hoặc session/CSRF trên staging.

Bằng chứng mới: [kết quả bốn viewport](evidence/m1b-unified/browser-results.json), [ảnh 320px](evidence/m1b-unified/viewport-320.png), [ảnh 1440px](evidence/m1b-unified/viewport-1440.png). Đã xem ảnh 320px để xác nhận chỉ một nút Hỗ trợ và nội dung chat nằm trong cùng bảng. Các giới hạn staging, screen reader, migration và deployment ở trên vẫn áp dụng.
