# 16 — Rủi ro và checklist hoàn chỉnh

## 1. Sổ rủi ro

| Risk | Kịch bản | Kiểm soát | Owner theo trách nhiệm | Bằng chứng |
| --- | --- | --- | --- | --- |
| R-01 | Model bịa hoặc chọn sai thao tác | Eligibility, published immutable, render nội dung cố định | Kỹ thuật + backend | TC-008/009/024 |
| R-02 | Hỏi dây dưa khi đang dạy | Giới hạn câu hỏi/bước/thời gian, hỗ trợ trực tiếp | Product + UI/backend | TC-007/011/018 |
| R-03 | Bấm nhầm hoàn tất | Xác nhận, mở lại/follow-up, không sửa phiếu trái quyền | Product + backend | TC-017 |
| R-04 | Retry tạo nhiều phiên/phiếu | Key, receipt, unique, TX ngắn | Backend + QA | TC-005/006 |
| R-05 | Gộp sai sự cố hoặc mất lượt báo | Device/symptom xác nhận, report riêng, review khi mơ hồ | Quản trị + backend | TC-013/014 |
| R-06 | JSON hợp lệ nhưng lạm quyền | Allowlist, policy/state/precondition phía server | Backend/security | TC-023/024 |
| R-07 | IDOR qua path/parent/ảnh/command | Parent-scoped policy, private storage, serializer riêng | Backend/security | TC-023 |
| R-08 | Cạn ngân sách qua nhiều worker | Reservation shared, quota/concurrency/deadline | Vận hành + backend | TC-027 |
| R-09 | Provider lỗi hoặc kết quả muộn | Deadline, circuit, fallback, version check | Backend + vận hành | TC-021/025 |
| R-10 | Receipt có nhưng final commit thất bại | Hai TX, poll quyền, recovery idempotent | Backend + vận hành | TC-006/026 |
| R-11 | Có phiếu không ai nhận | Lịch trực, report acknowledgement, deadline và người thay | Quản trị | TC-015/016 |
| R-12 | Báo mới nối ticket cũ rồi giả đã tiếp nhận | Tách report acknowledgement khỏi ticket progress | Backend + UI | TC-014/015, UAT-13 |
| R-13 | Lộ chat qua log/API/push/ảnh | Data minimization, policy, private, preview tối thiểu | Chủ quản dữ liệu + dev | TC-020/028 |
| R-14 | App/server mất mạng, request không rõ kết quả | Stable key/ID, status thật, contact xác nhận | Mobile/backend | TC-019/021 |
| R-15 | Thay model/prompt làm sai hành vi | Version và eval hồi quy | Người duyệt + dev | TC-032 |
| R-16 | Backup không khôi phục được/rollback mất dữ liệu | Restore staging, feature flag, migration tương thích | Vận hành | TC-031 |

Tên người phụ trách thực tế được chốt trước pilot. Việc ghi một dòng kiểm soát không có nghĩa kiểm soát đã có code/test.

## 2. Checklist sẵn sàng phát triển

- [ ] Task có FR/NFR, UC/BR/TC và contract liên quan.
- [ ] Biết phần nào CURRENT_CODE, phần nào TARGET.
- [ ] Quan hệ dữ liệu, state guard và quyền không mâu thuẫn.
- [ ] Failure/retry/concurrency/side effect được mô tả.
- [ ] Có test oracle và phạm vi file; không phụ thuộc endpoint chưa triển khai.
- [ ] Các quyết định còn thiếu không ảnh hưởng task hoặc được ghi là blocker thật.

Những task độc lập như M1b có thể tiếp tục khi chưa chốt ngân sách/provider. Không coi đây là yêu cầu hỏi lại mọi quyết định đã được người dùng giao.

## 3. Checklist sẵn sàng mở cho người dùng

- [ ] UC-01–13 trong phạm vi release đi được trên web/Android, không chỉ API.
- [ ] Nút hỗ trợ trực tiếp và form truyền thống hoạt động khi model tắt/lỗi.
- [ ] Guide published có nguồn/người duyệt/metadata thực và check lúc phát.
- [ ] Mọi nhánh nguy hiểm, sai quyền và dữ liệu người khác bị chặn trong test.
- [ ] Receipt/report/ticket/outbox nhất quán, MySQL concurrency có evidence.
- [ ] Không có 202 hoặc status_url giả; pending/failed/succeeded phản ánh dữ liệu thật.
- [ ] Quản trị/KTV nhận việc, deadline và lịch trực có owner.
- [ ] Không tự xóa ảnh/reopen ticket khi giảng viên mở lại phiên.
- [ ] AI eval đạt ngưỡng, lỗi nghiêm trọng/cao đã xử lý.
- [ ] Monitoring, quota/cost, private data và retention được kiểm chứng.
- [ ] Có backup/restore, rollback/feature flag và runbook.
- [ ] BASE-001 và các issue còn mở có trạng thái/đánh giá rõ trước release.
- [ ] Version môi trường và người nghiệm thu được ghi thực tế.

## 4. Checklist tính đầy đủ của tài liệu

- [ ] FR/NFR có acceptance criteria và RTM.
- [ ] UC có actor, trigger, precondition, main/alternate/exception, postcondition.
- [ ] BR có đầu vào rõ và quyết định, không chỉ nguyên tắc chung.
- [ ] Permission matrix gồm cả tài nguyên con và quyền người báo sau.
- [ ] API xác định input/output/error, idempotency, version và trạng thái hiện có/đích.
- [ ] ERD và dictionary thống nhất, không vẽ FK/ORM giả.
- [ ] Sequence chỉ rõ commit, retry, queue, timeout và kết quả cũ.
- [ ] State machines tách session/command/guide/report/ticket.
- [ ] UI có empty/loading/error/network/permission/accessibility.
- [ ] Guide/eval/UAT có nguồn và phân biệt draft với duyệt thật.
- [ ] Task và evidence có nguồn duy nhất, hồ sơ thay đổi không phủ định lịch sử.
- [ ] Các Q bên ngoài được theo dõi, không điền giả để báo hoàn chỉnh.

Checklist này phục vụ review, không tự đánh dấu PASS vì file đã tồn tại. Bằng chứng biên soạn nằm trong verification, không đồng nghĩa nghiệm thu sản phẩm.
