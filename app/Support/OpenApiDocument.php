<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class OpenApiDocument
{
    private const TAGS = [
        '01 - Xác thực',
        '02 - Tài khoản cá nhân',
        '03 - Phòng và danh mục',
        '04 - Customer - Phiếu sự cố',
        '05 - Staff - Công việc',
        '06 - Admin - Tổng quan',
        '07 - Admin - Phiếu và lịch',
        '08 - Admin - Tài khoản',
        '09 - Admin - Danh mục lỗi',
        '10 - Admin - Địa điểm',
        '11 - Thông báo',
    ];
    private const STATUSES = ['new', 'assigned', 'in_progress', 'resolved', 'closed', 'reopened'];
    private const ROLES = ['student', 'teacher', 'staff', 'admin'];

    public function build(Request $request): array
    {
        $paths = [];
        foreach (Route::getRoutes() as $route) {
            if (!str_starts_with($route->uri(), 'api/')) continue;
            $path = '/'.substr($route->uri(), 4);
            $action = str_replace('App\\Http\\Controllers\\', '', $route->getActionName());
            $middleware = $route->gatherMiddleware();
            $protected = in_array('auth:sanctum', $middleware, true);
            $role = collect($middleware)->first(fn ($value) => str_starts_with($value, 'role:'));
            foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
                $operation = [
                    'tags' => [$this->tag($path, $action)],
                    'summary' => $this->summary($action, $method),
                    'operationId' => strtolower($method).'_'.trim(preg_replace('/[^a-zA-Z0-9]+/', '_', $path), '_'),
                    'description' => ($role ? 'Quyền: '.substr($role, 5).'. ' : ($protected ? 'Cần Bearer token của tài khoản đang hoạt động. ' : 'Không cần đăng nhập. '))
                        .$this->notes($action),
                    'security' => $protected ? [['bearerAuth' => []]] : [],
                    'responses' => $this->responses($action),
                ];
                $parameters = [];
                preg_match_all('/\{([^}]+)\}/', $path, $matches);
                foreach ($matches[1] as $parameter) {
                    $schema = $parameter === 'code'
                        ? ['type' => 'string', 'example' => 'A.101']
                        : ($parameter === 'id' && str_starts_with($path, '/notifications/')
                            ? ['type' => 'string', 'format' => 'uuid']
                            : $this->id());
                    $parameters[] = ['name' => $parameter, 'in' => 'path', 'required' => true, 'schema' => $schema];
                }
                if ($method === 'GET') $parameters = array_merge($parameters, $this->queries($action));
                if ($parameters) $operation['parameters'] = $parameters;
                $body = $this->body($action, $method);
                if ($body) $operation['requestBody'] = $body;
                $paths[$path][strtolower($method)] = $operation;
            }
        }
        ksort($paths);
        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'Helpdesk API',
                'version' => '1.0.0',
            ],
            'servers' => [['url' => rtrim($request->root(), '/').'/api', 'description' => 'Helpdesk']],
            'tags' => array_map(fn ($name) => ['name' => $name], self::TAGS),
            'paths' => $paths,
            'components' => [
                'securitySchemes' => ['bearerAuth' => ['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'Sanctum token',
                    'description' => 'Dán access_token, không thêm chữ Bearer.']],
                'schemas' => $this->schemas(),
            ],
        ];
    }

    private function id(): array { return ['type' => 'integer', 'minimum' => 1, 'example' => 1]; }
    private function string(string $example = ''): array { return ['type' => 'string', 'example' => $example]; }
    private function enum(array $values): array { return ['type' => 'string', 'enum' => $values]; }
    private function ref(string $name): array { return ['$ref' => '#/components/schemas/'.$name]; }
    private function object(array $properties = [], array $required = []): array
    {
        $schema = ['type' => 'object', 'properties' => $properties ?: new \stdClass()];
        if ($required) $schema['required'] = $required;
        return $schema;
    }
    private function arrayOf(array $items): array { return ['type' => 'array', 'items' => $items]; }
    private function data(array $schema): array { return $this->object(['data' => $schema]); }

    private function tag(string $path, string $action): string
    {
        $tags = self::TAGS;
        if (str_starts_with($path, '/auth/')) return $tags[0];
        if ($path === '/me' || str_starts_with($path, '/account/')) return $tags[1];
        if (str_starts_with($path, '/notifications')) return $tags[10];
        if (str_starts_with($path, '/staff/')) return $tags[4];
        if (str_starts_with($path, '/admin/')) {
            if (str_contains($path, '/dashboard') || str_contains($path, '/reports/')) return $tags[5];
            if (preg_match('~/admin/(tickets|schedules)~', $path)) return $tags[6];
            if (preg_match('~/admin/(users|team|staff)~', $path)) return $tags[7];
            if (str_starts_with($path, '/admin/categories')) return $tags[8];
            return $tags[9];
        }
        return str_starts_with($path, '/tickets') || str_starts_with($path, '/customer/') ? $tags[3] : $tags[2];
    }

    private function summary(string $action, string $method): string
    {
        if ($method === 'POST') {
            $uploadLabel = match ($action) {
                'AccountController@updateProfile' => 'Cập nhật hồ sơ và tải/thay ảnh đại diện',
                'TicketController@store' => 'Gửi báo cáo sự cố kèm ảnh',
                'TicketController@updateReport' => 'Sửa phiếu và thay/xóa ảnh báo cáo',
                'TicketController@staffUpdate' => 'Cập nhật tiến độ và tải ảnh sau sửa chữa',
                default => null,
            };
            if ($uploadLabel) return $uploadLabel;
        }
        $labels = [
            'AuthController@register' => 'Đăng ký sinh viên',
            'AuthController@verifyOtp' => 'Xác thực OTP email',
            'AuthController@resendOtp' => 'Gửi lại OTP email',
            'AuthController@tokenLogin' => 'Đăng nhập lấy Bearer token',
            'AuthController@tokenLogout' => 'Đăng xuất thiết bị hiện tại',
            'AuthController@sendPasswordResetOtp' => 'Gửi OTP quên mật khẩu',
            'AuthController@resetPasswordWithOtp' => 'Đặt lại mật khẩu bằng OTP',
            'AuthController@changePassword' => 'Đổi mật khẩu',
            'AccountController@profile' => 'Xem hồ sơ của tôi',
            'AccountController@updateProfile' => 'Cập nhật tên hoặc avatar',
            'TicketController@rooms' => 'Danh sách phòng',
            'TicketController@room' => 'Tra phòng theo mã QR hoặc mã phòng',
            'TicketController@index' => 'Danh sách phiếu có bộ lọc và phân trang',
            'TicketController@store' => 'Gửi báo cáo sự cố',
            'TicketController@updateReport' => 'Người báo sửa phiếu đang chờ tiếp nhận',
            'TicketController@show' => 'Chi tiết phiếu, ảnh và lịch',
            'TicketController@logs' => 'Lịch sử xử lý phiếu',
            'TicketController@assign' => 'Phân công nhân viên hoặc cập nhật lịch',
            'TicketController@acknowledgeSchedule' => 'Nhân viên xác nhận lịch',
            'TicketController@cancelSchedule' => 'Quản lý hủy lịch',
            'TicketController@staffUpdate' => 'Gửi tiến độ hoặc kết quả sửa chữa',
            'TicketController@close' => 'Duyệt hoàn thành và đóng phiếu',
            'TicketController@reopen' => 'Yêu cầu xử lý lại',
            'TicketController@filterIncidents' => 'Lọc ID phiếu cho giao diện web',
            'TicketController@filterSchedules' => 'Lọc ID lịch cho giao diện web',
            'ApiScheduleController@index' => 'Danh sách lịch theo khoảng ngày',
            'ApiScheduleController@show' => 'Chi tiết lịch',
            'ApiDashboardController@admin' => 'Tổng quan quản lý và dữ liệu biểu đồ',
            'ApiDashboardController@staff' => 'Tổng quan nhân viên',
            'ApiDashboardController@customer' => 'Tổng quan phiếu của tôi',
            'UiController@reportSummary' => 'Thống kê báo cáo theo thời gian',
            'ApiDirectoryController@team' => 'Danh sách đội ngũ có bộ lọc',
            'ApiDirectoryController@teamSummary' => 'Thống kê đội ngũ',
            'ApiDirectoryController@userSummary' => 'Thống kê người dùng',
            'ApiDirectoryController@user' => 'Chi tiết tài khoản và số phiếu',
            'ApiDirectoryController@teamMember' => 'Chi tiết thành viên đội ngũ',
            'ApiDirectoryController@reportedTickets' => 'Phiếu do tài khoản đã báo',
            'ApiDirectoryController@assignedTickets' => 'Phiếu được giao cho nhân viên',
            'ApiDirectoryController@buildingOptions' => 'Tòa nhà kèm phòng để báo sự cố',
            'ApiDirectoryController@locationsSummary' => 'Thống kê tòa nhà và phòng',
            'ApiDirectoryController@building' => 'Chi tiết tòa nhà, phòng và thiết bị',
            'ApiDirectoryController@room' => 'Chi tiết phòng, thiết bị, phiếu và URL QR',
            'UserController@index' => 'Danh sách người dùng',
            'UserController@staff' => 'Nhân viên đang hoạt động và chuyên môn',
            'UserController@filterTeam' => 'Lọc ID đội ngũ cho giao diện web',
            'UserController@createTeamAccount' => 'Tạo tài khoản quản lý hoặc nhân viên',
            'UserController@createStaff' => 'Tạo nhân viên có chuyên môn',
            'UserController@updateStaff' => 'Sửa nhân viên và chuyên môn',
            'UserController@updateRole' => 'Cấp hoặc đổi vai trò tài khoản',
            'UserController@updateStatus' => 'Khóa hoặc mở tài khoản',
            'CategoryController@options' => 'Danh mục lỗi để gửi phiếu',
            'CategoryController@index' => 'Danh mục và số phiếu/thiết bị',
            'CategoryController@store' => 'Tạo danh mục lỗi',
            'CategoryController@update' => 'Cập nhật danh mục lỗi',
            'CategoryController@destroy' => 'Xóa danh mục chưa được sử dụng',
            'LocationController@buildings' => 'Danh sách tòa nhà và phòng',
            'LocationController@createBuilding' => 'Tạo tòa nhà',
            'LocationController@updateBuilding' => 'Sửa tòa nhà',
            'LocationController@deleteBuilding' => 'Xóa tòa nhà và các phòng',
            'LocationController@createRoom' => 'Tạo phòng và mã QR',
            'LocationController@updateRoom' => 'Sửa phòng',
            'LocationController@deleteRoom' => 'Xóa phòng',
            'NotificationController@index' => 'Thông báo của tôi và số chưa đọc',
            'NotificationController@read' => 'Đánh dấu một thông báo đã đọc',
            'NotificationController@readTicket' => 'Đánh dấu thông báo của một phiếu đã đọc',
            'NotificationController@readAll' => 'Đánh dấu tất cả đã đọc',
        ];
        return ($labels[$action] ?? $action).(in_array($method, ['PUT', 'PATCH']) ? ' ('.$method.')' : '');
    }

    private function notes(string $action): string
    {
        return match ($action) {
            'AuthController@register' => 'Chỉ email @ut.edu.vn. Luôn tạo student; vai trò teacher do quản lý cấp.',
            'AuthController@verifyOtp', 'AuthController@resendOtp' => 'OTP lấy từ email, hiệu lực 3 phút; chỉ gửi lại sau 180 giây.',
            'AuthController@sendPasswordResetOtp' => 'Áp dụng mọi email tài khoản hoạt động đã xác thực, không giới hạn email trường.',
            'AuthController@resetPasswordWithOtp' => 'Đặt lại mật khẩu thu hồi các token cũ. Đăng nhập lại sau khi thành công.',
            'TicketController@index', 'TicketController@show' => 'Admin xem tất cả; staff xem phiếu được giao; student/teacher xem phiếu của mình. Customer không nhận ảnh sửa chữa nội bộ.',
            'TicketController@store' => 'Giáo viên được ưu tiên high. Nếu phòng/danh mục đã có phiếu đang xử lý, trả 200 và ticket_number thay vì tạo trùng.',
            'TicketController@updateReport' => 'Chỉ người báo staff/student/teacher và chỉ khi trạng thái new. Dùng POST multipart khi gửi ảnh.',
            'TicketController@assign' => 'Bắt buộc chọn nhân viên, ngày giờ xử lý và nhập ghi chú phân công. Nhân viên phải có chuyên môn phù hợp. scheduled_at phải ở tương lai; gửi schedule_id khi sửa lịch. Lịch ngày đã qua chỉ được xem.',
            'TicketController@staffUpdate' => 'Cần xác nhận lịch trước. Trạng thái resolved cần ảnh minh chứng; dùng POST multipart để gửi ảnh.',
            'TicketController@close' => 'Chỉ duyệt phiếu resolved có ảnh sửa chữa và lịch đã được nhân viên xác nhận.',
            'TicketController@reopen' => 'Chỉ phiếu resolved/closed. Bắt buộc ghi lý do; ảnh sửa chữa cũ sẽ được xóa để gửi kết quả mới.',
            'ApiScheduleController@index' => 'Mặc định ẩn lịch hủy. Staff luôn chỉ xem lịch của mình; from/to dùng YYYY-MM-DD.',
            'CategoryController@destroy' => 'Danh mục có liên kết phiếu, thiết bị hoặc chuyên môn nhân viên trả 422.',
            'AccountController@updateProfile' => 'PATCH nhận JSON để sửa tên. POST multipart hỗ trợ avatar tối đa 5 MB.',
            default => '',
        };
    }

    private function queries(string $action): array
    {
        $page = ['page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1], 'per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 15]];
        $period = $this->enum(['day', 'week', 'month', 'year']);
        $dates = ['from' => ['type' => 'string', 'format' => 'date'], 'to' => ['type' => 'string', 'format' => 'date']];
        $tickets = [
            'search' => ['type' => 'string', 'description' => 'Mã phiếu, nội dung, phòng, tên/email/số điện thoại người báo'],
            'q' => ['type' => 'string', 'description' => 'Bí danh của search'],
            'status' => $this->enum(self::STATUSES), 'category_id' => $this->id(), 'category' => ['type' => 'string'],
            'room_id' => $this->id(), 'building' => ['type' => 'string', 'description' => 'Mã tòa nhà'],
            'reporter_role' => $this->enum(self::ROLES), 'priority' => $this->enum(['low', 'normal', 'high', 'urgent']), 'period' => $period,
        ] + $dates;
        $properties = match ($action) {
            'TicketController@index' => $tickets + $page,
            'TicketController@filterIncidents' => $tickets,
            'ApiScheduleController@index' => $dates + [
                'staff_id' => $this->id(), 'category_id' => $this->id(), 'ticket_status' => $this->enum(self::STATUSES),
                'status' => $this->enum(['scheduled', 'acknowledged', 'cancelled']),
                'include_cancelled' => ['type' => 'boolean', 'default' => false],
            ] + array_replace($page, ['per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50]]),
            'TicketController@filterSchedules' => ['staff' => ['type' => 'string', 'description' => 'Tên nhân viên'], 'category' => $this->id(), 'status' => $this->enum(self::STATUSES)],
            'ApiDashboardController@admin', 'UiController@reportSummary' => ['period' => $period + ['default' => 'month']],
            'ApiDashboardController@staff', 'ApiDashboardController@customer' => ['period' => $period],
            'UserController@index' => ['role' => $this->enum(self::ROLES), 'search' => ['type' => 'string'], 'status' => $this->enum(['active', 'unverified', 'locked'])] + array_replace($page, ['per_page' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 25]]),
            'ApiDirectoryController@team', 'UserController@filterTeam' => [
                'role' => $this->enum(['admin', 'staff']), 'search' => ['type' => 'string'], 'status' => $this->enum(['active', 'unverified', 'locked']),
                $action === 'UserController@filterTeam' ? 'category' : 'category_id' => $this->id(),
            ] + ($action === 'UserController@filterTeam' ? [] : $page),
            'ApiDirectoryController@reportedTickets', 'ApiDirectoryController@assignedTickets', 'ApiDirectoryController@room' => $page,
            'NotificationController@index' => ['page' => $page['page']],
            default => [],
        };
        return collect($properties)->map(function ($schema, $name) {
            $description = $schema['description'] ?? '';
            unset($schema['description']);
            return ['name' => $name, 'in' => 'query', 'required' => false, 'description' => $description, 'schema' => $schema];
        })->values()->all();
    }

    private function body(string $action, string $method): ?array
    {
        $email = ['type' => 'string', 'format' => 'email', 'example' => 'taikhoan@example.com'];
        $password = ['type' => 'string', 'format' => 'password', 'minLength' => 8, 'writeOnly' => true];
        $otp = ['type' => 'string', 'pattern' => '^[0-9]{6}$', 'example' => '123456'];
        $ids = ['type' => 'array', 'minItems' => 1, 'uniqueItems' => true, 'items' => $this->id()];
        $report = [
            'room_code' => $this->string('A.101'), 'room_id' => $this->id(), 'building_code' => $this->string('A'),
            'category_id' => $this->id(), 'title' => $this->string('Máy chiếu không hoạt động'),
            'description' => $this->string('Máy chiếu không lên hình khi bật nguồn.'), 'phone' => $this->string('0900000000'),
            'priority' => $this->enum(['low', 'normal', 'high', 'urgent']),
        ];
        $staff = ['name' => $this->string('Nhân viên kiểm thử'), 'email' => $email, 'phone' => ['type' => 'string'], 'password' => $password, 'category_ids' => $ids];
        [$properties, $required] = match ($action) {
            'AuthController@register' => [
                ['name' => $this->string('Sinh viên kiểm thử'), 'email' => array_replace($email, ['example' => 'sinhvien@ut.edu.vn']), 'password' => $password, 'password_confirmation' => $password],
                ['name', 'email', 'password', 'password_confirmation'],
            ],
            'AuthController@tokenLogin' => [['email' => $email, 'password' => ['type' => 'string', 'format' => 'password'], 'device_name' => $this->string('swagger-ui')], ['email', 'password', 'device_name']],
            'AuthController@verifyOtp' => [['email' => $email, 'otp' => $otp], ['email', 'otp']],
            'AuthController@resendOtp', 'AuthController@sendPasswordResetOtp' => [['email' => $email], ['email']],
            'AuthController@resetPasswordWithOtp' => [['email' => $email, 'otp' => $otp, 'password' => $password, 'password_confirmation' => $password], ['email', 'otp', 'password', 'password_confirmation']],
            'AuthController@changePassword' => [['current_password' => ['type' => 'string', 'format' => 'password'], 'password' => $password, 'password_confirmation' => $password], ['current_password', 'password', 'password_confirmation']],
            'AccountController@updateProfile' => [['name' => $this->string('Tên mới')], []],
            'TicketController@store' => [$report, ['room_code', 'title', 'description', 'category_id']],
            'TicketController@updateReport' => [$report + ['remove_attachments' => ['type' => 'boolean', 'default' => false]], ['room_code', 'room_id', 'building_code', 'title', 'description', 'category_id']],
            'TicketController@assign' => [['staff_id' => $this->id(), 'scheduled_at' => ['type' => 'string', 'format' => 'date-time'], 'schedule_id' => $this->id(), 'note' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 2000]], ['staff_id', 'scheduled_at', 'note']],
            'TicketController@cancelSchedule', 'TicketController@close' => [['note' => ['type' => 'string']], []],
            'TicketController@reopen' => [['note' => $this->string('Vui lòng kiểm tra lại kết quả sửa chữa.')], ['note']],
            'TicketController@staffUpdate' => [['status' => $this->enum(['in_progress', 'resolved']), 'note' => $this->string('Đã kiểm tra và cập nhật tiến độ.')], ['status', 'note']],
            'UserController@createStaff' => [$staff, ['name', 'email', 'password', 'category_ids']],
            'UserController@createTeamAccount' => [$staff + ['role' => $this->enum(['admin', 'staff'])], ['name', 'email', 'password', 'role']],
            'UserController@updateStaff' => [array_diff_key($staff, ['email' => true]), ['name', 'category_ids']],
            'UserController@updateRole' => [['role' => $this->enum(self::ROLES)], ['role']],
            'UserController@updateStatus' => [['is_deleted' => ['type' => 'boolean', 'example' => false]], ['is_deleted']],
            'CategoryController@store', 'CategoryController@update' => [['name' => $this->string('Danh mục kiểm thử'), 'color' => ['type' => 'string', 'pattern' => '^#[0-9A-Fa-f]{6}$', 'example' => '#1686DF']], ['name', 'color']],
            'LocationController@createBuilding', 'LocationController@updateBuilding' => [['code' => $this->string('TEST'), 'name' => $this->string('Tòa nhà kiểm thử'), 'description' => ['type' => 'string']], $action === 'LocationController@createBuilding' ? ['code', 'name'] : []],
            'LocationController@createRoom', 'LocationController@updateRoom' => [['code' => $this->string('TEST101'), 'name' => $this->string('Phòng kiểm thử'), 'floor' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 99]], $action === 'LocationController@createRoom' ? ['code', 'name'] : []],
            default => [[], []],
        };
        if (!$properties || in_array($method, ['GET', 'DELETE'])) return null;
        $content = ['application/json' => ['schema' => $this->object($properties, $required)]];
        $upload = $method === 'POST' && in_array($action, ['AccountController@updateProfile', 'TicketController@store', 'TicketController@updateReport', 'TicketController@staffUpdate'], true);
        if ($upload) {
            $files = $action === 'AccountController@updateProfile'
                ? ['avatar' => ['type' => 'string', 'format' => 'binary', 'description' => 'Chọn ảnh đại diện mới, tối đa 5 MB. Ảnh mới thay thế ảnh cũ.']]
                : ['attachments[]' => ['type' => 'array', 'maxItems' => 5, 'description' => 'Chọn ảnh từ máy tính. Tối đa 5 ảnh, mỗi ảnh tối đa 5 MB.', 'items' => ['type' => 'string', 'format' => 'binary']]];
            $formProperties = $properties + $files;
            if ($action === 'TicketController@updateReport') {
                $formProperties['remove_attachments'] = [
                    'type' => 'integer', 'enum' => [0, 1], 'default' => 0,
                    'description' => '0: giữ ảnh nếu không chọn ảnh mới; 1: xóa toàn bộ ảnh báo cáo. Chọn ảnh mới sẽ thay bộ ảnh cũ.',
                ];
            }
            $content = ['multipart/form-data' => ['schema' => $this->object($formProperties, $required)]] + $content;
        }
        return ['required' => (bool) $required, 'content' => $content];
    }

    private function responses(string $action): array
    {
        $created = in_array($action, ['AuthController@register', 'TicketController@store', 'UserController@createStaff', 'UserController@createTeamAccount', 'CategoryController@store', 'LocationController@createBuilding', 'LocationController@createRoom']);
        // Keep the required OpenAPI response declaration without generating
        // example JSON that could be mistaken for data returned by the API.
        // Swagger's Server response shows the actual status and body, including errors.
        $responses = [($created ? '201' : '200') => ['description' => $created ? 'Đã tạo thành công' : 'Thành công']];
        if ($action === 'TicketController@store') $responses['200'] = ['description' => 'Trùng phiếu đang xử lý, không tạo mới'];
        return $responses;
    }

    private function schemas(): array
    {
        $attachment = $this->object(['id' => $this->id(), 'url' => ['type' => 'string', 'format' => 'uri'], 'original_name' => ['type' => 'string'], 'attachment_type' => $this->enum(['report', 'repair'])]);
        return [
            'User' => $this->object(['id' => $this->id(), 'name' => ['type' => 'string'], 'email' => ['type' => 'string', 'format' => 'email'], 'role' => $this->enum(self::ROLES), 'avatar_url' => ['type' => 'string', 'nullable' => true], 'categories' => $this->arrayOf($this->object())]),
            'TokenResponse' => $this->object(['message' => ['type' => 'string'], 'token_type' => ['type' => 'string', 'example' => 'Bearer'], 'access_token' => ['type' => 'string'], 'user' => $this->ref('User')]),
            'Ticket' => $this->object(['id' => $this->id(), 'ticket_number' => ['type' => 'string'], 'title' => ['type' => 'string'], 'description' => ['type' => 'string'], 'priority' => $this->enum(['low', 'normal', 'high', 'urgent']), 'status' => $this->enum(self::STATUSES), 'attachments' => $this->arrayOf($attachment), 'schedules' => $this->arrayOf($this->ref('Schedule')), 'created_at' => ['type' => 'string', 'format' => 'date-time']]),
            'Schedule' => $this->object(['id' => $this->id(), 'ticket_id' => $this->id(), 'staff_id' => $this->id(), 'status' => $this->enum(['scheduled', 'acknowledged', 'cancelled']), 'starts_at' => ['type' => 'string', 'format' => 'date-time'], 'ends_at' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true], 'ticket' => $this->object()]),
            'Page' => $this->object(['data' => $this->arrayOf($this->object()), 'current_page' => ['type' => 'integer'], 'per_page' => ['type' => 'integer'], 'total' => ['type' => 'integer'], 'last_page' => ['type' => 'integer'], 'next_page_url' => ['type' => 'string', 'nullable' => true]]),
            'Dashboard' => $this->object(['period' => ['type' => 'string', 'nullable' => true], 'total' => ['type' => 'integer'], 'counts' => ['type' => 'object', 'additionalProperties' => ['type' => 'integer']], 'teacher_reports' => ['type' => 'integer'], 'recent_tickets' => $this->arrayOf($this->ref('Ticket')), 'categories' => $this->arrayOf($this->object()), 'buildings' => $this->arrayOf($this->object()), 'unread_notifications' => ['type' => 'integer']]),
            'Error' => $this->object(['message' => ['type' => 'string'], 'errors' => ['type' => 'object', 'additionalProperties' => $this->arrayOf(['type' => 'string'])]], ['message']),
        ];
    }
}
