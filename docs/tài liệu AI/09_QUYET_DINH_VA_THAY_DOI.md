# 09 — Quyết định, giả định và lịch sử

## 1. Quyết định đã chốt

| ID | Quyết định | Hệ quả |
| --- | --- | --- |
| D-01 | API AI trả theo usage cho baseline production; ngoại lệ LOCAL-AI-01 được người dùng chốt ngày 20/11/2026: dùng Ollama offline trên máy để test | Laravel giữ nguyên; thêm provider local tùy chọn, không fine-tune hoặc tự thay production. Xem D-13 |
| D-02 | Giữ Laravel và cấu trúc hiện tại, web/Flutter dùng chung API | Không tách microservices/đổi frontend framework tùy tiện |
| D-03 | Phạm vi guide chuyên môn TARGET ban đầu là máy chiếu/micro, khoảng 10 tình huống | CR-13 đã mở tập quan sát sơ bộ cho máy tính/máy chiếu/mạng/âm thanh; không coi đó là guide được duyệt hoặc phạm vi production tự mở rộng |
| D-04 | Backend sở hữu quyền, trạng thái và tác dụng phụ | Model chỉ đề xuất; mọi hành động kiểm tra ở server |
| D-05 | Đường bàn giao không cần AI phải có trước mở agent | Model lỗi/quota không làm mất khả năng báo lỗi |
| D-06 | Checkpoint M1a ban đầu chỉ có API/test; đã được mở rộng bằng UI M1b và CR-01–13 | UI/current provider/migration local nay đã có evidence theo README; vẫn chưa có staging/release nghiệm thu |
| D-07 | Bộ docs/tài liệu AI là baseline hiện hành | Tài liệu cá nhân cũ giữ lịch sử; nhiệm vụ theo tasks/todo.md |
| D-08 | Session và Ticket có vòng đời khác nhau | Không mở/đóng/xóa minh chứng phiếu bằng lệnh phiên |
| D-09 | Idempotency và gộp sự cố là hai bài toán khác nhau | Một intent không nhân đôi; mỗi người báo vẫn có report riêng |
| D-10 | Android Flutter là mục tiêu app bản đầu | iOS/voice/AI vision cần quyết định mở rộng riêng |
| D-11 | Bàn giao TARGET dùng TX-A receipt và TX-B tạo/liên kết report/phiếu/outbox | TX-B lỗi không mất intent đã nhận; command status và recovery có từ M2, không đợi AI M3 |
| D-13 | LOCAL-AI-01: Ollama/qwen2.5:7b cho thử nghiệm offline theo yêu cầu trực tiếp của người dùng | .env local chọn ollama, repository mặc định openai; không cloud fallback. [Evidence](../../tasks/verification-local-ai.md), [hướng dẫn](../local-ai.md) |
| D-14 | CR-01–10: hai chế độ thủ công/chat lưu server; report tại chỗ, brief/fallback và chống trùng phòng/danh mục; sửa UX và spinner | Thay luồng điền nháp của portal; không nhầm chặn trùng với gộp có căn cứ hoặc outbox |
| D-15 | CR-11: xóa mềm phiên handed_off/report/ticket closed; không xóa hồ sơ phiếu | resolved của ticket còn chờ quản lý; can_delete tính từ backend; không phải purge |
| D-16 | CR-12: biết phòng, công việc/danh mục và mô tả trước hướng dẫn; CR-13 hỏi quan sát theo nhánh, nhận ý định chuyển KTV tự nhiên | Backend chọn câu hỏi và side effect; model hỗ trợ diễn đạt/brief, không tự cấp quyền; schema quyết định guide vẫn TARGET |
| D-17 | AI-SDLC-1.2: đồng bộ tài liệu đến CR-13 trước push theo yêu cầu người dùng | Giữ checkpoint lịch sử, todo là nguồn task duy nhất; review tài liệu không tự commit/push hoặc triển khai production |
| D-12 | Bộ sơ đồ và quy tắc chi tiết là phần thiết kế của baseline 1.1 | Mermaid là nguồn; SVG là bản xuất, cần dựng lại khi sửa nguồn; CURRENT và TARGET luôn tách rõ |

Thứ tự D-12/D-13 giữ ID lịch sử, không đánh lại mã. D-14–17 bổ sung yêu cầu đã chốt, không xóa lý do D-01–13.

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

BASE-001 đã khắc phục sau M1a; bằng chứng review M1b và CR-01 ở tasks/verification-m1b.md, tasks/verification-cr01.md. Giữ kết quả lỗi nền cũ làm lịch sử, không dùng nó làm trạng thái hiện tại.

| Phiên bản | Ngày | Nội dung |
| --- | --- | --- |
| Kế hoạch cá nhân 1.0 | 19/11/2026 | API AI, sáu giai đoạn triển khai |
| Kế hoạch cá nhân 2.0 | 19/11/2026 | Bổ sung state, rủi ro, contract và nghiệm thu |
| M1a code checkpoint | 19/11/2026 | API lưu/đọc phiên, 16 test; 135 pass/1 lỗi nền toàn suite |
| AI-SDLC-1.0 | 19/11/2026 | Tách bộ tài liệu hiện hành, requirement IDs, RTM, task và quy tắc vibe coding |
| CR-01 CURRENT_CODE | 20/11/2026 | Theo yêu cầu mới: hai chế độ báo thủ công/chat AI, gửi phiếu ngay trong chat; receipt/version và DB notification atomic. Không dùng popup điền nháp làm luồng chính. Contract delta ở 03 mục 2.1; không tuyên bố hoàn thành toàn M2/M3/M4 hoặc RELEASED |
| LOCAL-AI-01 | 20/11/2026 | Provider Ollama qwen2.5:7b chạy offline test local; 162 passed / 2130 assertions |
| CR-02 / CR-02D | 20/11/2026 | Bản tóm tắt và gợi ý nhanh cho KTV kèm hash nguồn, enum U1/U2 tham chiếu lời người báo; 180 passed / 2235 assertions |
| CR-03 | 20/11/2026 | Đồng bộ chặn phiếu AI trùng cùng phòng/danh mục chưa hoàn tất; 192 passed / 2396 assertions |
| CR-04–10 | 20/11/2026 | Danh mục hiện tại, fallback IT, trạng thái gửi/busy/typewriter, tăng quota 40/12/200 và giữ status DOM; evidence theo từng CR trong tasks |
| CR-11 | 20/11/2026 | Xóa mềm phiên báo cáo đã hoàn tất; migration local batch 17 |
| CR-12 | 20/11/2026 | Intake phòng/công việc/mô tả trước model, clarify không tính quota chat |
| CR-13 | 20/11/2026 | Quan sát từng nhánh, không lặp nguồn, bàn giao KTV tự nhiên; 276 PHP/2892 assertions, 62 JS; model mới chỉ fake |
| AI-SDLC-1.2 | 20/11/2026 | Rà soát CURRENT_CODE/TARGET, hướng dẫn nhận việc/local/API, contract/quyền/RTM/giới hạn; chỉ đồng bộ tài liệu, chưa release |
| AI-SDLC-1.1 | 19/11/2026 | Use case, business rules, quyền, UI, từ điển dữ liệu, UAT/rủi ro và sơ đồ; làm rõ receipt bàn giao, tiếp nhận report và duyệt guide |

AI-SDLC-1.1 chỉ bổ sung tài liệu và quy tắc làm việc; thiết kế TARGET chưa được triển khai. Không migrate DB hoặc gọi API AI trả phí trong lần chuẩn bị tài liệu này.

Lần biên soạn AI-SDLC-1.0 chỉ thay tài liệu/quy tắc làm việc, không triển khai thêm chức năng ứng dụng, không migrate DB và không gọi API trả phí.
