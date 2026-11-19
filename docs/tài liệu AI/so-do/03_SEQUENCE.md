# Sequence diagrams

Mỗi sơ đồ ghi rõ CURRENT_CODE hoặc TARGET. Không tự chuyển code để khớp hình nếu hợp đồng sở hữu chưa được cập nhật. Đường gọi nội bộ là service invocation, không phải server tự HTTP vào endpoint của chính mình.

## DG-SEQ-01 — Tạo phiên và replay

CURRENT_CODE M1a. UC-01, AI-FR-001/002/003; TC-001/002/005.

```mermaid
sequenceDiagram
    actor C as Customer
    participant API as Laravel API
    participant S as SessionCreationService
    participant DB as Database
    C->>API: POST session + Idempotency-Key
    API->>API: Auth / role / policy / shape validation
    API->>S: create(actor, validated input)
    S->>DB: BEGIN, lock user row
    S->>DB: Find actor + operation + key hash
    alt Key exists, same payload
        DB-->>S: Original response snapshot
        S->>DB: COMMIT
        S-->>API: Replay data
    else Key exists, different payload
        S->>DB: ROLLBACK
        S-->>API: Validation conflict
    else New intent
        S->>DB: Validate and lock room / equipment
        S->>DB: Insert session + first message + replay data
        S->>DB: COMMIT
        S-->>API: Creation snapshot
    end
    API-->>C: 201 data or structured error
    Note over API,DB: No AI call and no ticket created
    C->>API: GET session / messages
    API->>DB: Owner-scoped queries
    DB-->>API: Current state / ordered messages
    API-->>C: 200 with pagination where relevant
```

## DG-SEQ-02 — Một lượt hỏi hoặc hướng dẫn

TARGET M3/M4. UC-02/03; TC-007/008/010/025/027.

```mermaid
sequenceDiagram
    actor C as Web / Flutter
    participant API as Session API
    participant DB as Database
    participant W as AI worker
    participant G as Gateway / guards
    participant P as Provider
    C->>API: message / turn + key + expected_version
    API->>DB: TX: policy, key, version, store message/command
    API->>DB: Persist dispatch intent, COMMIT
    API-->>C: 202 command_id + status_url
    W->>DB: Claim command lease, read current snapshot
    W->>G: Eligible questions/steps + verified context
    G->>G: Quota reservation + time budget
    G->>P: Bounded inference request
    P-->>G: Decision + usage or error
    G->>G: Schema + semantic validation
    alt Valid decision and deadline remains
        G->>DB: TX: recheck version / state / published guide
        alt Snapshot still valid
            G->>DB: Persist question or attempt snapshot, command succeeded
        else Session changed
            G->>DB: Mark command discarded, preserve usage
        end
        G->>DB: COMMIT
    else Invalid / unavailable
        G->>DB: Record failure and usage uncertainty
        G->>API: Internal fallback handoff service, not HTTP
    end
    C->>API: GET command and session
    API->>DB: Policy-scoped state
    API-->>C: Actual command result and current permitted actions
```

Dispatch intent có thể dùng outbox nội bộ; worker/scheduler phải phục hồi command pending sau crash. Không chỉ dispatch trong memory sau commit rồi mất lượt khi process chết. request_resolution chỉ hiện xác nhận, không tự đóng phiên.

## DG-SEQ-03 — Bàn giao không cần model

TARGET M2, theo hai transaction local ở BR mục 6. UC-04/05; TC-006/011/012/026.

```mermaid
sequenceDiagram
    actor C as Customer
    participant API as Handoff API
    participant H as EscalationService
    participant DB as Database
    participant W as Delivery worker
    participant A as Admin
    C->>API: POST escalations + key + expected_version
    API->>H: Authorized handoff intent
    H->>DB: TX-A: lock session, key/version checks
    H->>DB: Store receipt command, state pending, increase version
    H->>DB: COMMIT TX-A
    H->>DB: TX-B: lock receipt and session
    H->>DB: Replay check, match/create ticket, report, log, outbox
    H->>DB: Mark receipt succeeded, session handed_off
    H->>DB: COMMIT TX-B
    H-->>API: Handoff result snapshot
    API-->>C: 201 report and ticket reference
    Note over API,H: No provider call required
    W->>DB: Claim outbox event
    W->>DB: Insert unique database notification / delivery
    W-->>A: Notify update
    A->>API: Explicit acknowledge report
    API->>DB: Store acknowledgement actor/time
    Note over C,A: Ticket reference, delivery and acknowledgement are distinct
```

Tóm tắt AI nếu có là dữ liệu phụ, mẫu facts luôn có. TX-B lỗi không được rollback TX-A receipt; nhánh lỗi ở DG-SEQ-05.

## DG-SEQ-04 — Retry và nhiều người báo

TARGET, có phần nguyên tắc M1a đã có. UC-04/05; TC-005/006/013/014.

```mermaid
sequenceDiagram
    actor C1 as Customer one
    actor C2 as Customer two
    participant H as Handoff service
    participant DB as Database
    C1->>H: Intent K for session S
    H->>DB: Lock intent, commit receipt and final report
    DB-->>H: Ticket T / report R1
    H-->>C1: 201 snapshot
    C1->>H: Retry same S, K, payload
    H->>DB: Find successful intent
    H-->>C1: Replay, no second report
    C2->>H: Different session, same equipment and symptom
    H->>DB: Lock equipment, find eligible active candidate
    alt Exactly one confirmed match
        H->>DB: Insert report R2 linked to T
        H-->>C2: Own report + minimal progress
    else Ambiguous / different symptom / no device
        H->>DB: Create separate ticket/report, mark review if needed
        H-->>C2: Own reference, no private data from R1
    end
```

Không thay idempotency bằng similarity. Các luồng form/agent tham gia auto-match đều dùng cùng quy tắc khóa, không chỉ một endpoint.

## DG-SEQ-05 — Model lỗi, final transaction lỗi và mất response

TARGET. UC-12; TC-021/026/027.

```mermaid
sequenceDiagram
    actor C as Customer
    participant G as Gateway / backend
    participant P as Provider
    participant H as Handoff service
    participant DB as Database
    G->>P: Request within deadline
    P-->>G: Timeout / quota / invalid output
    G->>G: No blind retries, settle or mark unknown usage
    G->>H: Fallback from stored facts
    H->>DB: TX-A commit receipt
    H->>DB: Try TX-B report/ticket/outbox
    alt TX-B succeeds
        H->>DB: COMMIT, succeeded receipt
        H-->>G: Final handoff result committed
        C->>G: Read session / command
        G-->>C: Actual handoff state
    else TX-B fails after receipt exists
        H->>DB: ROLLBACK TX-B, retain pending receipt
        H-->>G: Pending receipt, no ticket success
        C->>G: Read session / command
        G-->>C: Pending command / recovery state
        C->>H: GET authorized command status or retry same intent
        H->>DB: Resume final transaction using same receipt
    end
    Note over C,DB: If no server acknowledgement arrives, client must not claim success
```

Nếu đây là request escalations đồng bộ, TX-B thành công trả 201 và TX-B lỗi sau receipt trả 503 như hợp đồng 03; worker AI fallback không tự gửi một HTTP 201 đến client đã kết thúc request.

Nếu TX-A cũng thất bại hoặc server không truy cập được, không có receipt để hứa đã nhận. Nếu thiếu phòng, đi luồng thu thập vị trí/contact thay vì bịa ticket.

## DG-SEQ-06 — AI cũ trả sau lệnh từ thiết bị khác

TARGET M4. UC-03/04/12; TC-025.

```mermaid
sequenceDiagram
    actor W as Web client
    actor M as Mobile client
    participant API as Laravel
    participant DB as Database
    participant P as AI worker/provider
    W->>API: Start AI turn at snapshot version V
    API->>P: Process bounded turn
    M->>API: Request direct support
    API->>DB: TX-A increase version, handoff_pending
    API->>DB: TX-B commit report and handed_off
    API-->>M: Handoff reference
    P-->>API: Decision for old version V
    API->>DB: Check snapshot/state under lock
    DB-->>API: Version changed, already handed_off
    API->>DB: Command discarded, usage accounted
    W->>API: Poll command/session
    API-->>W: Discarded turn + current handoff state
    Note over W,M: Neither client shows old step as an active instruction
```

Client không chỉ dựa vào thứ tự response HTTP; luôn đối chiếu session version và permitted_actions.

## DG-SEQ-07 — Sự cố vẫn còn

TARGET M4/M5. UC-06/09; TC-017.

```mermaid
sequenceDiagram
    actor C as Report owner
    participant API as Laravel
    participant DB as Database
    participant A as Admin
    C->>API: Still not working
    API->>DB: Read session/report/ticket under policy
    alt Session resolved, no report
        API->>DB: Reopen session, increase version, preserve history/counters
        API-->>C: collecting with valid next actions
    else Report exists, ticket active
        API->>DB: Store idempotent follow-up and notification intent
        API-->>C: Follow-up reference, ticket still managed separately
    else Ticket resolved or closed
        API->>DB: Store recurrence/reopen review request
        API-->>C: Request saved, not a reopened ticket
        A->>API: Decide authorized ticket action
        API->>DB: Existing ticket workflow with audit
    end
```

Không tự gọi reopen ticket từ lệnh customer; tác dụng phụ lên minh chứng trong code ticket hiện có phải được quản trị hiểu và theo quy trình.
