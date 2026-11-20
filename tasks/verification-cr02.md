# CR-02 — tóm tắt và gợi ý nhanh cho KTV

Ngày: 20/11/2026. Trạng thái: **DONE_CODE và smoke model thật local**, chưa RELEASED. Nguồn task duy nhất: [todo.md](todo.md).

## Trước và sau

Trước: phiếu từ hội thoại chỉ nối các tin người dùng. KTV đã thấy mô tả nhưng chưa có bản tổng hợp/gợi ý. Yêu cầu mới: mô tả lại vấn đề dễ đọc, giữ kiểm tra/kết quả đã báo và gợi ý kiểm tra nhanh cho bộ phận hỗ trợ.

Sau: mô tả phiếu mới gồm phòng/danh mục, tóm tắt AI có nhãn chưa xác minh, các kiểm tra/kết quả người dùng đã nêu, gợi ý AI cho KTV cần kiểm chứng và lời báo gốc. Khi không có brief hợp lệ/nguồn đã thay đổi, dùng mẫu hệ thống có nhãn riêng. Không sửa phiếu đã gửi trước thay đổi.

AI chuẩn bị brief cùng lượt chat, không thêm request model lúc gửi báo cáo hoặc trong transaction. Brief lưu trên support_messages, kèm hash facts/phòng/danh mục và provider/model. Chỉ lưu khi response còn đúng version/trạng thái; lúc bàn giao kiểm tra lại hash. Phần description giới hạn 5.000 ký tự; transcript đầy đủ vẫn lưu trong support_reports. Các reader đã có quyền xem phiếu (gồm người báo) thấy mô tả mới; không cấp KTV quyền xem phiên người khác.

Truy vết: AI-FR-008, AI-NFR-001/003/004/008; UC-04/08/12, BR-41; phạm vi TC-011/012/021/023/025/026. Đây là lát cắt bản bàn giao, không hoàn thành toàn bộ M2/M5 hoặc bộ TC TARGET.

## RED → GREEN và phát hiện qua model thật

- Baseline: PHP 162 passed / 2130 assertions.
- SupportHandoffBriefTest đầu tiên: **4 failed / 1 passed** do mô tả chỉ có lời báo nối, chưa có schema/lưu/render brief.
- Sau triển khai: **6 passed / 45 assertions**. Bao phủ brief mới, KTV được phân công thấy ở API và Blade, KTV khác bị 403, replay không gọi thêm model/không trùng phiếu, facts mới làm stale brief, output sai không phá chat/báo cáo, dữ liệu nguồn dài vẫn giữ transcript và giới hạn description, model reference giải thành trích đoạn thật.
- Smoke Ollama đầu tiên cho thấy model chọn brief=null dù vẫn trả reply; thêm test RED và schema object bắt buộc riêng cho phiên hỗ trợ.
- Model thật từng tự viết lại câu “đã kiểm tra”, thêm bước chưa được người dùng báo. Tăng guard: brief có trích đoạn không khớp nguồn bị loại toàn bộ, không chỉ bỏ câu sai. Viết test RED, sửa và GREEN. Chuyển schema luồng hỗ trợ sang enum U1/U2… tham chiếu lời báo; backend lấy văn bản trực tiếp từ nguồn thay vì để model viết lại việc đã làm.
- Prompt hỗ trợ được tách rõ khỏi prompt soạn draft cũ, yêu cầu tiếng Việt, summary ngắn và draft=null. Schema reply/draft endpoint cũ vẫn tương thích; brief là field bổ sung từ service. Có lần model xen tiếng Trung; điều chỉnh prompt và chạy lại, evidence cuối là tiếng Việt. Không coi một mẫu đạt là bảo đảm chất lượng ngôn ngữ/an toàn cho mọi tình huống.

## Kết quả thực

| Lệnh / kiểm tra | Kết quả |
| --- | --- |
| `php artisan test --compact --filter=SupportHandoffBriefTest` | 6 passed / 45 assertions |
| `php artisan test --compact` | **168 passed / 2175 assertions**, 9,05 giây |
| Pint trên 7 file thuộc thay đổi | Đạt |
| `git diff --check` | Đạt; cảnh báo CRLF của Git |
| `php artisan test --compact storage/framework/testing/cr01/BriefConversationSmokeTest.php` | **1 passed / 15 assertions**, 3,83 giây |
| `php artisan migrate:status --path=database/migrations/2026_11_20_090000_add_handoff_brief_to_support_messages.php` | **Ran, batch 16**, local |

Test tự động dùng SQLite :memory:, fake HTTP. Smoke chạy riêng với Ollama qwen2.5:7b thật; chỉ cho HTTP loopback 127.0.0.1:11434, DB vẫn SQLite bộ nhớ, không ghi phiếu hoặc notification vào DB local đang dùng. Model chat → brief → tạo phiếu → replay → render trang KTV được phân công đạt. Chỉ một request model, một phiếu/report. [JSON evidence](evidence/cr02/offline-brief.json) ghi reply, description và thời gian toàn luồng **3,14 giây** trên mẫu cố định.

Migration local chỉ thêm JSON nullable handoff_brief vào support_messages:

```powershell
php artisan migrate --path=database/migrations/2026_11_20_090000_add_handoff_brief_to_support_messages.php --force
```

Không dùng migrate:fresh, không xóa dữ liệu, không backfill/gọi AI cho phiếu cũ. Không chạy API trả phí.

## Review và giới hạn

Đã review đường đi quyền, version, nguồn dữ liệu và failure/retry bằng CodeGraph. AI không nhận quyền tạo phiếu, phân công hoặc kết luận đã sửa. reported_checks tham chiếu đúng lời người dùng; summary và suggestions vẫn là nội dung suy ra, có thể không đầy đủ/sai nên được ghi nhãn cần KTV kiểm chứng. Guard schema/source không chứng minh mọi câu tóm tắt đúng nghĩa. Prompt dùng lịch sử gần nhất theo giới hạn chat hiện có; transcript gốc là nguồn đối chiếu đầy đủ.

Trang KTV hiện có đã render escaped text với white-space: pre-line; không thay layout/JS ở task này. Đã kiểm tra HTML trả từ route thật trong integration test, chưa smoke browser có đăng nhập hoặc screen reader. Chưa kiểm chứng OpenAI thật, MySQL concurrency, toàn bộ bộ eval/an toàn, staging hoặc production. Phiếu/chat cũ không có brief dùng fallback nếu bàn giao mới; phiếu đã gửi giữ nguyên.

## CR-02D — sửa lỗi nguồn brief (20/11/2026)

Truy vết: AI-FR-008, AI-NFR-003/008, UC-04/08/12, BR-41; TC-011/012/021/025. Baseline thực tế trước sửa: `php artisan test --compact tests/Feature/Support/SupportHandoffBriefTest.php` đạt 6 passed / 45 assertions.

Đọc phiên #4 bằng truy vấn chỉ đọc cho thấy câu “Gửi báo cáo giúp tôi” đã được loại khỏi facts. Nhận định ban đầu cho rằng regex không nhận câu này là sai. Câu “bạn hãy báo cáo giúp mình nhá” mới là yêu cầu chưa được nhận diện, bị tính vào facts và gọi model thêm. Brief được tạo trước khi đủ phòng/danh mục; bổ sung ngữ cảnh sau đó làm hash đổi và buộc dùng fallback. Hai nguyên nhân được xử lý riêng; không sửa phiếu đã gửi hoặc nội dung lịch sử phiên #4.

Thay đổi:

- Nhận thêm cách yêu cầu báo cáo như trong phiên #4, “AI ơi…” và đuôi lịch sự; vẫn chặn câu phủ định/có điều kiện/câu hỏi.
- Phân biệt ý định gửi và câu lệnh thuần: chỉ câu lệnh thuần bị loại khỏi facts. Câu “Gửi báo cáo giúp tôi, máy chiếu có mùi khét” vẫn gửi báo cáo, giữ thông tin mới và làm brief cũ hết hiệu lực.
- Backend lưu `source_context` cùng brief. Bổ sung phòng/danh mục trước đó null được chấp nhận khi facts còn khớp; đổi ngữ cảnh đã biết vẫn loại brief. Brief cũ không có metadata giữ cơ chế strict hash, không suy đoán nguồn hoặc backfill.
- Không gọi thêm model ở handoff, không đổi phân quyền, không migrate hoặc ghi dữ liệu vào database đang dùng.

RED/GREEN: test mới ban đầu tái hiện hai biến thể câu lệnh bị trả 200 thay vì tạo phiếu. Sau sửa nhận diện, ca bổ sung phòng còn RED do mất summary đúng nguyên nhân hash. Thêm metadata nguồn đưa ca này sang GREEN. Review phát hiện cần giữ hành vi gửi khi cùng câu có dữ kiện mới: test riêng RED (200 thay vì 201), sau đó sửa tách chế độ nhận diện và kiểm tra lại GREEN. Một lỗi fixture dùng quan hệ room chưa tồn tại đã được sửa trong test; không coi lỗi fixture là bằng chứng bug.

| Lệnh | Kết quả cuối |
| --- | --- |
| `php artisan test --compact tests/Feature/Support/SupportHandoffBriefTest.php tests/Feature/Support/SupportConversationTest.php` | 32 passed / 196 assertions tại checkpoint trước khi thêm ca thiếu cả phòng và danh mục |
| `php artisan test --compact` | **180 passed / 2235 assertions**, 9,22 giây |
| `vendor/bin/pint --test app/Services/Support/ConversationContext.php app/Services/Support/ConversationBrief.php app/Services/Support/SupportConversationService.php tests/Feature/Support/SupportHandoffBriefTest.php` | Passed |
| `git diff --check` | Đạt; Git có cảnh báo CRLF/LF ở thay đổi có sẵn |

Các ca hồi quy bao phủ giữ summary/gợi ý khi gửi, transcript giữ câu lệnh, bổ sung phòng hoặc cả phòng/danh mục không gọi thêm model, đổi phòng/danh mục đã biết, facts mới, yêu cầu phủ định/có điều kiện/câu hỏi. Test dùng SQLite :memory:, fake HTTP và notification database trong DB bộ nhớ; không gọi OpenAI/Ollama thật trong lượt sửa này. Review correctness/security xác nhận metadata do backend sinh và quyền/idempotency vẫn đi qua luồng cũ. Chưa sửa hoặc eval lỗi lẫn tiếng Trung, chưa UAT browser/staging/production. Cần dùng hội thoại mới để kiểm chứng brief có metadata mới; phiếu đã gửi không tự cập nhật.
