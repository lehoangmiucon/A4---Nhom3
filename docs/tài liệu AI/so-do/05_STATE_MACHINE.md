# State machines

File này sở hữu biểu diễn sơ đồ. Luật chuyển trạng thái ở [02](../02_KIEN_TRUC.md), [11](../11_QUY_TAC_NGHIEP_VU.md); không suy thêm chuyển trạng thái từ cách bố trí hình.

## DG-ST-01 — Session TARGET

Delta CURRENT_CODE CR-13: diagnosis.stage là projection quan sát riêng, không thay enum session hoặc đánh dấu resolved. collecting_observations hỏi từng quan sát → ready_for_it khi hết câu hỏi/chưa thể tự xác định; safety_stop ưu tiên dừng. Chỉ yêu cầu report rõ ràng mới đi handoff_pending → handed_off qua giao dịch hiện có. Không đồng nhất ready_for_it với đã gửi phiếu. SVG baseline chưa biểu diễn projection này; contract tại 03 và evidence CR-13.

UC-01–06/09; TC-007/010/011/017/025. Tên enum đã có trong model M1a nhưng phần lớn transition chưa có code.

```mermaid
stateDiagram-v2
    [*] --> collecting
    collecting --> awaiting_result: Phát bước hợp lệ
    awaiting_result --> collecting: not_fixed và còn phạm vi
    collecting --> resolved: Xác nhận rõ đã được
    awaiting_result --> resolved: resolved do người dùng
    collecting --> handoff_pending: TX-A nhận intent
    awaiting_result --> handoff_pending: TX-A nhận intent
    handoff_pending --> handed_off: TX-B commit
    handoff_pending --> handoff_pending: Retry cùng intent khi final lỗi
    resolved --> collecting: Mở lại, giữ counter
    collecting --> cancelled: Hủy trước bàn giao
    awaiting_result --> cancelled: Hủy trước bàn giao
    cancelled --> [*]
    note right of handed_off
        Theo dõi hoặc follow-up
        Không quay lại hướng dẫn
    end note
```

resolved không đóng ticket. Không có bước AI về collecting từ handed_off/cancelled. Receipt lỗi cuối giữ handoff_pending, không tự resolved.

## DG-ST-02 — Command TARGET

UC-02/04/12; TC-006/021/025/026. Command receipt handoff có từ M2; lượt AI tái sử dụng ở M3.

```mermaid
stateDiagram-v2
    [*] --> pending
    pending --> processing: Claim lease
    pending --> discarded: Hết deadline AI hoặc state đổi
    processing --> succeeded: Kết quả hợp lệ commit
    processing --> discarded: Snapshot cũ hoặc AI quá hạn
    processing --> failed: Lỗi cuối hoặc không thể tự tiếp tục
    processing --> pending: Handoff lỗi tạm, trả lease
    failed --> pending: Handoff retry có kiểm soát
    succeeded --> [*]
    discarded --> [*]
```

Hai nhánh về pending chỉ dành cho bàn giao/local recovery theo retry policy; không dùng để hồi sinh lượt AI đã hết deadline hoặc bỏ qua quota. succeeded chỉ replay, không chạy side effect lại. Failed cần lý do, owner và cơ chế xử lý, không tự mở retry vô hạn.

## DG-ST-03 — Guide version TARGET

UC-10; TC-008/009/023.

```mermaid
stateDiagram-v2
    [*] --> draft
    draft --> in_review: Gửi duyệt
    in_review --> draft: Từ chối kèm lý do
    in_review --> published: Người duyệt chấp nhận
    published --> withdrawn: Thu hồi có lý do
    withdrawn --> [*]
    note right of published
        Nội dung bất biến
        Muốn sửa tạo version mới
    end note
```

Không có published→draft cho cùng version. Fixture chưa duyệt không được dùng production.

## DG-ST-04 — Acknowledgement của report TARGET

UC-07/11; TC-015/016. Đây là report acknowledgement, không phải ticket status.

```mermaid
stateDiagram-v2
    [*] --> unacknowledged
    unacknowledged --> unacknowledged: Nhắc hoặc chuyển người trực quá hạn
    unacknowledged --> acknowledged: Lệnh admin hoặc phân công nêu rõ report
    acknowledged --> acknowledged: Theo dõi deadline phân công
    note right of acknowledged
        Có actor và timestamp
        Không đồng nghĩa đã sửa
    end note
```

Một report mới liên kết ticket đã assigned vẫn bắt đầu unacknowledged. Không đảo về unacknowledged chỉ vì đọc lại hoặc đổi KTV; audit giữ người đã tiếp nhận.

## DG-ST-05 — Ticket CURRENT_CODE, các chuyển chính đã đối chiếu

Nguồn TicketController::store/assign/acknowledgeSchedule/staffUpdate/close/reopen/cancelSchedule. UC-07/08/09. Đây là phần workflow phiếu liên quan module, không phải migration đổi state.

```mermaid
stateDiagram-v2
    [*] --> new: Tạo phiếu
    new --> assigned: Admin phân công
    reopened --> assigned: Admin phân công lại
    assigned --> assigned: Sửa phân công hợp lệ
    assigned --> in_progress: KTV xác nhận lịch
    reopened --> in_progress: KTV xác nhận lịch còn hợp lệ
    in_progress --> in_progress: Cập nhật tiến độ hợp lệ
    in_progress --> resolved: Gửi kết quả và ảnh
    resolved --> closed: Admin kiểm tra điều kiện và duyệt
    resolved --> reopened: Admin yêu cầu xử lý lại
    closed --> reopened: Admin yêu cầu xử lý lại
    assigned --> new: Hủy lịch cuối, bỏ assignment
    reopened --> reopened: Hủy lịch cuối, bỏ assignment
```

KTV cập nhật cần lịch đang acknowledged theo code; resolved cần ảnh; close cần các điều kiện hiện có. Reopen hiện xóa ảnh sửa chữa cũ: module AI không gọi thay quyền quản trị. Không thêm trạng thái Open/InProgress hoặc acknowledged vào ticket chỉ vì tên trên sơ đồ khác.
