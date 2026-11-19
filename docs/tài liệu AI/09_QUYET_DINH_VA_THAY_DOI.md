# 09 — Quyết định, giả định và lịch sử

## 1. Quyết định đã chốt

| ID | Quyết định | Hệ quả |
| --- | --- | --- |
| D-01 | API AI trả theo usage, không tự vận hành/fine-tune trong phiên bản này | Không thêm Python inference/GPU hosting vào task |
| D-02 | Giữ Laravel và cấu trúc hiện tại, web/Flutter dùng chung API | Không tách microservices/đổi frontend framework tùy tiện |
| D-03 | Máy chiếu và micro, văn bản, khoảng 10 tình huống đầu | Thiết bị khác đi form trực tiếp; không bịa guide |
| D-04 | Backend sở hữu quyền, trạng thái và tác dụng phụ | Model chỉ đề xuất; mọi hành động kiểm tra ở server |
| D-05 | Đường bàn giao không cần AI phải có trước mở agent | Model lỗi/quota không làm mất khả năng báo lỗi |
| D-06 | M1a đã có code/test, chưa deployed hoặc có UI | Không báo hoàn thành toàn M1 hoặc sản phẩm |
| D-07 | Bộ docs/tài liệu AI là baseline hiện hành | Tài liệu cá nhân cũ giữ lịch sử; nhiệm vụ theo tasks/todo.md |
| D-08 | Session và Ticket có vòng đời khác nhau | Không mở/đóng/xóa minh chứng phiếu bằng lệnh phiên |
| D-09 | Idempotency và gộp sự cố là hai bài toán khác nhau | Một intent không nhân đôi; mỗi người báo vẫn có report riêng |
| D-10 | Android Flutter là mục tiêu app bản đầu | iOS/voice/AI vision cần quyết định mở rộng riêng |
| D-11 | Bàn giao TARGET dùng TX-A receipt và TX-B tạo/liên kết report/phiếu/outbox | TX-B lỗi không mất intent đã nhận; command status và recovery có từ M2, không đợi AI M3 |
| D-12 | Bộ sơ đồ và quy tắc chi tiết là phần thiết kế của baseline 1.1 | Mermaid là nguồn; SVG là bản xuất, cần dựng lại khi sửa nguồn; CURRENT và TARGET luôn tách rõ |

Tham khảo lịch sử: [ADR API](<../../tài liệu cá nhân/decisions/001-hosted-ai-api.md>). ADR đó giải thích quyết định D-01, không thay thế hợp đồng mới ở tài liệu 03.

## 2. Quyết định còn thiếu trước môi trường thật

| ID | Điều cần chốt | Người/đầu mối cần cung cấp | Chặn phần nào |
| --- | --- | --- | --- |
| Q-01 | Provider, model ID, tài khoản và quyền API | Chủ dự án/người quản lý tài khoản | Gọi API thật và release, không chặn fake/UI |
| Q-02 | Ngân sách, quota, context token tối đa và concurrency thực | Chủ dự án + vận hành | Bật AI trả phí |
| Q-03 | Người duyệt guide, nội dung thực và metadata thiết bị | Bộ phận kỹ thuật | Hướng dẫn người dùng thật |
| Q-04 | Giờ trực, người thay thế, contact và thời hạn tiếp nhận/phân công | Quản trị nhà trường | Bàn giao có cam kết vận hành |
| Q-05 | Retention, quyền riêng tư, provider processing, backup/RPO/RTO | Chủ quản dữ liệu + vận hành | Production dữ liệu thật |
| Q-06 | Server, MySQL/queue/cache, push và thiết bị kiểm thử | Nhóm triển khai | Staging/load/push/restore |
| Q-07 | Ánh xạ safety_flag sang priority/SLA, quyền nâng ưu tiên và chính sách ngoài giờ | Quản trị + bộ phận kỹ thuật | Thay chính sách priority/SLA; hiện teacher vẫn high theo code, không tự thêm critical |

Các giá trị pilot trong tài liệu là đề xuất cần kiểm chứng. Không tự điền thông tin giả để đánh dấu gate xong. Không yêu cầu trả lời cả bảy mục nếu task hiện tại chỉ là UI đọc phiên.

## 3. Nguyên tắc quản lý thay đổi

CR ghi rõ: mô tả trước/sau, lý do, FR/NFR/TC/task ảnh hưởng, API/state/schema, tương thích, dữ liệu cần chuyển, chi phí, test, rủi ro và quyết định.

- Thay đổi nhỏ đúng baseline: cập nhật task/evidence, không tạo CR hình thức.
- Thay đổi quyền/scope/breaking API hoặc chính sách vận hành: ghi quyết định của người chịu trách nhiệm trước phần phụ thuộc.
- Sửa lỗi an toàn có thể tắt feature/thu hồi guide trong phạm vi vận hành được giao; vẫn lưu bằng chứng và cập nhật sau.
- Không xóa quyết định cũ: đánh dấu superseded, dẫn quyết định thay thế.
- Nếu nhiều tài liệu cùng nhắc một nội dung, sửa file sở hữu rồi sửa liên kết/tóm tắt liên quan.

## 4. Nhật ký baseline

Vấn đề đang mở BASE-001: lỗi truy vấn lặp trong UiPerformanceTest đã có trước M1a; theo dõi ở tasks/todo.md. Không dùng lỗi này để bỏ qua kiểm thử, không gọi toàn repository đã xanh. Chưa có kết luận đã khắc phục.

| Phiên bản | Ngày | Nội dung |
| --- | --- | --- |
| Kế hoạch cá nhân 1.0 | 19/11/2026 | API AI, sáu giai đoạn triển khai |
| Kế hoạch cá nhân 2.0 | 19/11/2026 | Bổ sung state, rủi ro, contract và nghiệm thu |
| M1a code checkpoint | 19/11/2026 | API lưu/đọc phiên, 16 test; 135 pass/1 lỗi nền toàn suite |
| AI-SDLC-1.0 | 19/11/2026 | Tách bộ tài liệu hiện hành, requirement IDs, RTM, task và quy tắc vibe coding |
| AI-SDLC-1.1 | 19/11/2026 | Use case, business rules, quyền, UI, từ điển dữ liệu, UAT/rủi ro và sơ đồ; làm rõ receipt bàn giao, tiếp nhận report và duyệt guide |

AI-SDLC-1.1 chỉ bổ sung tài liệu và quy tắc làm việc; thiết kế TARGET chưa được triển khai. Không migrate DB hoặc gọi API AI trả phí trong lần chuẩn bị tài liệu này.

Lần biên soạn AI-SDLC-1.0 chỉ thay tài liệu/quy tắc làm việc, không triển khai thêm chức năng ứng dụng, không migrate DB và không gọi API trả phí.
