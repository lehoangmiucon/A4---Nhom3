# Tài liệu AI

> Bộ tài liệu phát triển và nghiệm thu tính năng AI Agent hỗ trợ kỹ thuật phòng học.
> Baseline tài liệu: AI-SDLC-1.1 — 19/11/2026.
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
| [11 — Quy tắc nghiệp vụ](11_QUY_TAC_NGHIEP_VU.md) | 42 quy tắc, bảng quyết định, điều kiện phát bước và bàn giao hai transaction |
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
API đã có: [docs/mobile-api.md](../mobile-api.md) và Swagger tại /api.

Kiểm chứng bộ tài liệu: [AI-SDLC-1.1](verification/AI-SDLC-1.1.md). Lịch sử: [AI-SDLC-1.0](verification/AI-SDLC-1.0.md).

Trước task nghiệp vụ, đối chiếu use case ở 10, quy tắc ở 11, quyền ở 12 và sơ đồ tương ứng. Không dùng hình tổng quan thay cho điều kiện chi tiết. Mẫu [ca kiểm thử](templates/TEST_SCENARIO.md) và [biên bản UAT](templates/UAT.md) giúp lưu bằng chứng khi thực hiện.

## 3. Quy tắc đọc và xử lý mâu thuẫn

- Yêu cầu mới được người dùng chốt có ưu tiên; ghi thay đổi vào tài liệu và task liên quan.
- Bộ tài liệu này là baseline thiết kế hiện hành cho phần AI.
- Mã nguồn, migration, route và test phản ánh phần đã triển khai; không coi thiết kế là bằng chứng code đã tồn tại.
- Khi code khác hợp đồng, ghi rõ độ lệch; không tự sửa hợp đồng để hợp thức hóa lỗi.
- Trong bộ tài liệu, mỗi nội dung có một file sở hữu ở bảng trên; file khác chỉ dẫn chiếu.
- Tài liệu ý tưởng/kế hoạch trong tài liệu cá nhân được giữ làm lịch sử, không dùng làm nguồn cạnh tranh.
- Không tự đổi nghiệp vụ phiếu, vai trò, provider, model, scope hoặc stack để giải quyết một lỗi cục bộ.

## 4. Trạng thái tại baseline

| Phần | Trạng thái có căn cứ |
| --- | --- |
| Chat cũ | Có mã gọi OpenAI, trả reply/draft; chưa chứng minh cấu hình tài khoản trả phí hoạt động |
| M1a: lưu phiên | Có 4 API, migration, policy, service, Swagger và 16 test mới |
| Database thật | Chưa chạy migration M1a trong các bước đã ghi nhận |
| UI theo phiên | Chưa tích hợp; chat cũ không tự trở thành lịch sử phiên |
| Hướng dẫn/công cụ agent | Chưa triển khai theo hợp đồng mới |
| Chuyển cấp/outbox | Chưa triển khai |
| Flutter và push | Chưa triển khai trong phạm vi đã kiểm chứng |
| Vận hành | Chưa nghiệm thu production |

Kết quả gần nhất đã ghi nhận: 135 test đạt, 1 lỗi nền ở UiPerformanceTest; đây là kết quả trước lần biên soạn tài liệu này, không phải lần chạy mới. Không được báo toàn suite xanh.

## 5. Luồng phát triển bắt buộc

Yêu cầu có mã → thiết kế/hợp đồng → task có phạm vi → test thất bại phù hợp → triển khai tối thiểu → test đạt → review → cập nhật bằng chứng → staging → nghiệm thu → phát hành.

Hoàn thành code không đồng nghĩa đã migrate, đã gọi API thật, đã có app hay đã phát hành. Bước tiếp theo mặc định là M1b: giao diện tạo và đọc lại phiên, chưa hứa hội thoại agent đầy đủ khi API gửi tin chưa tồn tại.
