# Deployment và trust boundary

DG-DEP-01 — TARGET pilot, chưa có bằng chứng đã triển khai. Truy vết AI-NFR-004/005/006/009; Q-01/02/04/05/06.

```mermaid
flowchart TD
    subgraph CLIENT["Thiết bị người dùng"]
        BROWSER["Trình duyệt"]
        MOBILE["Flutter Android"]
    end
    subgraph APPHOST["Hạ tầng ứng dụng do nhóm quản lý"]
        TLS["HTTPS / web server"]
        PHP["Laravel HTTP process"]
        WORKER["Queue worker"]
        SCHED["Scheduler"]
        SECRETS["Server secrets"]
    end
    subgraph DATA["Vùng dữ liệu kiểm soát truy cập"]
        MYSQL[("MySQL")]
        PRIVATE[("Private files")]
        BACKUP[("Backup theo policy")]
    end
    subgraph EXTERNAL["Dịch vụ bên ngoài"]
        MODEL["API AI"]
        FCM["Push service"]
    end
    BROWSER -->|"HTTPS"| TLS
    MOBILE -->|"HTTPS"| TLS
    TLS --> PHP
    PHP --> MYSQL
    PHP --> PRIVATE
    PHP --> SECRETS
    WORKER --> MYSQL
    WORKER --> SECRETS
    WORKER -->|"TLS outbound, tối thiểu context"| MODEL
    WORKER -->|"TLS outbound, preview tối thiểu"| FCM
    SCHED --> MYSQL
    SCHED --> WORKER
    MYSQL --> BACKUP
    PRIVATE --> BACKUP
    FCM -. "Thông báo, không thay DB" .-> MOBILE
```

- APPHOST/DATA là vai trò logic, có thể cùng máy pilot; không bắt buộc thuê nhiều server ngay.
- Test/staging/production tách cấu hình, dữ liệu và credential; không dùng test runner vào DB thật.
- Không có GPU/Python inference/Ollama trong đường vận hành đã chốt.
- DB, queue và storage không public trực tiếp cho app; ảnh tải qua endpoint có policy.
- Key chỉ trong secrets server, không nằm trong app bundle/browser/push/backup log.
- Notification DB là lịch sử bền vững; push/WebSocket là kênh bổ sung.
- Worker và scheduler cần cơ chế khởi động lại/giám sát; backup cần restore thực nghiệm.
- Quota/circuit cần shared store; scale thêm worker không được tạo ngân sách riêng ngoài kiểm soát.
- Chưa chọn push provider chính thức tại Q-06; FCM trong sơ đồ là phương án Android dự kiến, không phải cấu hình đã mua/bật.
