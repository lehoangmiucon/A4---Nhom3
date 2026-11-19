# Kiểm chứng baseline tài liệu AI-SDLC-1.1

Ngày: 19/11/2026. Phạm vi: hồ sơ phát triển tính năng AI Helpdesk trong `docs/tài liệu AI`, đồng bộ quy tắc đọc tài liệu và task. Đây là kiểm chứng tài liệu, không phải nghiệm thu chức năng hoặc production.

## 1. Hồ sơ đã chuẩn bị

- 16 tài liệu chính: yêu cầu; kiến trúc; dữ liệu/API; an toàn/AI; quy trình; RTM/test; vận hành; vibe coding; quyết định; use case; business rules; quyền; UI; từ điển dữ liệu; tình huống/UAT; rủi ro/gate.
- 14 use case, 42 business rules; giữ 18 FR và 10 NFR hiện hành, 32 nhóm test case; 10 tình huống nội dung DRAFT và 14 ca UAT chưa thực hiện.
- 18 sơ đồ chi tiết: 1 actor/use case, 1 activity, 7 sequence, 2 ERD, 5 state, 1 component, 1 deployment. Sơ đồ component tổng quan ở 02 được giữ riêng; state diagram ở 02 chuyển sang dẫn chiếu nguồn duy nhất.
- 18 SVG tương ứng tại [danh mục bản xuất](../so-do/svg/README.md); 5 template task/CR/verification/test scenario/UAT; schema quyết định AI.
- Đồng bộ M2 receipt và outbox trước bàn giao; GET command có từ M2; M3 mở rộng kho command đó cho AI. Bổ sung Q-07 priority/SLA, không thay code ticket hiện tại.

## 2. Đối chiếu mã hiện tại

Dùng CodeGraph trước khi đọc mã liên quan, đối chiếu các nhánh tạo/đọc phiên và TicketController::store/assign/acknowledgeSchedule/staffUpdate/close/reopen/cancelSchedule. Hình CURRENT_CODE chỉ mô tả phần đã đọc. TARGET không phải trạng thái triển khai.

Các điểm đã sửa qua review tài liệu:

- Receipt TX-A tồn tại khi TX-B tạo report/phiếu/outbox thất bại; không nói đã tạo phiếu khi mới có receipt.
- Không đợi M3 mới có command status/recovery cho bàn giao M2.
- Report mới nối ticket assigned vẫn chưa được tiếp nhận; không lấy assignment cũ làm acknowledgement mới.
- Guide có in_review, từ chối về draft và published bất biến; tình huống mẫu không được tự phát hành.
- Auto-merge cần thiết bị và symptom_code đã xác nhận, đúng một ticket active; không dùng similarity AI làm quyết định.
- Command status customer chỉ thuộc chủ phiên; quyền report không tự cấp raw command/chat.
- Phiên context cũ không bị sửa ngầm khi phòng đã xóa; tạo phiên mới với phòng/mô tả người dùng xác nhận.

## 3. Kiểm tra cấu trúc và hợp đồng

Chạy Python đọc UTF-8, kiểm tra tất cả Markdown trong bộ tài liệu: liên kết tương đối, cặp code fence, số cột bảng và ký tự thay thế lỗi encoding. Kiểm tra tập ID yêu cầu trong 01 đều xuất hiện ở RTM 06; kiểm tra số UC/BR/UAT khai báo. Parse JSON và dùng `jsonschema.Draft202012Validator.check_schema` cho schema AI.

Fixture kiểm tra schema: 4 trường hợp hợp lệ theo bốn action; 6 trường hợp không hợp lệ gồm field ngoài hợp đồng, thiếu question_code cho ask_question, action lạ, summary quá 2.000 ký tự, thiếu field bắt buộc và hai field action mâu thuẫn. Cả 10 trường hợp cho kết quả mong đợi. Đây là validation cấu trúc, không chứng minh backend eligibility/policy đã được triển khai.

## 4. Kiểm tra sơ đồ

Công cụ: `@mermaid-js/mermaid-cli` **11.17.1**, Chrome headless có sẵn. Công cụ chỉ cài trong thư mục tạm; không thêm dependency hoặc lockfile vào ứng dụng.

Trình tự thực hiện:

1. Trích 18 khối Mermaid trong 7 file sơ đồ vào Markdown tạm, giữ thứ tự file/block.
2. Dựng bằng CLI với `-e svg -j 2 -p <puppeteer-config.json>`; executablePath trỏ Chrome của máy.
3. Kết quả: **18/18 dựng thành công**, kiểm tra XML SVG và đối chiếu ID nguồn/bản xuất.
4. Dựng riêng component tổng quan trong tài liệu 02: thành công. Tổng số khối Mermaid hiện hành được kiểm tra: **19**.
5. Xuất thêm PNG tạm và quan sát ba hình đại diện: sequence bàn giao, ERD TARGET, deployment. Các thành phần hiển thị; hình lớn nên đọc bằng SVG phóng to.

Lần đầu bị timeout khi khởi động Chrome trong sandbox; lần chạy được cấp quyền thành công cho thấy lỗi cú pháp dấu chấm phẩy trong message của sequence. Đã sửa nguồn, dựng lại toàn bộ thành công và chỉ sao chép bản xuất sau khi đạt. Worker fallback trong sequence được làm rõ không tự gửi HTTP 201 đến một client đã kết thúc request.

Khi sửa sơ đồ sau này: sửa Markdown nguồn, dựng lại, kiểm tra log và hình, thay bản SVG tương ứng; không sửa SVG bằng tay. Việc dựng thành công kiểm tra cú pháp/hình, không tự chứng minh đúng nghiệp vụ.

## 5. Giới hạn và phần còn chờ

- Không sửa hành vi ứng dụng, không chạy migration trên DB thật, không gọi API AI trả phí hoặc gửi thông báo thật.
- Không chạy lại test ứng dụng cho thay đổi tài liệu. Kết quả lịch sử M1a: 135 đạt, 1 lỗi nền; xem [evidence M1a](../../../tasks/verification-m1a.md). Không tuyên bố toàn suite xanh.
- Q-01–Q-07 cần người phụ trách thực tế xác nhận theo phần phụ thuộc: provider/model, ngân sách, nguồn guide/người duyệt, lịch trực, dữ liệu/backup, hạ tầng/push và priority/SLA.
- Chưa có 100 ca eval được duyệt, kết quả UAT thực tế hoặc biên bản ký nghiệm thu; đã có cấu trúc, kế hoạch và template để thực hiện.
- Bước triển khai kế tiếp vẫn là M1B-01 trong [task chính](../../../tasks/todo.md), sau khi kiểm tra lại hiện trạng; không nhảy thẳng sang agent trả phí.

Kết quả rà cuối: **33 Markdown, 18 SVG, 1 JSON schema; liên kết nội bộ, bảng và code fence hợp lệ; 28/28 yêu cầu có mặt trong RTM; 14 UC, 42 BR, 14 UAT; `git diff --check` không báo lỗi.** Các con số là trạng thái bộ tài liệu ở baseline này.
