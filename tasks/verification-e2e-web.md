# Báo cáo Kiểm chứng Toàn diện E2E trên Trình duyệt thật (Chrome CDP)

Ngày: 20/11/2026. Trạng thái: **DONE_CODE & VERIFIED_REAL_BROWSER**.  
Phương pháp: Điều khiển Google Chrome headless/CDP trực tiếp trên môi trường Windows, kết nối máy chủ backend Laravel thật (`http://127.0.0.1:8000`), cơ sở dữ liệu MariaDB thật và mô hình AI local Ollama (`qwen2.5:7b`).

---

## 1. Mục tiêu kiểm chứng & Trọng tâm

Xác minh tính ổn định của bản web trong luồng liên hoàn thực tế từ người dùng đến kỹ thuật viên (KTV), với 3 trọng tâm chính:
1. **Tạo chat mới (`#conversation-new`)**: Đảm bảo dọn sạch phiên, giải phóng bộ nhớ client (`sessionStorage`), mở khóa khung nhập và mở phiên mới trên server mà không bị rò rỉ trạng thái cũ.
2. **Đổi danh mục**: Kiểm tra việc thay đổi danh mục sự cố trong panel ngữ cảnh, đồng bộ tức thời lên thanh ngữ cảnh (`#conversation-context`) và đưa vào ngữ cảnh của AI.
3. **Nội dung phiếu KTV**: Xác minh phiếu tạo từ chat AI có đầy đủ cấu trúc 4 phần (Tóm tắt AI, Kiểm tra người dùng đã nêu, Gợi ý KTV, Lời báo gốc đối chiếu), và kiểm tra định dạng hiển thị thực tế (`white-space: pre-line`) trên cả trang Sinh viên và trang Kỹ thuật viên (`/staff/ticket-detail`).

---

## 2. Kết quả 8 bước thực thi trên Trình duyệt thật

| Bước | Thao tác thực tế | Kết quả kiểm chứng | Bằng chứng hình ảnh |
| :--- | :--- | :--- | :--- |
| **1. Đăng nhập Sinh viên** | Đăng nhập tài khoản `trint0607@ut.edu.vn` tại `/auth/login`. | Đăng nhập thành công, chuyển hướng đến `/customer/index`. | — |
| **2. Chuyển sang AI** | Vào `/customer/portal`, click nút **Nhờ AI hỗ trợ** (`#report-mode-ai`). | Panel AI `#report-ai-view` hiển thị, hiển thị lời chào mở đầu chuẩn, composer sẵn sàng. | `evidence/e2e-web/01_ai_portal_mode.png` |
| **3. Tin nhắn ban đầu** | Gửi sự cố: *"Máy chiếu phòng A203 không lên hình, đã thử cắm lại dây HDMI nhưng vẫn báo No signal"*. | Hiển thị bong bóng gửi/suy nghĩ, AI phản hồi sau 3–4 giây. Thanh ngữ cảnh tự động nhận diện: `A203 · Máy chiếu · Hội thoại đã lưu`. | `evidence/e2e-web/02_first_reply.png` |
| **4. Đổi danh mục** | Mở `#conversation-context-picker`, đổi danh mục sang **Điện & chiếu sáng** và gửi tin bổ sung. | Thanh ngữ cảnh cập nhật tức thời: `A203 · Điện & chiếu sáng · Hội thoại đã lưu`. AI nhận ngữ cảnh mới và trả lời phù hợp. | `evidence/e2e-web/03_category_changed.png` |
| **5. Tạo chat mới** | Click nút **Cuộc trò chuyện mới** (`#conversation-new`). | **Reset hoàn hảo**: Log quay về 1 lời chào, input trống và mở khóa (`readOnly=false`), biên nhận cũ ẩn, context reset về *"Cuộc trò chuyện mới. Bạn đang gặp sự cố gì?"*. | `evidence/e2e-web/04_new_chat_reset.png` |
| **6. Tạo phiếu từ chat mới** | Trong phiên mới, gửi sự cố loa/máy chiếu và click **Gửi báo cáo giúp tôi** (`#conversation-escalate`). | Tạo phiếu thành công! Biên nhận `#conversation-report` xuất hiện với mã phiếu `HD-20261120-DAAD17` và link xem chi tiết phiếu. | `evidence/e2e-web/05_ticket_created_receipt.png` |
| **7. Chi tiết phiếu Sinh viên** | Truy cập `/customer/ticket-detail?ticket=21`. | Mô tả hiển thị đầy đủ 4 phần rõ ràng. Kiểm tra CSS: `white-space = pre-line`, ngắt dòng và thụt lề chuẩn xác. | `evidence/e2e-web/06_student_ticket_detail.png` |
| **8. KTV kiểm tra phiếu** | Đăng xuất sinh viên, đăng nhập KTV `staff@gmail.com`, truy cập `/staff/ticket-detail?ticket=21`. | Giao diện KTV tải chuẩn xác với trạng thái **● Đã tiếp nhận**, font chữ rõ ràng, `white-space = pre-line`, đầy đủ gợi ý kiểm tra ban đầu và lời báo gốc. | `evidence/e2e-web/07_staff_ticket_detail.png` |

---

## 3. Bảng tiêu chí kiểm tra (13/13 Checks PASSED)

```json
{
  "login_success": true,
  "new_chat_cleared_log": true,
  "new_chat_cleared_input": true,
  "new_chat_cleared_report": true,
  "category_sync_working": true,
  "ticket_created_receipt": true,
  "student_description_has_sections": true,
  "student_description_has_checks": true,
  "student_description_has_suggestions": true,
  "student_description_has_raw_source": true,
  "student_white_space_preline": true,
  "staff_white_space_preline": true,
  "staff_view_ok": true
}
```

---

## 4. Bằng chứng nội dung phiếu thực tế (Phiếu #21)

```text
Phòng: A203 — Phòng A.203
Thiết bị/danh mục: Máy chiếu

TÓM TẮT AI — CHƯA ĐƯỢC KTV XÁC MINH
Máy chiếu phòng A203 không hoạt động, đã thử bật công tắc.

KIỂM TRA/KẾT QUẢ NGƯỜI DÙNG ĐÃ NÊU
- Máy chiếu phòng A203 bị trục trặc không hoạt động được, đã bật thử công tắc

GỢI Ý AI CHO KTV — CHỈ THAM KHẢO, CẦN KIỂM CHỨNG
- Kiểm tra lại dây nối máy chiếu và nguồn điện.

LỜI BÁO GỐC — NGUỒN ĐỐI CHIẾU
Máy chiếu phòng A203 bị trục trặc không hoạt động được, đã bật thử công tắc

Nguồn: hội thoại hỗ trợ #21. Hội thoại đầy đủ được lưu trong báo cáo hỗ trợ. Gợi ý không phải kết luận nguyên nhân hoặc xác nhận đã sửa.
```

---

## 5. Kết luận & Điểm lưu ý

- **Tính ổn định của bản web**: Cả 3 tính năng trọng tâm (Tạo chat mới, Đổi danh mục, Nội dung phiếu KTV) hoạt động hoàn toàn mượt mà, đồng bộ chính xác giữa client JS, API backend Laravel, DB MariaDB và mô hình AI Ollama.
- **Cơ chế chống trùng phiếu (CR-03)**: Được kiểm chứng hoạt động đúng thực tế — khi thử gửi sự cố vào phòng và danh mục đang có phiếu chưa đóng, hệ thống chặn tạo phiếu trùng và thông báo lịch sự cho người dùng mà không làm mất hội thoại.
- **Hiển thị trực quan**: Thuộc tính `white-space: pre-line` trên `.ticket-description` (sinh viên) và `.staff-ticket-description p` (KTV) hiển thị văn bản phân đoạn sắc nét, không bị dính liền dòng.
