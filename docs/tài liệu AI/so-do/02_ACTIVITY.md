# Activity view có lane

DG-ACT-01 — TARGET, luồng chính và nhánh dừng. Lane thể hiện trách nhiệm, không thay chi tiết transaction ở sequence. Truy vết UC-01–09; BR-01–36; TC-007–018/021.

```mermaid
flowchart TD
    subgraph USER["Giảng viên"]
        S(["Bắt đầu"])
        LOC["Xác nhận phòng và mô tả"]
        DIRECT{"Muốn hỗ trợ trực tiếp?"}
        ANSWER["Trả lời một câu hỏi"]
        RESULT{"Kết quả bước"}
        CONFIRM["Xác nhận đã hoạt động"]
        TRACK["Theo dõi / bổ sung / báo còn lỗi"]
    end
    subgraph BACK["Laravel và điều phối"]
        SAVE["Lưu phiên và tin gốc"]
        CHECK{"Được tiếp tục hướng dẫn?"}
        NEED{"Thiếu facts?"}
        ASK["Phát một câu hỏi đã duyệt"]
        ELIG{"Có bước đủ điều kiện?"}
        STEP["Phát một snapshot bước"]
        DONE["Session resolved"]
        RECEIPT["TX-A: receipt + handoff_pending"]
        COMMIT["TX-B: report / ticket / log / outbox"]
        STATUS["Hiển thị mã phiếu và trạng thái thật"]
        WAIT["Pending hoặc chưa xác nhận; retry an toàn"]
    end
    subgraph MODEL["API AI"]
        SELECT["Đề xuất mã câu hỏi / bước trong tập ứng viên"]
    end
    subgraph STAFF["Quản trị và KTV"]
        ACK["Quản trị tiếp nhận report"]
        ASSIGN["Phân công người phù hợp"]
        REPAIR["KTV xác nhận lịch và xử lý"]
        REVIEW["Quản trị kiểm tra và đóng theo nghiệp vụ"]
    end
    subgraph ASYNC["Worker / scheduler"]
        NOTICE["Gửi thông báo từ outbox"]
        REMIND["Nhắc / chuyển người trực khi quá hạn"]
    end
    S --> LOC --> SAVE --> DIRECT
    DIRECT -->|Có| RECEIPT
    DIRECT -->|Không| CHECK
    CHECK -->|Không: nguy hiểm, hết giới hạn, model lỗi| RECEIPT
    CHECK -->|Có| NEED
    NEED -->|Có, còn câu hỏi| SELECT --> ASK --> ANSWER --> CHECK
    NEED -->|Đủ facts| ELIG
    ELIG -->|Không| RECEIPT
    ELIG -->|Có| STEP --> RESULT
    RESULT -->|Đã được| CONFIRM --> DONE --> TRACK
    RESULT -->|Chưa được và còn giới hạn| CHECK
    RESULT -->|Không thể hoặc hết bước| RECEIPT
    RECEIPT --> COMMIT
    COMMIT -->|Commit thành công| STATUS --> TRACK
    COMMIT -->|Lỗi| WAIT --> RECEIPT
    COMMIT -->|Outbox đã commit| NOTICE --> ACK --> ASSIGN --> REPAIR --> REVIEW
    NOTICE -. "Chưa tiếp nhận" .-> REMIND
    ACK -. "Chưa phân công" .-> REMIND
    REVIEW --> TRACK
```

Khi thiếu facts nhưng hết giới hạn hỏi, CHECK phải chuyển receipt, không đi vào SELECT vô hạn. SELECT không có nghĩa model tự tạo nội dung thao tác: ELIG/STEP vẫn do backend kiểm tra và render. Đường STEP trong view rút gọn bao gồm model chọn ID và eligibility recheck như DG-SEQ-02.

TRACK không tự nối ngược về hướng dẫn khi đã handed_off: dùng follow-up hoặc quy tắc quay lại BR-31–36. Nếu chưa có phòng, UI thu thập vị trí/contact, chưa vào SAVE hoặc receipt có thể tạo phiếu.
