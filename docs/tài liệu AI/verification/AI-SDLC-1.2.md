# AI-SDLC-1.2 — đồng bộ hiện trạng để nhận việc

Ngày 20/11/2026, đến CR-13. Phiên bản **tài liệu**, không phải release ứng dụng. Baseline thiết kế 1.1 và evidence checkpoint cũ vẫn giữ nguyên.

Nguồn kiểm chứng duy nhất của lần rà soát: [DOC-SYNC-01](../../../tasks/verification-doc-sync01.md). Checklist/trạng thái công việc: [todo](../../../tasks/todo.md); trình tự/file sở hữu: [plan](../../../tasks/plan.md). Không lặp bảng trạng thái ở đây.

Đã phân biệt code web/API/provider đã có với guide/outbox/worker/cost/Flutter/pilot còn TARGET, cập nhật contract/quyền/migration/local/API và gỡ link file không có trong checkout. Sơ đồ/SVG baseline có nhãn snapshot, chưa render sơ đồ CURRENT_CODE mới; phần việc đó theo DOC-SYNC-02.

Kiểm tra local-link/reference/FR bằng `python tasks/check-ai-docs.py`; script đọc file, không gọi DB/mạng. Test liên quan description Swagger đã đạt theo evidence. Việc này không thay nghiệm thu business logic, model thật, staging, dữ liệu cá nhân hoặc phê duyệt release.
