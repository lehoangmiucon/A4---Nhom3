# Danh mục sơ đồ AI

> Nguồn sơ đồ là các khối Mermaid trong Markdown này, không có bộ sơ đồ khác chỉnh tay song song.
> TARGET = thiết kế đích; CURRENT_CODE = phản ánh mã đã đọc, chưa đồng nghĩa triển khai production.
> Các hình là view cho tài liệu 02/03/10/11/12/14, không tự thêm nghiệp vụ.

| ID | Loại/view | File | Truy vết |
| --- | --- | --- | --- |
| DG-UC-01 | Bản đồ actor/use case | [01_USE_CASE](01_USE_CASE.md) | UC-01–14 |
| DG-ACT-01 | Luồng nghiệp vụ có lane | [02_ACTIVITY](02_ACTIVITY.md) | UC-01–09, BR-01–36 |
| DG-SEQ-01–07 | Tương tác, transaction và ngoại lệ | [03_SEQUENCE](03_SEQUENCE.md) | TC-001/005/006/008/011/021/025/026 |
| DG-ER-01–02 | ERD hiện có và phần mở rộng | [04_ERD](04_ERD.md) | 03/14, AI-NFR-003 |
| DG-ST-01–05 | Session/command/guide/acknowledgement/ticket | [05_STATE_MACHINE](05_STATE_MACHINE.md) | 02/11, TC-009/015/017/025 |
| DG-CMP-01 | Component và interface | [06_COMPONENT](06_COMPONENT.md) | Module map 02 |
| DG-DEP-01 | Deployment và trust boundary | [07_DEPLOYMENT](07_DEPLOYMENT.md) | AI-NFR-004/006/009 |

Mermaid không có cú pháp use-case UML hoặc BPMN đầy đủ. DG-UC và DG-ACT dùng flowchart để thể hiện actor, chức năng và lane; không tuyên bố đây là mô hình UML/BPMN hình thức. Sequence, state và ERD dùng loại Mermaid tương ứng.

Khi đổi luật hoặc contract, cập nhật file sở hữu trước, sau đó cập nhật diagram liên quan và TC. Nếu export SVG, đó là bản sinh từ source Markdown, không sửa trực tiếp.

## Tính mới của các hình

Diagram 1.1 chủ yếu mô tả TARGET. CURRENT_CODE đến CR-13 và bản đồ file/lượt đồng bộ ở [kiến trúc 02](../02_KIEN_TRUC.md), [contract 03](../03_DU_LIEU_VA_API.md) và [plan](../../../tasks/plan.md). Mermaid hiện giữ hình baseline, thêm chú thích delta CR-11/13 ngoài code block; không sửa đồ thị trong lượt rà tài liệu này. SVG ngày 19/11 chưa thể hiện các delta, không dùng làm sơ đồ triển khai hiện tại hoặc bằng chứng outbox/Flutter đã có. Cần task cập nhật/render hình riêng trước đưa bản CURRENT_CODE vào báo cáo.

## Bản xuất để xem hoặc đưa vào báo cáo

Có [18 bản SVG](svg/README.md) tương ứng các diagram ID ở trên. Mermaid Markdown là nguồn chính; xuất lại và kiểm tra khi thay luật/hợp đồng. Trạng thái kiểm chứng và công cụ ở [verification 1.1](../verification/AI-SDLC-1.1.md).
