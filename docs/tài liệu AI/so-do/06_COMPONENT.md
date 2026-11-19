# Component view

DG-CMP-01 — TARGET. View ranh giới/interface, bổ sung sơ đồ tổng thể ở 02. Không yêu cầu microservices, mọi component backend có thể là module trong cùng Laravel.

```mermaid
flowchart LR
    WEB["Web hiện có"]
    APP["Flutter"]
    subgraph LARAVEL["Laravel application"]
        API["Controllers / Requests / Resources"]
        POLICY["Policies và auth"]
        SESSION["SessionCommandService"]
        ORCH["SupportAgentService"]
        GUIDE["GuideEligibilityService"]
        GATE["AiClient + validator"]
        QUOTA["Shared quota / circuit"]
        HANDOFF["EscalationService"]
        TICKET["TicketCreationService"]
        OUTBOX["Outbox / delivery jobs"]
        AUDIT["Audit metadata"]
    end
    DB[("Database")]
    STORE[("Private attachment storage")]
    P["AI provider"]
    PUSH["Push provider"]
    WEB -->|"Session và CSRF"| API
    APP -->|"Bearer token"| API
    API --> POLICY
    API --> SESSION
    SESSION --> ORCH
    ORCH --> GUIDE
    ORCH --> GATE
    GATE --> QUOTA
    GATE <-->|"Chỉ context cần thiết và decision"| P
    ORCH --> HANDOFF
    HANDOFF --> TICKET
    HANDOFF --> OUTBOX
    SESSION --> DB
    GUIDE --> DB
    TICKET --> DB
    OUTBOX --> DB
    QUOTA --> DB
    OUTBOX --> PUSH
    API --> STORE
    SESSION --> AUDIT
    GATE --> AUDIT
    HANDOFF --> AUDIT
```

QUOTA→DB có nghĩa shared atomic store; có thể dùng cache phù hợp môi trường nếu contract/tính nguyên tử được chứng minh. Không dùng memory riêng từng worker.

POLICY phải kiểm tra cả read/write và đường ảnh/command/report, không chỉ controller đầu. Callback/worker vẫn recheck điều kiện trạng thái, không giả client đã kiểm tra thay.

Provider không có đường trực tiếp tới DB, storage hoặc TicketCreationService. Outbox/delivery không tự chẩn đoán. Cấu trúc class cụ thể chỉ tạo theo task có sử dụng, không tạo toàn bộ skeleton trước.
