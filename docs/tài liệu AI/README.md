# Tài liệu AI

> Bộ tài liệu phát triển và nghiệm thu tính năng AI Agent hỗ trợ kỹ thuật phòng học.
> Baseline thiết kế: AI-SDLC-1.1 — 19/11/2026; bản đồng bộ hiện trạng: AI-SDLC-1.2 — 20/11/2026, đến CR-13.
> Phạm vi: bổ sung tính năng vào đề tài hiện có; không đổi tên đề tài.
> Đây là yêu cầu và thiết kế để xây sản phẩm, không phải xác nhận mọi chức năng đã vận hành.

## 1. Điểm bắt đầu

Đọc [yêu cầu](01_YEU_CAU.md), [kiến trúc](02_KIEN_TRUC.md), [hợp đồng dữ liệu/API](03_DU_LIEU_VA_API.md), sau đó chọn task đang đến lượt ở [tasks/todo.md](../../tasks/todo.md). Khi dùng AI viết code, thực hiện [hướng dẫn vibe coding](08_HUONG_DAN_VIBE_CODING.md).

Tên thư mục sử dụng docs hiện có của repository: **docs/tài liệu AI**. Không tạo thêm doc/ song song.

## 2. Cấu trúc tài liệu và trách nhiệm

| Tài liệu | Sở hữu nội dung |
| --- | --- |
| [01 — Yêu cầu](01_YEU_CAU.md) | Mục tiêu, phạm vi, vai trò, yêu cầu FR/NFR và acceptance criteria |
| [02 — Kiến trúc](02_KIEN_TRUC.md) | Ranh giới module, trạng thái, luồng và tính nhất quán |
| [03 — Dữ liệu và API](03_DU_LIEU_VA_API.md) | Schema, payload, phân trang, lỗi và idempotency |
| [04 — An toàn và hợp đồng AI](04_AN_TOAN_VA_AI.md) | Quyền model, cẩm nang, validation, quota và chất lượng |
| [05 — Quy trình và kế hoạch](05_QUY_TRINH_VA_KE_HOACH.md) | Definition of Ready/Done, thứ tự thực hiện và quản lý thay đổi |
| [06 — Kiểm thử và truy vết](06_KIEM_THU_VA_TRUY_VET.md) | Test case, ánh xạ yêu cầu, bằng chứng và điều kiện nghiệm thu |
| [07 — Vận hành](07_VAN_HANH.md) | Staging, ngân sách, dữ liệu, giám sát, phát hành và khôi phục |
| [08 — Vibe coding](08_HUONG_DAN_VIBE_CODING.md) | Quy tắc làm việc của AI coding agent, mẫu giao việc và chống lạc hướng |
| [09 — Quyết định](09_QUYET_DINH_VA_THAY_DOI.md) | Quyết định đã chốt, giả định, quyết định còn thiếu và lịch sử sửa |
| [10 — Use case chi tiết](10_USE_CASE_CHI_TIET.md) | 14 use case: luồng chính, thay thế, ngoại lệ, điều kiện trước/sau |
| [11 — Quy tắc nghiệp vụ](11_QUY_TAC_NGHIEP_VU.md) | BR-01–45, bảng quyết định; đánh dấu quy tắc CURRENT_CODE và TARGET |
| [12 — Ma trận phân quyền](12_MA_TRAN_PHAN_QUYEN.md) | Quyền theo chủ sở hữu, nhiệm vụ, phạm vi dữ liệu và capability |
| [13 — Thiết kế giao diện](13_THIET_KE_GIAO_DIEN.md) | Màn hình web/app/quản trị, trạng thái lỗi, wireframe và khả năng tiếp cận |
| [14 — Từ điển dữ liệu](14_TU_DIEN_DU_LIEU.md) | Thuật ngữ, dữ liệu CURRENT/TARGET, constraint và nguồn gốc dữ liệu |
| [15 — Tình huống và UAT](15_TINH_HUONG_VA_UAT.md) | 10 tình huống nháp, kế hoạch eval và 14 ca nghiệm thu nghiệp vụ |
| [16 — Rủi ro và nghiệm thu](16_RUI_RO_VA_NGHIEM_THU.md) | Risk register, điều kiện sẵn sàng phát triển/pilot/phát hành |
| [Sơ đồ thiết kế](so-do/README.md) | Use case, activity, sequence, ERD, state, component và deployment |
| [templates](templates/) | Mẫu task, thay đổi yêu cầu và báo cáo kiểm chứng |

Nguồn trạng thái task duy nhất: [tasks/todo.md](../../tasks/todo.md).
Kế hoạch thực thi đang chạy: [tasks/plan.md](../../tasks/plan.md).
Bằng chứng M1a: [verification-m1a.md](../../tasks/verification-m1a.md).
Bằng chứng review M1b và BASE-001: [verification-m1b.md](../../tasks/verification-m1b.md).
Bằng chứng hai luồng báo sự cố CR-01: [verification-cr01.md](../../tasks/verification-cr01.md).
API đã có: [docs/mobile-api.md](../mobile-api.md) và Swagger tại /api.

Người mới nhận việc: đọc hiện trạng mục 4 dưới đây → [cách chạy local/API](../local-ai.md) → [kế hoạch nhận việc và file sở hữu](../../tasks/plan.md) → chọn task trong [todo](../../tasks/todo.md). Không cần đọc toàn bộ evidence lịch sử để biết hệ thống hiện chạy gì.

Kiểm chứng gần nhất: [CR-13](../../tasks/verification-cr13.md) — **276 test PHP / 2892 assertions, 62 test JavaScript**, Chrome bố cục 1440/390px với service SQLite :memory: và model giả. Đây là số đo checkpoint, không phải cam kết mọi checkout khác cũng đạt. [Rà soát tài liệu 1.2](../../tasks/verification-doc-sync01.md).

Kiểm chứng đồng bộ hiện trạng: [AI-SDLC-1.2](verification/AI-SDLC-1.2.md). Lịch sử thiết kế: [AI-SDLC-1.1](verification/AI-SDLC-1.1.md), [AI-SDLC-1.0](verification/AI-SDLC-1.0.md).

Trước task nghiệp vụ, đối chiếu use case ở 10, quy tắc ở 11, quyền ở 12 và sơ đồ tương ứng. Không dùng hình tổng quan thay cho điều kiện chi tiết. Mẫu [ca kiểm thử](templates/TEST_SCENARIO.md) và [biên bản UAT](templates/UAT.md) giúp lưu bằng chứng khi thực hiện.

## 3. Quy tắc đọc và xử lý mâu thuẫn

- Yêu cầu mới được người dùng chốt có ưu tiên; ghi thay đổi vào tài liệu và task liên quan.
- Bộ tài liệu này là baseline thiết kế hiện hành cho phần AI.
- Mã nguồn, migration, route và test phản ánh phần đã triển khai; không coi thiết kế là bằng chứng code đã tồn tại.
- Khi code khác hợp đồng, ghi rõ độ lệch; không tự sửa hợp đồng để hợp thức hóa lỗi.
- Trong bộ tài liệu, mỗi nội dung có một file sở hữu ở bảng trên; file khác chỉ dẫn chiếu.
- Tài liệu ý tưởng/kế hoạch trong tài liệu cá nhân được giữ làm lịch sử, không dùng làm nguồn cạnh tranh.
- Không tự đổi nghiệp vụ phiếu, vai trò, provider, model, scope hoặc stack để giải quyết một lỗi cục bộ.

## 4. Hiện trạng đến CR-13 — 20/11/2026

| Phần | CURRENT_CODE / kiểm chứng | Còn TARGET hoặc chưa kiểm chứng |
| --- | --- | --- |
| Phiên và UI web | M1a/M1b + CR-01: hai chế độ thủ công/chat, lưu lịch sử, phân trang, retry/version, khôi phục/logout; CR-05–10 phản hồi gửi/animation/trạng thái và sửa spinner | UAT đăng nhập/CSRF của toàn phiên bản cuối trên staging, screen reader thật |
| Intake và phân nhánh | CR-12 yêu cầu phòng/danh mục/mô tả; CR-13 quan sát máy tính/máy chiếu không khởi động/mất hình, mạng/âm thanh, tránh lặp và chuyển KTV | Nhận diện mọi cách nói, định danh hai thiết bị cùng loại trong một phòng, kho guide published/eligibility và eval độc lập |
| Bàn giao IT | Tạo ticket/report/log/audit/DB notification cùng TX-B, receipt TX-A/retry; brief AI có hash và fallback; danh mục hiện tại; chống trùng cùng phòng/danh mục | Outbox/delivery ledger/recovery tự động, gộp theo thiết bị + triệu chứng, report acknowledgement/progress/follow-up riêng |
| Xóa phiên | CR-11: chủ phiên chỉ xóa mềm handed_off có ticket closed; giữ hồ sơ/phiếu | Purge/retention/ẩn danh theo policy được duyệt |
| Provider | HelpdeskAiService có OpenAI Responses API và Ollama; mặc định repo openai/gpt-4.1-mini; LOCAL-AI-01 đã smoke Ollama/qwen2.5:7b thật tại checkpoint trước | Chưa smoke OpenAI thật hoặc eval model thật sau CR-12/13; không tự chuyển provider/model khi lỗi |
| Database local | Bằng chứng batch 15: hai migration phiên/workflow; batch 16: brief; batch 17: deleted_at. Dữ liệu test đã dọn có bản sao private | Thành viên khác phải kiểm tra migrate:status; không coi batch máy này là schema staging/production |
| App và vận hành | Laravel/API dùng chung đã có; test tự động SQLite :memory: | Flutter/push, quota tiền/token phân tán, circuit/queue, giám sát, tải MySQL, restore, staging và release còn theo todo |

Các số liệu và trạng thái cũ là checkpoint lịch sử tại evidence M1a/M1b, CR-01–13 và LOCAL-AI-01; không ghi đè để giả tất cả đã được kiểm chứng ở phiên bản cuối. [E2E local cũ](../../tasks/verification-e2e-web.md) ghi model thật/backend local tại thời điểm chạy, không thay nghiệm thu CR-13 hoặc staging. Phiếu/phiên ID trong ảnh cũ đã được [dọn test](../../tasks/verification-data-cleanup.md), không phải fixture để gọi lại trên máy người nhận.

CURRENT_CODE nghĩa có mã/bằng chứng trong workspace; không đồng nghĩa đã commit/push, không đồng nghĩa DEPLOYED. Chỉ RELEASED khi có version/môi trường/người nghiệm thu. Trạng thái triển khai chi tiết và thứ tự tiếp tục ở [todo](../../tasks/todo.md) và [plan](../../tasks/plan.md).

## 5. Luồng phát triển bắt buộc

Yêu cầu có mã → thiết kế/hợp đồng → task có phạm vi → test thất bại phù hợp → triển khai tối thiểu → test đạt → review → cập nhật bằng chứng → staging → nghiệm thu → phát hành.

Hoàn thành code không đồng nghĩa đã gọi API thật, đã có app hay đã phát hành. CR-01 đã có API gửi tin và tạo phiếu trong chat, migration local và smoke UI; cấu hình provider, UAT đăng nhập trên staging, MySQL concurrency và các phần TARGET còn theo tasks/todo.md.
