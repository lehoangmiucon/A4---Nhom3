# 05 — Quy trình phát triển và kế hoạch

## 1. Quy trình áp dụng

Làm theo vòng lặp nhỏ có kiểm chứng: đặc tả → thiết kế/hợp đồng → task → TDD → review → cập nhật tài liệu → staging → nghiệm thu → phát hành. Không gọi đây là chứng nhận tuân thủ ISO hoặc một tiêu chuẩn mà dự án chưa được đánh giá.

Bộ này dùng tài liệu nhẹ để thực hiện các hoạt động cần thiết của phát triển phần mềm, không yêu cầu viết mọi thứ trước rồi mới có một bản chạy được.

### Trách nhiệm theo vai trò

| Hồ sơ/kết quả | Người thực hiện | Vai trò chịu trách nhiệm chốt | Tham vấn |
| --- | --- | --- | --- |
| Phạm vi, UC/BR và ưu tiên | Phân tích nghiệp vụ/dev | Chủ nhiệm dự án hoặc người được ủy quyền nghiệp vụ | Giảng viên, quản trị/KTV |
| Contract/API, dữ liệu và kiến trúc | Dev phụ trách module | Người phụ trách kỹ thuật | Web/mobile, QA |
| Nội dung guide và an toàn | Người soạn chuyên môn | Người được phân công duyệt guide | KTV, quản trị |
| Test kỹ thuật và evidence | Dev/QA | Người phụ trách chất lượng | Người review kỹ thuật |
| UAT | Đại diện giảng viên/quản trị/KTV | Đại diện nghiệp vụ được giao nghiệm thu | QA/dev |
| Ngân sách, riêng tư và release | Vận hành/dev triển khai | Chủ dự án/chủ quản môi trường theo phân công | Chủ quản dữ liệu, nghiệp vụ |

Đây là phân công theo trách nhiệm, chưa gán tên cá nhân hoặc tự coi đã ký duyệt. Một người có thể giữ nhiều vai trò nhưng mỗi đợt nghiệm thu cần ghi rõ người chịu trách nhiệm thực tế và phạm vi quyết định.

## 2. Các cổng kiểm soát

| Gate | Đầu vào | Đầu ra cần có |
| --- | --- | --- |
| G0 — Yêu cầu | Vấn đề và phạm vi được người dùng chốt | FR/NFR, actors, ngoài phạm vi, quyết định còn thiếu |
| G1 — Thiết kế | Yêu cầu của task | Contract, trạng thái, dữ liệu, failure modes và phụ thuộc rõ |
| G2 — Ready | Task nhỏ | Điều kiện Ready bên dưới đạt; không có mâu thuẫn ảnh hưởng task |
| G3 — Code | Test RED phù hợp | GREEN, review và evidence; chỉ đánh dấu DONE_CODE |
| G4 — Staging | Code và migration đã review | Kiểm chứng môi trường, lỗi hạ tầng, UI và hợp đồng thực |
| G5 — Release | Staging và nghiệm thu nghiệp vụ | Người chịu trách nhiệm, cấu hình, rollback và bằng chứng phát hành |

Các quyết định trong hội thoại đã được chốt không cần hỏi lại. Đây không phải chuỗi xin phép cho từng file. Cần ý kiến người dùng khi thay đổi mục tiêu/quyền/hợp đồng đã chốt hoặc thiếu một quyết định ảnh hưởng công việc; vẫn làm các phần độc lập.

## 3. Definition of Ready cho một task

- Có task ID, module ID và FR/NFR liên quan; dẫn UC/BR, quyền, màn hình và diagram ID khi task tác động các phần đó.
- Mô tả hành vi trước/sau và acceptance criteria quan sát được.
- Phân biệt code đã có với target mới, đã đọc call path bằng CodeGraph.
- Endpoint/DTO/enum/migration cần thiết đã rõ.
- Phụ thuộc đã hoàn thành ở mức cần thiết; không dựa trên API chưa tồn tại.
- Có failure cases: quyền, dữ liệu sai, gửi lại, lỗi phụ thuộc, đồng thời nếu có.
- Có test/verification command phù hợp và môi trường an toàn.
- File dự kiến khoảng 3–5; nếu quá lớn thì tách trước.
- Có phạm vi không làm và không có “tiện thể đổi framework” trong task.

## 4. Definition of Done

### DONE_CODE

- [ ] Acceptance criteria của task đạt.
- [ ] Có test thất bại trước sửa logic và đạt sau sửa, trừ tài liệu/cấu hình tĩnh.
- [ ] Đã chạy test liên quan và hồi quy thích hợp.
- [ ] Không xóa/bỏ qua test để làm xanh; lỗi nền được so trước/sau.
- [ ] Policy và validation nằm ở boundary; backend không tin client/model.
- [ ] Lỗi, retry, trạng thái pending và side effect được xử lý.
- [ ] API/Swagger và tài liệu sở hữu nội dung đã cập nhật.
- [ ] Review correctness, security, readability, architecture, performance.
- [ ] Evidence ghi command, kết quả, giới hạn và phần chưa triển khai.
- [ ] Không commit secret, không thay dữ liệu production hoặc phát sinh phí ngoài phạm vi được giao.

### VERIFIED_STAGING và RELEASED

DONE_CODE không đủ để phát hành. Cần migration, UI thật, MySQL concurrency khi liên quan, provider thật trong ngân sách, giám sát và runbook theo tài liệu 07. Chỉ RELEASED khi có môi trường, version và người xác nhận.

## 5. Trình tự milestone

Đây là roadmap TARGET; CURRENT_CODE đã đi theo các lát cắt CR-01–13 qua một phần M2/M3/M4. Không chạy lại từ đầu task đã có hoặc coi cả milestone hoàn tất từ các lát cắt đó. Thứ tự nhận việc cụ thể và bản đồ file ở [plan](../../tasks/plan.md); trạng thái/AC duy nhất ở todo.

| Mốc | Kết quả | Phụ thuộc |
| --- | --- | --- |
| M1a | API lưu/đọc phiên | Đã có mã/test; chưa deployed |
| M1b | Web tạo, liệt kê, đọc lại phiên | M1a |
| M2 | Bàn giao không cần AI, report, phiếu, outbox và progress riêng | M1a; tích hợp web trên M1b |
| M3 | Một tình huống qua guide/command/provider | M2, contract an toàn |
| M4 | Safety, quota, stale response, timeout, hoàn tác và chế độ suy giảm | M3; trước mở agent cho người dùng thật |
| M5 | Tiếp nhận, phân công, nhắc, quản trị guide | M2 và guide catalog |
| M6 | Flutter Android và push | API ổn định của M1–M5 |
| M7 | Nội dung và đánh giá độc lập | M3/M4, người duyệt |
| M8 | Pilot, vận hành, tải, phát hành | Các mốc liên quan đạt |

Danh sách task và trạng thái duy nhất nằm ở [tasks/todo.md](../../tasks/todo.md). Không tạo bản checkbox thứ hai trong bộ tài liệu. Các nhánh frontend giả lập có thể làm song song sau hợp đồng; schema, shared service và route dùng chung phải phối hợp tuần tự.

## 6. Chu trình thực hiện task bằng AI coding agent

1. Đọc index → task đang đến lượt → file sở hữu hợp đồng liên quan.
2. Kiểm tra git status; không xóa/sửa thay đổi không thuộc task.
3. Khi .codegraph tồn tại, dùng CodeGraph trước khi grep/đọc code.
4. Nêu phạm vi sẽ làm và assumptions; chỉ hỏi khi thiếu quyết định cần thiết.
5. Viết test cho hành vi/failure mode thật, chạy để xác nhận RED có đúng nguyên nhân.
6. Triển khai tối thiểu để GREEN; không viết implementation rồi tạo test mô phỏng lại code.
7. Refactor trong phạm vi nếu cần, chạy lại khi có thay đổi liên quan.
8. Review; cập nhật API, RTM và evidence.
9. Đánh dấu task DONE_CODE, không đánh dấu milestone hoàn tất khi thiếu integration.
10. Tiếp tục task được giao kế tiếp nếu đủ điều kiện; báo rõ chỗ chặn thực sự.

Test không được tiêu API thật, gửi mail/push thật hoặc dùng database người dùng mặc định. TestCase hiện bắt buộc SQLite :memory:.

## 7. Công cụ và lệnh hiện có

```powershell
git status --short
php artisan test --filter=SupportSessionTest
php artisan test --filter=SwaggerDocumentationTest
php artisan test --compact
node --test tests/js/*.test.mjs tests/JavaScript/*.test.cjs
node tests/browser/support-diagnostic-smoke.mjs
php -l app/Http/Controllers/SupportSessionController.php
php vendor/bin/pint --test app/Http/Controllers/SupportSessionController.php
git diff --check
php artisan route:list --path=api/support-sessions --except-vendor
```

Đổi đường dẫn/test filter theo task. “No tests executed” không phải PASS. Không chạy Pint toàn repo để sửa hàng loạt style ngoài phạm vi.

composer test là script khai báo của repository; cài Composer/PHP phù hợp máy nhận, không giả định PATH hoặc đường dẫn của máy tác giả. Có thể chạy php artisan test theo phpunit.xml/TestCase (SQLite :memory:). Chrome fixture cần browser theo CHROME_PATH, dùng model/API fake; không thay smoke provider thật hoặc staging. Windows sandbox từng chặn đọc vendor/autoload.php, cần kiểm tra quyền execution thay vì sửa test hoặc DB.

Lệnh migrate, queue, scheduler và test trả phí chỉ chạy ở môi trường đã xác định, theo 07. Không lấy lệnh ví dụ trong tài liệu để chạy mù trên database thật.

## 8. Kiểm soát thay đổi và sai lệch

Một thay đổi ảnh hưởng API, trạng thái, quyền, schema, provider hoặc scope cần CR theo [mẫu](templates/CHANGE_REQUEST.md). Ghi lý do, FR ảnh hưởng, lựa chọn, tương thích, migration, tests và quyết định người có trách nhiệm.

Routine implementation theo baseline đã được giao không cần CR hay xác nhận lặp. Nếu phát hiện một lỗi thiết kế cục bộ, mô tả điều chỉnh và cập nhật hợp đồng trước/sát thay đổi, không che bằng workaround.

Không đổi test kỳ vọng chỉ để che regression. Khi lỗi tồn tại từ baseline, giữ bằng chứng và tách issue; không gọi repository sẵn sàng phát hành khi gate chất lượng vẫn chưa đạt.

## 9. Hồ sơ trước nghiệm thu

Dùng [UAT](15_TINH_HUONG_VA_UAT.md) và [risk register/gate](16_RUI_RO_VA_NGHIEM_THU.md). Biên bản có người thực hiện, môi trường, kết quả thực và lỗi còn mở; không đánh dấu đạt từ việc mới viết kịch bản. Hợp đồng, sơ đồ và task phải đồng bộ trước G2; nội dung Q đang mở chỉ chặn task phụ thuộc.
