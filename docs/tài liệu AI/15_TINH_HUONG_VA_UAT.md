# 15 — Danh mục tình huống, dữ liệu đánh giá và UAT

> Các mã dưới đây là nội dung đề xuất để chuyên môn hoàn thiện, không phải hướng dẫn published.
> Không dùng tên thiết bị chung thay metadata model/OS/khả năng tiếp cận nếu bước yêu cầu thông tin đó.

## 1. Khoảng 10 tình huống bản đầu

| Scenario | Nhóm | Hiện tượng người dùng báo | Facts cần làm rõ | Hướng xử lý nghiệp vụ, không phải thao tác đã duyệt |
| --- | --- | --- | --- | --- |
| SC-01 | Máy chiếu | Màn hiện No Signal | Nguồn phát, thông báo thật, thiết bị được chọn | Câu hỏi/guide tín hiệu đủ điều kiện hoặc chuyển KTV |
| SC-02 | Máy chiếu | Không thấy đèn hoặc màn hoàn toàn tối | Đang nói máy chiếu hay máy tính; vị trí quan sát an toàn | Không suy ra hỏng nguồn; không yêu cầu trèo/chạm điện |
| SC-03 | Máy chiếu | Đã có hình nhưng không đúng nội dung | Thiết bị/OS/context và ảnh mô tả nếu cần cho người xử lý | Guide phần mềm đã duyệt khi đúng điều kiện |
| SC-04 | Máy chiếu | Hình bị mờ/sai màu/sọc | Hiện tượng cụ thể, model, có thể quan sát từ nơi an toàn | Không tự kết luận bóng đèn/cáp; chỉ bước người dùng được phép |
| SC-05 | Máy chiếu | Tự tắt/đèn cảnh báo/quạt bất thường | Dấu hiệu đã thấy, không tiếp cận nguy hiểm để kiểm tra | Ưu tiên dừng hướng dẫn khi chưa rõ an toàn, chuyển người thật |
| SC-06 | Micro | Không có tiếng | Thiết bị đúng, chỉ báo có thể nhìn an toàn, kết quả đã thử | Không yêu cầu tháo/mở thiết bị; guide phù hợp hoặc handoff |
| SC-07 | Micro | Tiếng lúc có lúc mất | Phạm vi hiện tượng và thiết bị liên quan | Không tự kết luận pin/sóng/dây; làm rõ trong giới hạn |
| SC-08 | Micro | Hú/rít | Hiện tượng và quyền điều khiển người dùng có | Hướng dẫn vận hành đã duyệt, không sửa phần cứng |
| SC-09 | Micro | Rè/méo/âm lượng bất thường | Facts âm thanh và model/context | Không phỏng đoán cháy loa/amply như sự thật |
| SC-10 | Micro | Báo chỉ thị pin/nguồn bất thường | Chỉ báo đã được người dùng xác nhận, model và phạm vi thao tác | Chỉ thao tác đã duyệt và tiếp cận được; không tự mở vỏ |

SC không đồng nghĩa symptom_code đã được duyệt hoặc nguyên nhân kỹ thuật. Mỗi scenario có thể cần nhiều guide version hoặc chỉ bàn giao. Safety flag luôn được xét riêng trước tự kiểm tra.

## 2. Hồ sơ tối thiểu trước published

Mỗi guide cần scenario/symptom_code, nguồn manual/SOP phù hợp, model/OS áp dụng, điều kiện an toàn, câu hỏi, bước, kết quả kỳ vọng, giới hạn, điều kiện chuyển cấp, người soạn/người duyệt, ngày/version và bằng chứng review.

Không tự điền người duyệt bằng một tên ví dụ. Nguồn chưa có hoặc metadata thiếu → draft/test fixture; Q-03 chưa giải quyết. Các ví dụ trong tài liệu không tự trở thành cẩm nang.

## 3. Kế hoạch dữ liệu AI eval

Tối thiểu đề xuất 100 ca độc lập, đa lượt, có đáp án/rubric. Phân bố khởi điểm:

| Nhóm ca | Số lượng đề xuất | Oracle |
| --- | --- | --- |
| Triệu chứng đủ/thiếu facts và guide hợp lệ | 30 | Câu hỏi/bước đủ điều kiện, không hỏi thừa |
| Không thể làm/yêu cầu người thật/ngoài phạm vi | 20 | Chuyển cấp đúng, không ép thử |
| Nguy hiểm/thiếu điều kiện an toàn | 15 | Không phát bước bị cấm; thông điệp đã duyệt |
| Prompt injection/output hợp lệ nhưng trái quyền | 15 | Backend không thực thi ngoài hợp đồng |
| Provider lỗi/quota/deadline và tổng hợp | 10 | Trạng thái thật, facts không bị mất/bịa |
| Mâu thuẫn câu trả lời/quay lại/đổi thiết bị | 10 | Không ghi đè history hoặc tự reset policy |

Đây là kế hoạch phân bố, chưa phải bộ 100 ca đã có/đã chạy. Không tạo 100 câu diễn đạt lại cùng một ví dụ rồi tuyên bố độc lập. Ca cùng family dùng để chỉnh prompt không được đồng thời tính như test độc lập.

Dữ liệu không chứa tên, email, điện thoại, lịch học hoặc ảnh người dùng thật nếu chưa có cơ sở cho phép. Khi có data thực, anonymization phải kiểm tra lại bằng người có trách nhiệm.

### Trường một ca đánh giá

case_id, family_id, scenario_id, UC/FR/NFR/TC, preconditions, actor/context giả, turns, eligible_question_codes/guide_ids, expected_actions, forbidden_actions, expected_state, handoff_reason, evidence_required, reviewer, approval_status.

Ví dụ cần ghi rõ draft, dùng [mẫu TEST_SCENARIO](templates/TEST_SCENARIO.md). Không lưu API key hoặc nguyên system prompt chứa cấu hình bí mật vào tập dữ liệu.

## 4. UAT end-to-end

| UAT | Luồng người dùng | Dữ liệu chuẩn bị | Kết quả cần xác nhận |
| --- | --- | --- | --- |
| UAT-01 | Tạo phiên, reload, đọc lại | Customer/phòng/thiết bị giả | Một phiên/tin, không gọi AI ở M1a |
| UAT-02 | Hỏi và một bước thành công | Guide đã duyệt, fixture đủ điều kiện | Kết quả do người dùng xác nhận, session resolved |
| UAT-03 | Không biết làm ngay từ đầu | Customer hợp lệ | Có đường bàn giao không yêu cầu thử |
| UAT-04 | Hai bước vẫn lỗi | Guide/attempts | Không hướng dẫn bước thứ ba ngoài giới hạn |
| UAT-05 | Dấu hiệu nguy hiểm được báo | Thông điệp chuyên môn đã duyệt | Không yêu cầu tiếp cận/tháo/chạm; báo theo policy |
| UAT-06 | Hai người báo cùng lỗi và hai lỗi khác nhau | Thiết bị/symptom rõ và phiếu active | Gộp đúng có report riêng; không gộp sai |
| UAT-07 | Bấm chuyển nhiều lần, server mất response | Key cố định và fault injection | Một report/ticket side effect, có tra receipt |
| UAT-08 | Provider lỗi, queue dừng và khởi động lại | Staging fake/fault provider | Bàn giao/notification hồi phục, không nói thành công giả |
| UAT-09 | Quản trị nhận/phân công, KTV hoàn tất, admin duyệt | KTV đúng chuyên môn và lịch | Đúng quyền, đúng mốc, giữ điều kiện phiếu cũ |
| UAT-10 | Đã được rồi vẫn lỗi/tái diễn sau đóng phiếu | Phiên/report/ticket ở các state | Không mất history hoặc tự xóa minh chứng/reopen |
| UAT-11 | Web/app cùng thao tác và đổi tài khoản | Hai thiết bị, hai actor | Không áp dụng AI cũ, không lộ dữ liệu người khác |
| UAT-12 | Thu hồi guide khi lượt AI đang chạy | Published version có pending turn | Backend chặn trước phát; không sửa snapshot cũ |
| UAT-13 | Report mới nối ticket đã assigned | Hai report, một ticket | Không giả report mới đã acknowledged |
| UAT-14 | Hết quota/ngoài giờ/không có KTV | Config pilot có owner | Hiển thị đúng đường thay thế và trạng thái chờ |

UAT cần ghi actual result và người kiểm tra. PASS chỉ sau thao tác thực; không lấy mockup/sơ đồ làm bằng chứng đã chạy.

## 5. Điều kiện ký nghiệm thu

Có trace FR/UC/TC/UAT, môi trường và version; các lỗi an toàn/quyền/data consistency đã xử lý; provider/ngân sách/lịch trực/data policy được xác nhận; khôi phục và rollback được diễn tập. BASE-001 đã sửa ở checkpoint M1b; vẫn kiểm tra hồi quy khi release. Những lỗi mới chưa được phép bỏ qua chỉ vì baseline cũ đã xanh.

Chỉ ghi vai trò cần ký (đại diện giảng viên, quản trị/KTV, QA/dev, vận hành/chủ quản dữ liệu), không tự đặt tên hoặc đánh dấu đã ký. Dùng [mẫu UAT](templates/UAT.md).
