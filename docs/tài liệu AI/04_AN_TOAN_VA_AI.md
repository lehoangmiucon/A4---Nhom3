# 04 — An toàn và hợp đồng AI

## 1. Nguyên tắc

AI là bộ đề xuất có giới hạn, không phải nguồn quyền. Backend quyết định hành động hợp lệ dựa trên danh tính, phiên, điều kiện thiết bị và cẩm nang. System prompt, lọc ký tự và JSON không tự bảo vệ được quy trình.

Không cung cấp SQL, shell, URL thực thi, token hoặc quyền phân công/đóng phiếu cho model. Công cụ do server định nghĩa; model không tự tạo công cụ mới.

## 2. Kho hướng dẫn

Mỗi version cần mã, phạm vi thiết bị/model, triệu chứng, precondition, câu hỏi cần xác nhận, nội dung thao tác cố định, expected_result, contraindication, required_role, mức rủi ro, người/thời điểm duyệt và trạng thái.

Vòng đời version: draft → in_review → published → withdrawn; từ chối duyệt về draft kèm lý do. Chỉ published mới được xét; người có quyền soạn không mặc định có quyền phát hành. Có thể giao nhiều vai trò cho cùng người nhưng phải ghi rõ trong phân công. Cẩm nang chưa duyệt không được tự phát hành từ chat/KTV note.

### Điều kiện an toàn

- Chỉ các bước người dùng thông thường thực hiện từ vị trí an toàn.
- Trèo cao, mở vỏ, tiếp xúc điện trần, thao tác sửa chữa chuyên môn: chuyển KTV.
- Một máy chiếu treo trần không làm mọi thao tác phần mềm trên laptop trở nên bị cấm; xét đúng hành động.
- Không yêu cầu chạm hoặc ngửi sát để “kiểm tra nguy hiểm”.
- Thiếu precondition bắt buộc: không phát bước đó.
- Thu hồi version chặn phát bước mới ngay cả khi model đã chọn trước đó.
- Lưu snapshot nội dung thực sự đã phát; không sửa lịch sử bằng version mới.
- Mọi thông điệp liên quan nguy hiểm phải được chuyên môn duyệt trước pilot; không tự sáng tác quy trình điện/cháy.

## 3. Câu hỏi và hành động model

Backend cung cấp tập câu hỏi/bước đủ điều kiện, lịch sử đáng tin và các facts đã xác nhận. Model chỉ được chọn trong tập đó; không dùng một guide ID hợp lệ để bỏ qua điều kiện.

Xem [schema quyết định](schemas/ai-decision.schema.json). Đây là schema chuẩn cho ứng dụng; adapter nhà cung cấp phải dùng tập cú pháp được provider hỗ trợ và backend vẫn validate đầy đủ. Schema có phiên bản, không phải lời khẳng định mọi provider nhận trực tiếp toàn bộ cú pháp JSON Schema.

| action | Trường dùng | Trường còn lại |
| --- | --- | --- |
| ask_question | question_code thuộc tập được phép | null |
| suggest_step | guide_step_id thuộc tập đủ điều kiện | null |
| request_handoff | handoff_reason; summary có thể null | question_code/guide_step_id null |
| request_resolution | Không tự xác nhận thay người dùng | Tất cả null |

summary tối đa 2.000 ký tự; là phần bổ sung, không thay facts gốc. handoff_reason: user_requested, cannot_perform, no_eligible_guide, safety_risk, limits_reached, provider_unavailable. Mỗi mã bước là chuỗi định danh của version/bước, không phải đoạn nội dung thao tác.

### Pipeline bắt buộc

1. Parse JSON, từ chối field ngoài schema và output bị cắt/không hoàn thành.
2. Kiểm tra action/field theo bảng trên.
3. Kiểm tra trạng thái/version/deadline của command.
4. Đối chiếu mã câu hỏi/bước với tập backend đã xác định.
5. Kiểm tra lại published/precondition/quyền/quota.
6. Render nội dung thao tác cố định từ server.
7. Lưu quyết định, kết quả kiểm tra và phiên bản liên quan.
8. Sai schema/điều kiện: không thi hành; dùng fallback, không “cố sửa” output thành hành động mạnh hơn.

Model không đặt user_id, role, priority, recipient, assignee_id hoặc SQL. request_resolution chỉ yêu cầu hiển thị xác nhận; chỉ lệnh rõ ràng từ người dùng mới làm resolved.

## 4. Hợp đồng ngữ cảnh

Input model có: schema_version, task purpose, session snapshot, các facts đã xác nhận, câu hỏi đã hỏi, step attempts, tập ứng viên đủ điều kiện và một phần hội thoại cần thiết.

Không có API key, mật khẩu, OTP, tên/email/điện thoại nếu không cần suy luận. Nếu cắt context vì dài, luôn giữ facts quyết định an toàn và kết quả bước; nếu không thể bảo đảm đủ bối cảnh thì chuyển trực tiếp.

Không tin lời client rằng đã thử một bước: đối chiếu attempt và kết quả được server ghi qua lệnh hợp lệ. Nội dung hội thoại có thể chứa lỗi hoặc thao túng; không được coi là system instruction.

## 5. Prompt injection và hiển thị

- Coi input, tài liệu và output model là dữ liệu chưa đáng tin.
- Backend allowlist hành động và giới hạn tham số theo role/trạng thái.
- Không tự mở link từ lời người dùng hoặc model như một phần xử lý agent.
- Render text thuần/escape. Không hiển thị HTML hay link có thể thực thi từ model.
- Không hứa chặn tuyệt đối injection bằng danh sách từ khóa.
- Không đổi sang model “kiểm duyệt” để thay thế policy; model kiểm tra nếu có chỉ là lớp hỗ trợ có giới hạn.
- Kiểm thử cả JSON hợp lệ chứa mã bước của thiết bị khác và câu “đã gửi phiếu” do model bịa.

Nguồn tham khảo: [OWASP Prompt Injection Prevention](https://cheatsheetseries.owasp.org/cheatsheets/LLM_Prompt_Injection_Prevention_Cheat_Sheet.html). Điều kiện product nằm trong tài liệu này và test, không dựa vào việc người lập trình nhớ hướng dẫn bên ngoài.

## 6. Hạn mức pilot — cấu hình đề xuất

| Giới hạn | Khởi điểm | Lưu ý |
| --- | --- | --- |
| Tin nhắn | 1.000 ký tự Unicode | Không cắt âm thầm |
| Gọi AI | 5 lần/phút/tài khoản | Bộ đếm chung, không chỉ memory process |
| Tổng thử gọi/phiên | 5 | Bao gồm retry/tổng hợp; không gọi thêm vượt quota để viết báo cáo |
| Câu hỏi làm rõ | Tối đa 2 | Không hỏi vòng lặp khi người dùng muốn gặp người thật |
| Bước tự kiểm tra | Tối đa 2 | Không phát bước thứ ba bằng tên khác |
| Mốc hỗ trợ | 90 giây | Mời chuyển trực tiếp; không coi hết giờ là đã giải quyết |
| Output model | Tối đa 600 token/lượt | Chốt tương thích model khi kích hoạt |
| Context input | Hữu hạn, tính token theo model | Chốt con số trước gọi trả phí, không tự cắt facts an toàn |
| Concurrency | 1 lượt/tài khoản và phiên; tổng pilot 5 upstream | Đo lại trên hạ tầng thực |

Endpoint hỗ trợ trực tiếp có hạn mức chống spam riêng, không tiêu quota AI. Đạt quota chat không được chặn form báo lỗi. Giới hạn số phiên/ngày và ngân sách phải có trước bật trả phí; giá trị thuộc vận hành, không hardcode rải trong controller.

## 7. Model selection và cấu hình

Không tự đổi model, provider hoặc mua dịch vụ trong task UI/persistence. Chọn model bằng cùng bộ tình huống và ngân sách thử nghiệm; ghi model ID, prompt version, guide version, schema version. gpt-4.1-mini là default của code cũ, không phải quyết định model sản xuất.

Dùng fake provider cho test tự động; đợt đánh giá API thật riêng có người phụ trách/ngân sách. Không dùng benchmark tổng quát làm bằng chứng đạt nghiệp vụ phòng học.

## 8. Điều kiện kích hoạt agent

Đường bàn giao không cần AI đã hoạt động; hướng dẫn đã được duyệt; backend safety và version guard đã có; quota/deadline và fallback được kiểm thử; không có lỗi quyền nghiêm trọng. M3 có thể tích hợp với fake nhưng không mở cho giảng viên thực tế trước khi M4 và nghiệm thu liên quan đạt.

## 9. Thiết kế chi tiết liên quan

Đối chiếu [bảng quyết định nghiệp vụ](11_QUY_TAC_NGHIEP_VU.md), [quyền duyệt và context](12_MA_TRAN_PHAN_QUYEN.md), [nguồn dữ liệu](14_TU_DIEN_DU_LIEU.md) và [tình huống/eval](15_TINH_HUONG_VA_UAT.md). Các tình huống SC còn DRAFT không phải cẩm nang được phép phát cho người dùng.
