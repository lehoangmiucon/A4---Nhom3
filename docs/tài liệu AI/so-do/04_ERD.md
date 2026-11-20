# ERD phần AI

ERD biểu diễn quan hệ dữ liệu, không khẳng định đã có mọi Eloquent relationship. Unique/index/FK và từ điển ở [03](../03_DU_LIEU_VA_API.md), [14](../14_TU_DIEN_DU_LIEU.md).

## DG-ER-01 — CURRENT_CODE M1a

```mermaid
erDiagram
    USERS ||--o{ SUPPORT_SESSIONS : owns
    USERS ||--o{ SUPPORT_IDEMPOTENCY_KEYS : actor
    ROOMS o|--o{ SUPPORT_SESSIONS : optional_after_delete
    EQUIPMENT o|--o{ SUPPORT_SESSIONS : optional_device
    SUPPORT_SESSIONS ||--o{ SUPPORT_MESSAGES : contains

    USERS {
        bigint id PK
        string role
        datetime email_verified_at
        boolean is_deleted
    }
    ROOMS {
        bigint id PK
        string code
        string name
    }
    EQUIPMENT {
        bigint id PK
        bigint room_id FK
        string name
        string status
    }
    SUPPORT_SESSIONS {
        bigint id PK
        bigint user_id FK
        bigint room_id FK
        bigint equipment_id FK
        string room_label
        string room_code
        string building_label
        string equipment_label
        string status
        int version
        datetime last_activity_at
    }
    SUPPORT_MESSAGES {
        bigint id PK
        bigint session_id FK
        string role
        text content_text
        uuid client_message_id
    }
    SUPPORT_IDEMPOTENCY_KEYS {
        bigint id PK
        bigint actor_id FK
        string operation
        string key
        string request_hash
        json response_data
        int response_status
    }
```

Mỗi phiên mới có ít nhất một message do giao dịch tạo; ký hiệu o{ cho phép trạng thái vật lý tổng quát, bất biến tạo phiên vẫn yêu cầu tin đầu. Idempotency key table không có FK session_id: ID nằm trong response snapshot, không được vẽ một FK giả.

Room/equipment nullable khi xóa, snapshot giữ nguyên. User FK restrict; session deletion cascade messages chỉ qua quy trình retention được phép.

Delta CURRENT_CODE CR-11: support_sessions có deleted_at nullable (migration 2026_11_20_150000). DELETE của chủ phiên là soft delete, không kích hoạt cascade vật lý, không đổi quan hệ report/ticket/messages/command. Phiên chỉ đủ điều kiện khi handed_off và report.ticket.status=closed. Bản SVG baseline 1.1 chưa thể hiện cột này; xem từ điển 14 và evidence CR-11.

## DG-ER-02 — TARGET mở rộng

```mermaid
erDiagram
    USERS ||--o{ SUPPORT_SESSIONS : owns
    SUPPORT_SESSIONS ||--o{ SUPPORT_COMMANDS : receives
    SUPPORT_SESSIONS ||--o{ SUPPORT_STEP_ATTEMPTS : records
    SUPPORT_SESSIONS ||--o| SUPPORT_REPORTS : produces_once
    SUPPORT_SESSIONS ||--o{ SUPPORT_ATTACHMENTS : stores
    GUIDES ||--|{ GUIDE_VERSIONS : versions
    GUIDE_VERSIONS ||--o{ SUPPORT_STEP_ATTEMPTS : source
    TICKETS ||--o{ SUPPORT_REPORTS : groups
    SUPPORT_REPORTS ||--|| SUPPORT_HANDOFFS : has
    SUPPORT_REPORTS ||--o{ SUPPORT_FOLLOW_UPS : receives
    SUPPORT_OUTBOX ||--o{ SUPPORT_DELIVERIES : delivers
    USERS ||--o{ SUPPORT_COMMANDS : actor
    USERS o|--o{ SUPPORT_HANDOFFS : acknowledges
    USERS ||--o{ SUPPORT_FOLLOW_UPS : authors

    SUPPORT_SESSIONS {
        bigint id PK
        bigint user_id FK
        string status
        int version
    }
    SUPPORT_COMMANDS {
        uuid id PK
        bigint actor_id FK
        bigint session_id FK
        bigint input_message_id FK
        string operation
        string purpose
        string intent_key_hash
        string request_hash
        int snapshot_version
        string status
        datetime deadline_at
        json result
    }
    GUIDES {
        bigint id PK
        string code UK
        string device_scope
    }
    GUIDE_VERSIONS {
        bigint id PK
        bigint guide_id FK
        int version
        string state
        json steps_and_conditions
        bigint reviewed_by FK
    }
    SUPPORT_STEP_ATTEMPTS {
        bigint id PK
        bigint session_id FK
        bigint guide_version_id FK
        string step_code
        json content_snapshot
        string result
    }
    TICKETS {
        bigint id PK
        bigint equipment_id FK
        bigint assigned_to_id FK
        string status
        string priority
    }
    SUPPORT_REPORTS {
        bigint id PK
        bigint session_id FK,UK
        bigint ticket_id FK
        string reason
        json source_snapshot
        text ai_summary
    }
    SUPPORT_HANDOFFS {
        bigint id PK
        bigint report_id FK,UK
        int version
        bigint acknowledged_by FK
        datetime acknowledged_at
    }
    SUPPORT_FOLLOW_UPS {
        bigint id PK
        bigint report_id FK
        bigint author_id FK
        uuid client_message_id
        string type
        text content
    }
    SUPPORT_ATTACHMENTS {
        bigint id PK
        bigint session_id FK
        string private_path
        string actual_mime
        int size
    }
    SUPPORT_OUTBOX {
        uuid event_id PK
        string aggregate_type
        string aggregate_id
        string status
        int attempts
    }
    SUPPORT_DELIVERIES {
        bigint id PK
        uuid event_id FK
        bigint recipient_id FK
        string channel
        string status
    }
```

Tên GUIDES/GUIDE_VERSIONS trong hình rút gọn của troubleshooting_guides/troubleshooting_guide_versions. Outbox dùng aggregate_type/id tổng quát; không vẽ FK đa hình giả vào reports. Những trường khóa ghép như unique actor/operation/intent_key_hash, session/input_message/purpose và event/recipient/channel phải triển khai bằng migration, không chỉ dựa vào ký hiệu UK riêng lẻ trong hình.

Receipt handoff ở SUPPORT_COMMANDS có thể tồn tại trước REPORT; session có report chỉ sau TX-B. input_message_id của handoff nullable. Điều kiện và published của guide version là luật backend, không được bảo đảm chỉ bởi ERD.
