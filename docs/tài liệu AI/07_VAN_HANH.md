# 07 — Vận hành, bảo mật dữ liệu và phát hành

## 1. Điều kiện môi trường

Phát triển và test tự động dùng dữ liệu giả, SQLite :memory: theo TestCase hiện có. Staging phải tách production, có database/queue/provider key/push riêng. Khi cần kiểm chứng khóa và concurrency, dùng MySQL staging đúng cấu hình triển khai.

Đã có evidence migration trên local: phiên/workflow batch 15, brief batch 16, deleted_at batch 17. Đây không phải bằng chứng staging/production hoặc máy thành viên khác đã cập nhật. Danh sách migration và cách kiểm tra ở [hướng dẫn local/API](../local-ai.md). Không suy từ RefreshDatabase rằng DB đang dùng đã cập nhật.

Trước thao tác có side effect, xác nhận APP_ENV, tên/kết nối database, queue, mail/push và tài khoản trả phí mà không in secret. Không tự sao chép .env hoặc dữ liệu người dùng vào tài liệu.

## 2. Cấu hình và bí mật

CURRENT_CODE có HELPDESK_AI_PROVIDER=openai|ollama, OPENAI_API_KEY/OPENAI_MODEL, OLLAMA_URL/OLLAMA_MODEL và ba biến hạn mức chat ở config/services.php/.env.example; cấu hình chi tiết và chuyển API/local tại [local-ai.md](../local-ai.md). Chọn provider/model sản xuất cần quyết định Q-01 ở tài liệu 09; không tự đổi default để đuổi theo model mới nhất.

Các cấu hình target cần nhóm rõ: agent_enabled, support_session_ui_enabled, deadlines, limiter, quota, ngân sách, lịch trực, contact, retention và storage private. Tên biến thực được chốt trong task, cập nhật config/.env.example không có giá trị bí mật.

Không nhúng key model vào Flutter, JS, link ảnh, log, prompt hoặc exception response. Không gửi key qua chat để AI coding agent xử lý. Nếu lộ key, thu hồi/thay key và rà usage theo runbook.

## 3. Timeout, retry và circuit breaker

CURRENT_CODE gọi provider đồng bộ ngoài transaction: OpenAI connect 5s/timeout 20s, Ollama connect 2s/timeout 20s; client abort 35s. Không retry provider tự động hoặc cloud fallback. Replay processing dưới 30s trả 202; sau 30s retry kết thúc bằng fallback, không gọi model lại. Đây không phải persisted deadline/circuit/queue. Các giá trị dưới đây là TARGET pilot, chưa phải SLA hoặc cấu hình đã có:

| Tham số | Đề xuất | Hành vi |
| --- | --- | --- |
| Mốc UX | 5 giây | Báo chậm và giữ nút hỗ trợ trực tiếp; chưa khẳng định API lỗi |
| Connect timeout | 3 giây | Không chờ kết nối vô hạn |
| Ngân sách upstream/lượt | 15 giây tổng | Bao gồm retry/backoff; lần sau chỉ dùng thời gian còn lại |
| Chờ queue AI | Tối đa 10 giây | Quá hạn thì không gọi upstream |
| Deadline lượt AI | Tối đa 25 giây từ nhận lệnh | Không áp dụng kết quả sau deadline |
| Retry AI tạm thời | Tối đa một lần | Còn quota/thời gian; tôn trọng Retry-After |
| Circuit | 5 lỗi tạm trong 60 giây | Mở 60 giây; một probe khi half-open |

Circuit và counter phải chia sẻ giữa worker. Auth/quota lỗi không spam probe; ngắt đường AI và cảnh báo vận hành. Sai input/schema không retry mù. Timeout không cho biết upstream đã tính phí hay chưa; ghi trạng thái chi phí unknown, giữ reservation đến lúc đối soát hoặc quy tắc đã định.

Circuit chỉ ngắt AI, không ngắt form/phiếu/progress. Khi fallback, tạo/liên kết phiếu bằng dữ liệu server rồi mới nói đã tạo. Server không truy cập được: client ghi chưa xác nhận và hiển thị contact đã cấu hình, không hứa đã bàn giao.

## 4. Outbox và worker — TARGET

CURRENT_CODE chỉ có DB notification trong TX-B và receipt để client retry cùng key; không có support_outbox/support_deliveries hoặc job tự phục hồi pending. Không chạy queue:work rồi coi việc bàn giao đã có recovery tự động.

Transaction ghi phiếu/report/outbox trước. Worker gửi notification DB/push sau commit, có unique event-recipient-channel, retry hữu hạn, backoff và failed queue có người xử lý.

Worker restart phải tiếp tục request đã commit; scheduler độc lập theo dõi quá hạn. Không chạy retry cả công việc chứa nhiều tác dụng phụ nếu chưa bảo đảm idempotency từng phần. Push được dịch vụ chấp nhận không đồng nghĩa đã đọc hoặc quản trị đã tiếp nhận.

Receipt handoff là luồng phục hồi riêng từ M2: TX-A đã commit nhưng TX-B lỗi thì scheduler/worker tìm command pending chưa được claim và tiếp tục cùng intent. Đề xuất pilot: sau lần đồng bộ đầu, tối đa ba retry tự động với backoff 1/5/15 giây, lease theo driver đã kiểm chứng; sau đó failed có owner xử lý thủ công. Pending quá 60 giây cảnh báo vận hành. Retry thủ công phải giữ cùng receipt, kiểm tra lại quyền/state và ghi audit; không tạo intent mới để né unique. Client vẫn thấy chưa tạo được phiếu, không tiếp tục AI; nếu command endpoint cũng mất kết nối thì hiện chưa xác nhận và contact thực. Các con số là cấu hình đề xuất, không cam kết SLA.

Lệnh vận hành minh họa, chỉ dùng sau khi xác định môi trường và job đã có:

```powershell
php artisan queue:work --tries=3 --timeout=60
php artisan schedule:work
```

Timeout worker/lease phải lớn hơn deadline task và nhỏ hơn retry_after thích hợp để tránh hai worker xử lý cùng lease. Cấu hình cụ thể phải được kiểm thử với queue driver chọn thực tế; không sao chép lệnh này làm bằng chứng đã đạt.

## 5. Ngân sách — TARGET, còn thiếu kiểm soát runtime

Code có giới hạn command chat, chưa lưu input/output token/chi phí/usage ledger hoặc đặt trước ngân sách. Không coi giới hạn lượt là bảo đảm không vượt ngân sách. Tài khoản/provider/model sản xuất và ngân sách Q-01/02 vẫn cần chốt; không gọi API trả phí chỉ để kiểm tra tài liệu.

Theo dõi input/output token của mọi lần thử và chi phí của từng provider/model/version. Context gửi lại và báo cáo tổng hợp đều tiêu phí; một ca hỗ trợ không phải một request.

Trước gọi upstream, đặt trước chi phí tối đa theo giới hạn token bằng thao tác nguyên tử; sau đó quyết toán usage. Nếu không có usage đáng tin khi timeout, không tự trả reservation ngay rồi cho gọi vô hạn.

Cấu hình ngân sách/ngày/tháng hữu hạn, cảnh báo ngưỡng và dự phòng chi phí chưa xác định. Kiểm tra hạn mức provider là hard cap hay chỉ cảnh báo; không hứa không thể vượt khi provider không đảm bảo.

Tính riêng hosting Laravel, DB, storage, backup, email/push và vận hành. Không ghi mức giá ví dụ thành giá cố định của sản phẩm. Thiếu ngân sách/người phụ trách: không mở chế độ trả phí cho người dùng thật, vẫn có thể phát triển bằng fake.

## 6. Lịch trực và bàn giao

Phải có người trực, người dự phòng, giờ phục vụ và kênh liên hệ trường xác nhận. Không tự tạo số điện thoại.

Mục tiêu pilot trong giờ trực: an toàn/khẩn 5 phút tiếp nhận; gián đoạn giờ học 10 phút; thông thường 30 phút. Đây là đề xuất để trường xác nhận. Không phải thời gian sửa xong.

Các mức ảnh hưởng trên cần mapping priority/SLA theo Q-07 trước vận hành; không tự đổi teacher=high hoặc thêm enum priority. Report acknowledgement và assignment của ticket là hai mốc độc lập; report mới nối ticket đã phân công vẫn chưa tiếp nhận theo BR-24.

Đọc notification không xác nhận tiếp nhận. Quá hạn tiếp nhận: nhắc và chuyển người trực thay thế theo policy. Đã tiếp nhận nhưng chưa phân công: có deadline/cảnh báo riêng được cấu hình trước vận hành. Ngoài giờ: thể hiện đúng thời gian phục vụ và contact, không hứa KTV đã lên đường.

## 7. Dữ liệu và quyền riêng tư

CURRENT_CODE CR-11 xóa mềm phiên đủ điều kiện, không purge report/messages/receipt/key/ảnh. DATA-CLEAN-01 là thao tác quản trị dữ liệu test được người dùng chỉ định ngày 20/11, có bản sao private; không phải job retention hoặc script để thành viên chạy lại. Không đưa backup/raw dữ liệu/key lên Git. Các chính sách/worker retention dưới đây còn TARGET.

- Gửi API tối thiểu facts cần thiết; không đưa liên hệ người báo vào suy luận nếu không cần.
- Log kỹ thuật mặc định chỉ metadata: request/command/session ID phù hợp quyền, model, prompt/schema/guide version, action, validation decision, usage, độ trễ và lỗi đã làm sạch.
- Nếu muốn trace raw chat để điều tra cần quy trình truy cập có giới hạn, thời hạn và lý do; không bật mặc định.
- Ảnh support mới private, MIME thực, dung lượng, tên an toàn, loại EXIF nhạy cảm; endpoint kiểm tra quyền mỗi lần.
- Không tự thay cơ chế lưu ảnh phiếu cũ ngoài task được giao; đánh giá compatibility riêng.
- Push preview không chứa raw chat hoặc thông tin người báo.
- Retention đề xuất chat 90 ngày; idempotency ít nhất 30 ngày và dài hơn toàn bộ retry/replay. Đây là giá trị cần phê duyệt, không phải job cleanup đã có.
- Cleanup phải xử lý cả snapshot response_data, reports, ảnh, logs, outbox và backup; không xóa key đang bảo vệ một intent có thể được gửi lại.
- Khi thu gọn dữ liệu idempotency, phải thiết kế tombstone/thông báo intent hết hạn để không chạy lại tác dụng phụ. Chưa có thiết kế thì không thêm cleanup tùy tiện.
- Khóa tài khoản dùng is_deleted hiện có; xóa vật lý user có dữ liệu support đang bị FK chặn. Thiết kế ẩn danh/xóa phải có CR, không gỡ FK để “sửa lỗi”.
- Backup có thời hạn riêng; không hứa xóa tức thì mọi bản sao.
- Xác nhận policy của trường và điều khoản provider trước production; không tự dùng chat để fine-tune.

## 8. Dashboard và cảnh báo

Theo dõi: tỷ lệ/loại lỗi AI, queue wait, deadline, discarded results, số chuyển cấp, phiếu chờ tiếp nhận/phân công, notification failed, quota/reservation, chi phí theo ngày, storage và backup.

Cảnh báo cần owner: lỗi khóa/quota, an toàn/sai quyền, backlog queue, outbox lỗi, chi phí bất thường, phiếu quá hạn và backup thất bại. Log không có người xử lý không được coi là cơ chế giám sát hoàn chỉnh.

## 9. Tải và mục tiêu phục hồi

Pilot đề xuất: 20 người dùng đồng thời, tối đa 5 upstream đang chạy, còn lại xếp hàng có deadline hoặc bàn giao trực tiếp. Đo trên hạ tầng mục tiêu.

Mục tiêu pilot: lưu lệnh p95 dưới 2 giây; tạo/liên kết phiếu sau đủ dữ liệu p95 dưới 3 giây. Báo cả độ trễ queue, provider, lỗi và số ca fallback. Nếu chưa đạt, không đổi cách đo để bỏ phần chờ.

Backup/restore đề xuất RPO 24 giờ, RTO 4 giờ. Trường xác nhận mức chấp nhận và diễn tập restore trước production.

## 10. Runbook và release

| Tình huống | Hành động tối thiểu |
| --- | --- |
| Model lỗi/quota | Tắt/ngắt gọi AI; giữ đường báo trực tiếp; kiểm tra usage/key an toàn |
| Hướng dẫn nguy hiểm | Tắt agent hoặc thu hồi guide; giữ snapshot; liên hệ chuyên môn |
| Lỗi quyền/lộ dữ liệu | Hạn chế chức năng liên quan, giữ chứng cứ, xử lý credential nếu cần |
| Queue dừng | Khôi phục worker, kiểm tra lease/outbox, replay có idempotency |
| Phiếu bỏ quên | Kiểm tra lịch trực, acknowledgement và escalation |
| Lỗi sau deploy | Feature flag off, rollback app tương thích, giữ dữ liệu |
| DB/backup sự cố | Theo kế hoạch restore đã diễn tập; không migrate fresh |

Checklist release:

- [ ] Có G4/G5 evidence, regression/AI eval và các quyết định Q-01 đến Q-07 liên quan đã chốt.
- [ ] Review migration/backup, chạy staging trước; không drop bảng để rollback.
- [ ] Bật lưu phiên và đường bàn giao trước, AI flag riêng.
- [ ] Quota, người trực, contact, cảnh báo và người vận hành có thật.
- [ ] Kiểm tra không mất chức năng form cũ và client phiên bản cũ.
- [ ] Có release version, thời điểm, người phụ trách và checklist rollback.
- [ ] Không dùng tài liệu này thay bằng chứng triển khai thực.

Migration ví dụ của M1a chỉ được áp dụng theo môi trường/backup đã kiểm tra. Không chạy migrate:fresh hoặc xóa dữ liệu để giải quyết một lỗi tích hợp.
