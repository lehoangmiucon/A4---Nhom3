# 11 — Quy tắc nghiệp vụ và bảng quyết định

> TARGET được chuẩn hóa để triển khai; không khẳng định các luật mới đã có code. Luật CURRENT_CODE M1a vẫn do 03 mô tả.
> Đây là file sở hữu quyết định nghiệp vụ; sơ đồ diễn giải các luật này, không tự thêm luật.

## 1. Thứ tự ưu tiên hành động

Áp dụng theo thứ tự từ trên xuống sau kiểm tra actor/idempotency. Các điều kiện dừng không cần thêm lời gọi AI.

| Rule | Điều kiện | Hành động | Không làm |
| --- | --- | --- | --- |
| BR-01 | Replay một lệnh đã thành công của đúng actor | Trả kết quả đã lưu | Không chạy lại side effect hoặc kiểm tra version như intent mới |
| BR-02 | Không có quyền/tài khoản không hợp lệ | Từ chối theo contract | Không trả facts riêng |
| BR-03 | Có dấu hiệu nguy hiểm được báo hoặc thiếu điều kiện an toàn cho bước dự định | Ngừng bước liên quan, thông điệp đã duyệt, bàn giao | Không đề nghị tiến lại gần để xác minh |
| BR-04 | Người dùng yêu cầu gặp người thật/không thể làm | Chuyển trực tiếp khi đủ vị trí/mô tả | Không buộc thử thêm hoặc hỏi AI có đồng ý không |
| BR-05 | Phiên handed_off/cancelled | Theo trạng thái, không phát bước mới | Không nhận kết quả AI cũ để quay lại hướng dẫn |
| BR-06 | Ngoài phạm vi, không có guide hoặc hết giới hạn | Bàn giao mẫu xác định | Không bịa hướng dẫn |
| BR-07 | AI/quota/circuit/deadline không cho tiếp tục | Fallback theo UC-12 | Không chặn đường báo lỗi trực tiếp |
| BR-08 | Có một bước đang chờ kết quả | Chờ hoặc ghi kết quả bước đó | Không phát bước thứ hai đè lên |
| BR-09 | Thiếu facts và còn câu hỏi hợp lệ | Hỏi một câu trong tập đã duyệt | Không hỏi lại facts đã có |
| BR-10 | Có bước hợp lệ, đủ mọi điều kiện | Phát đúng một snapshot bước | Không coi guide ID hợp lệ là đủ |
| BR-11 | Không nhánh nào cho phép tiếp tục | Chuyển quản trị | Không vòng lặp suy đoán |

Không dùng bộ lọc từ khóa làm bộ phát hiện nguy hiểm duy nhất hoặc tuyên bố phát hiện tuyệt đối. Không chắc về điều kiện bắt buộc thì bước không đủ eligibility.

## 2. Bảng eligibility bước

| Published | Đúng thiết bị/model | Preconditions đủ và xác nhận | An toàn/quyền | Còn giới hạn | Kết quả |
| --- | --- | --- | --- | --- | --- |
| Không | Bất kỳ | Bất kỳ | Bất kỳ | Bất kỳ | Từ chối |
| Có | Không/không biết khi bắt buộc | Bất kỳ | Bất kỳ | Bất kỳ | Từ chối |
| Có | Có | Thiếu, còn câu hỏi an toàn hợp lệ | Có | Có | Hỏi facts, chưa phát bước |
| Có | Có | Thiếu và không thể hỏi tiếp | Bất kỳ | Bất kỳ | Bàn giao |
| Có | Có | Đủ | Không/không chắc | Bất kỳ | Bàn giao |
| Có | Có | Đủ | Có | Không | Bàn giao |
| Có | Có | Đủ | Có | Có | Ứng viên hợp lệ; kiểm tra lại khi commit |

Guide chưa biết model chỉ được dùng nếu phạm vi published nói rõ tương thích chung và các precondition còn lại đạt; không mặc định mọi model giống nhau.

## 3. Kết quả bước và hoàn tất

| Rule | Dữ liệu người dùng | State/result | Hành động |
| --- | --- | --- | --- |
| BR-12 | Xác nhận rõ đã hoạt động | resolved | Ghi bằng chứng xác nhận và cho quay lại |
| BR-13 | Đã thử, vẫn lỗi | not_fixed | Còn bước hợp lệ thì collecting; hết thì handoff |
| BR-14 | Không biết/không thể thao tác | cannot_perform | Handoff, không thử lại cùng bước |
| BR-15 | “Không chắc”, im lặng, thoát app | Chưa xác nhận thành công | Giữ chưa có kết quả; cung cấp hỗ trợ trực tiếp |
| BR-16 | Gửi lại đúng kết quả cũ | Replay | Không tăng số lần thử/notification lần hai |
| BR-17 | Attempt khác phiên hoặc đổi kết quả đã chốt | Từ chối/conflict | Không ghi đè history; yêu cầu lệnh bổ sung phù hợp |

## 4. Gộp sự cố

Đầu vào auto-match phải là thiết bị chính xác và symptom_code đã xác nhận từ taxonomy, không phải độ giống văn bản do model tự chấm. symptom_code thiếu/không chắc không auto-merge. Taxonomy và mapping được chuyên môn duyệt cùng guide.

| Rule | Điều kiện | Quyết định |
| --- | --- | --- |
| BR-18 | Cùng intent/report đã có | Replay/liên kết hiện tại, không report mới |
| BR-19 | Đúng thiết bị + cùng symptom_code + đúng một ticket active đủ điều kiện | Liên kết ticket, tạo report riêng |
| BR-20 | Cùng phòng nhưng khác thiết bị hoặc symptom_code khác | Không auto-merge |
| BR-21 | Thiếu thiết bị/symptom_code hoặc nhiều ứng viên | Không auto-merge; tạo phiếu riêng cần đối chiếu hoặc quản trị xem xét |
| BR-22 | Chỉ có ticket resolved/closed | Không coi là ticket active; ghi recurrence review |
| BR-23 | Chỉ có category/room giống nhau hoặc similarity từ AI | Không đủ bằng chứng tự gộp |

Active: new, reopened, assigned, in_progress. Việc so khớp áp dụng cho tất cả luồng đã chuyển sang nghiệp vụ dùng chung. Task tách service cũ không tự đổi luật gộp; task thay luật cần test trước/sau rõ.

Quản trị có thể liên kết lại các report về ticket chính khi xác minh cùng sự cố; giữ log, quyền và tham chiếu cũ, không hard-delete dữ liệu đã báo. Bản đầu có thể để việc này là thao tác quản trị chưa tự động thay vì tạo endpoint merge khi chưa có contract.

## 5. Ưu tiên và mức ảnh hưởng

Không thay đổi ngầm policy ticket hiện có. CURRENT_CODE: teacher nhận high khi tạo qua TicketController; mô tả của model không quyết định priority.

| Dữ liệu | Quy tắc TARGET |
| --- | --- |
| Người dùng bấm hỗ trợ trực tiếp | Ghi reason=user_requested, không tự urgent |
| Đang có lớp | Lưu impact=class_in_progress nếu người dùng xác nhận; phục vụ sắp hàng theo policy đã duyệt |
| Thiếu facts mức ảnh hưởng | impact=unknown, không đoán số sinh viên hoặc thời gian |
| Báo nguy hiểm | safety_flag riêng, route cảnh báo/người trực; thông điệp chuyên môn đã duyệt |
| Teacher + không có override đã duyệt | Giữ high như code hiện tại |
| Actor khác | Backend áp dụng policy đã chốt, không lấy priority từ output model |
| Đề xuất urgent do safety | Chỉ triển khai sau khi chính sách override Q-07 được chốt; chưa được chốt thì không tự sửa code hoặc coi SLA đã cam kết |

SLA an toàn/gián đoạn trong tài liệu 07 là mục tiêu đề xuất theo mức ảnh hưởng; trường phải xác nhận mapping đến priority trước mở vận hành. Đây là phần còn cần chuyên môn xác nhận, không phải khoảng trống cho agent tự quyết.

## 6. Bàn giao và hai giao dịch local

Chọn thiết kế TARGET sau để không mâu thuẫn giữa handoff_pending bền vững và rollback tạo phiếu:

1. **TX-A nhận intent:** khóa phiên, kiểm tra actor/key/version, lưu handoff command pending và metadata tối thiểu, tăng version, session=handoff_pending. Không gọi model, không tạo ticket, không outbox thông báo “đã có phiếu”.
2. **TX-B hoàn tất ngay trong request:** khóa receipt/phiên, tìm replay, tạo report/ticket/log/outbox và đánh dấu handed_off/command succeeded trong cùng transaction.
3. Thành công TX-B trả 201 Handoff. Notification xử lý sau commit.
4. Nếu TX-B lỗi, TX-A vẫn giữ receipt để không tiếp tục AI. Trả 503 kèm mã lệnh và URL trạng thái đã kiểm tra quyền; không trả 201 hoặc nói đã tạo.
5. Client tra command hoặc retry cùng key; worker phục hồi có thể tiếp tục TX-B từ receipt, không tạo report thứ hai.
6. Nếu TX-A không commit, không khẳng định server đã nhận intent. Client giữ key để retry.
7. Receipt handoff dùng command/status endpoint TARGET M2, không đợi M3 mới có đường đọc trạng thái. Command persistence có thể dùng chung sau này, không có queue gọi AI trong M2.

Guard expected_version chỉ áp dụng cho intent mới; replay đã thành công trả kết quả cũ. Khi intent chưa có nhưng version stale, trả 409 và tải lại; không chạy lệnh ngầm. idempotency fingerprint của handoff gồm actor/session/reason, không gồm expected_version vì version là precondition có thể cập nhật sau conflict; nếu thay reason thì là payload khác. Mọi retry phải xác nhận actor/intent phù hợp, không lấy “key có tồn tại” làm quyền.

M2 contract cần bổ sung command status và lỗi receipt; cập nhật tại 03. Đây là thiết kế TARGET, không sửa bảng idempotency M1a đã có để giả như đủ pending states.

## 7. Acknowledgement và phân công

| Rule | Tình huống | Hành vi |
| --- | --- | --- |
| BR-24 | Report mới, kể cả liên kết ticket đang assigned | acknowledgement của report mới vẫn chưa có |
| BR-25 | Quản trị tiếp nhận report | Ghi actor/time/version của report handoff |
| BR-26 | Phân công trực tiếp | Ghi nhận tiếp nhận các report được thao tác nêu rõ; không auto-ack report tương lai |
| BR-27 | Đã tiếp nhận nhưng chưa phân công | Giữ trạng thái đó, deadline phân công riêng |
| BR-28 | Không có KTV phù hợp/ngoài giờ | Giữ queue, cảnh báo theo lịch trực/contact đã cấu hình |
| BR-29 | Đọc notification/push delivered | Không thay acknowledgement |
| BR-30 | Đổi KTV khi lịch đã acknowledged | Theo rule hủy/đổi lịch hiện có, không override bằng AI |

Progress hiển thị riêng report acknowledgement và ticket progress. acknowledged_at trong DTO là mốc report hiện tại, assigned_at là mốc ticket; không dùng assignment cũ để nói lượt báo mới đã được đọc.

## 8. Quay lại và tái diễn

| Rule | Trạng thái | Yêu cầu | Kết quả |
| --- | --- | --- | --- |
| BR-31 | Session resolved, chưa report | Sự cố vẫn còn | Mở lại cùng session, tăng version, giữ counter/history |
| BR-32 | Session handed_off, ticket active | Bổ sung thông tin/còn lỗi | Follow-up của report, không quay lại hướng dẫn |
| BR-33 | Ticket resolved/closed | Cần xử lý lại | Recurrence/reopen review, chỉ quản trị quyết định |
| BR-34 | Khác thiết bị hoặc lỗi mới | Báo mới | Session/report mới; linkage lịch sử khi có căn cứ |
| BR-35 | Bấm nhầm trong 10 phút | Hoàn tác nhanh | Shortcut vào BR-31/32/33; không xóa audit |
| BR-36 | Sau 10 phút | Vẫn còn sự cố | Vẫn có đường báo tiếp, không khóa người dùng |

Không tự gọi TicketController::reopen từ lệnh session; không xóa ảnh hoặc sửa ngược facts của lần hỗ trợ cũ.

## 9. Quy tắc guide và dữ liệu

- BR-37: draft → in_review → published; trả về draft khi bị từ chối; published chỉ chuyển withdrawn, không sửa nội dung tại chỗ.
- BR-38: muốn phát hành bản thay thế phải có version mới và điều kiện tương thích; rút bản cũ không xóa snapshot đã phát.
- BR-39: guide thiếu nguồn/metadata/người duyệt chỉ được dùng fixture trong test, không published cho người dùng.
- BR-40: facts unknown được giữ unknown; không tự lấp bằng dự đoán model.
- BR-41: dữ liệu gốc, summary AI và kết luận KTV có provenance riêng.
- BR-42: thời gian lưu/xóa theo policy; không xóa receipt/key còn bảo vệ retry hoặc thay FK để né lỗi.

## 10. Quy tắc nào còn cần xác nhận bên ngoài?

Đã định nghĩa default thiết kế để lập trình các nhánh độc lập. Provider/ngân sách, nguồn guide, người trực/contact, priority/SLA mapping và retention/backup vẫn cần người phụ trách thực tế. Theo dõi Q-01–Q-07 ở 09; không gắn tên người hay nội dung duyệt giả.
