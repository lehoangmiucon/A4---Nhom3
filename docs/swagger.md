# Swagger UI trong Helpdesk

Mở trang:

    http://localhost:8888/Laravelpro/helpdesk/public/api

Nếu dùng php artisan serve:

    http://127.0.0.1:8000/api

OpenAPI được nhúng trực tiếp vào trang /api; không có trang JSON riêng hoặc liên kết xem JSON.
Trang tự xác định địa chỉ /api theo máy chủ đang mở, kể cả khi Laravel nằm trong thư mục con của XAMPP.

## Gọi thử API

1. Mở nhóm 01 - Xác thực.
2. Mở POST /auth/login. Try it out đã bật sẵn, có ô nhập và nút Execute.
3. Điền email, password và device_name (ví dụ swagger-admin), rồi Execute.
4. Sao chép access_token trong Server response và nhập vào Authorize, không thêm chữ Bearer. Trang không tự gắn token sau đăng nhập.
5. Mở endpoint cần kiểm tra, nhập ID/bộ lọc hoặc body, rồi Execute.
6. Xem Server response (Code, Response body, Response headers) để đọc kết quả thật. Không dựng sẵn JSON response mẫu hay liệt kê các mã lỗi cho mọi endpoint.
7. Khi đổi vai trò, đăng nhập tài khoản khác và thay token trong Authorize. POST /auth/logout thành công sẽ tự gỡ token.

OTP nhập mã nhận từ email thật. Mở trực tiếp API cần tải hoặc thay ảnh trong nhóm chức năng.
Các endpoint POST có ảnh chọn sẵn multipart/form-data, hiện ô chọn file avatar hoặc attachments[].
Có thể đổi sang application/json khi chỉ gửi dữ liệu văn bản.
PATCH hỗ trợ sửa dữ liệu JSON; PHP không xử lý upload PATCH multipart theo cùng cách POST.
Các request Execute gọi thẳng API đang chạy và áp dụng điều kiện nghiệp vụ như website.
Swagger UI là công cụ xem tài liệu và gọi API tương tác; các kiểm thử tự động của backend nằm trong tests/Feature.

## Cách tích hợp

- OpenAPI 3.0.3 được xây từ route API đã đăng ký, gồm nhóm chức năng, quyền, body, tham số và response.
- Mọi endpoint có auth:sanctum sử dụng Bearer authentication trong tài liệu; các endpoint công khai không yêu cầu token.
- JavaScript Swagger gửi X-Helpdesk-Client: swagger-ui và không gửi cookie.
- Middleware SwaggerTokenRequests loại cookie và thông tin frontend của request có marker này trước middleware Sanctum.
  Sanctum chỉ xét Bearer token cho những request này, kể cả khi web guard đã có người dùng.
  Vì vậy thiếu/sai token trả 401 và thử token customer/staff không bị phiên đăng nhập admin trên trình duyệt ghi đè.
- GET /rooms, GET /rooms/{code} và GET /categories là các API tra cứu công khai; gọi không có token vẫn trả dữ liệu theo route hiện tại.
  Các API hồ sơ, phiếu, thông báo, dashboard và quản trị đều yêu cầu xác thực; quyền còn được kiểm tra theo vai trò.
- Các request web bình thường vẫn giữ nguyên cơ chế session/CSRF.
- Token chỉ ở bộ nhớ trang; không lưu tự động qua lần tải lại.
- Online validator của Swagger được tắt; spec không được gửi sang dịch vụ validator bên ngoài.

## Tài nguyên Swagger

Swagger UI v5.34.1 được lưu tại public/vendor/swagger-ui để giao diện chạy không cần CDN.
Các file swagger-ui.css, swagger-ui-bundle.js và LICENSE lấy nguyên bản từ:

https://github.com/swagger-api/swagger-ui/tree/v5.34.1

Giấy phép Apache-2.0 nằm trong cùng thư mục.
Hướng dẫn chính thức: https://swagger.io/docs/open-source-tools/swagger-ui/usage/installation/

## Kiểm tra

    C:\xampp\php\php.exe vendor/phpunit/phpunit/phpunit
    node --test tests/JavaScript/swagger-docs.test.cjs

SwaggerDocumentationTest đối chiếu mọi method/route API, quyền token, path parameter,
tham chiếu schema và form upload. Kiểm thử backend vẫn dùng SQLite trong bộ nhớ,
không chạy trên database helpdesk đang sử dụng.
Các kiểm thử JavaScript kiểm tra đăng nhập không tự gắn token, gỡ token sau đăng xuất
và giữ riêng xác thực API với cookie đăng nhập web.
