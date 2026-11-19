# Bản đồ use case

DG-UC-01 — TARGET. Đặc tả từng UC ở [10](../10_USE_CASE_CHI_TIET.md); quyền ở [12](../12_MA_TRAN_PHAN_QUYEN.md). Đây là functional/use-case view bằng flowchart, không dùng include/extend UML giả.

```mermaid
flowchart LR
    C["Giảng viên / customer hợp lệ"]
    A["Quản trị"]
    K["KTV được giao"]
    G["Người duyệt guide"]
    O["Vận hành"]
    P["Dịch vụ AI bên ngoài"]
    subgraph SYS["Module AI Helpdesk"]
        U1(["UC-01 Tạo và đọc phiên"])
        U2(["UC-02 Làm rõ"])
        U3(["UC-03 Hướng dẫn và kết quả"])
        U4(["UC-04 Hỗ trợ trực tiếp"])
        U5(["UC-05 Liên kết sự cố"])
        U6(["UC-06 Hoàn tất / quay lại"])
        U7(["UC-07 Tiếp nhận / phân công"])
        U8(["UC-08 Xử lý và duyệt đóng"])
        U9(["UC-09 Bổ sung / tái diễn"])
        U10(["UC-10 Quản lý hướng dẫn"])
        U11(["UC-11 Tiến độ / thông báo"])
        U12(["UC-12 Suy giảm / fallback"])
        U13(["UC-13 Dùng qua Flutter"])
        U14(["UC-14 Vận hành"])
    end
    C --- U1
    C --- U2
    C --- U3
    C --- U4
    C --- U6
    C --- U9
    C --- U11
    C --- U13
    A --- U5
    A --- U7
    A --- U8
    A --- U9
    A --- U11
    K --- U8
    K --- U11
    G --- U10
    O --- U12
    O --- U14
    P -. "Chỉ đề xuất qua gateway" .- U2
    P -. "Không có quyền thực thi" .- U3
    U4 -. "Backend xét trùng" .-> U5
    U12 -. "Bàn giao không cần model" .-> U4
```

Không suy từ association rằng actor có mọi quyền con. Provider không được tiếp nhận/phân công/đóng ticket. Người soạn và người duyệt là trách nhiệm có thể phân công riêng, không tự thêm role DB khi chưa có task.
