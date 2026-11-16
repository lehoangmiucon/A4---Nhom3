<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Room;
use App\Models\Building;
use App\Models\Ticket;
use App\Models\TicketSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    /**
     * SEC-01 [KỲ VỌNG AN TOÀN]: Ảnh minh chứng sửa chữa KHÔNG ĐƯỢC lưu trên disk public.
     */
    public function test_sec_01_repair_attachment_must_not_be_stored_on_public_disk(): void
    {
        Storage::fake('public');

        $student = User::factory()->create([
            'role' => 'student',
            'email_verified_at' => now(),
            'is_deleted' => false,
        ]);

        $category = Category::firstOrCreate(['name' => 'Bàn ghế'], ['color' => '#64748B']);
        $building = Building::firstOrCreate(['code' => 'B1'], ['name' => 'Tòa B1']);
        $room = Room::firstOrCreate(['code' => 'B101'], [
            'building_id' => $building->id,
            'name' => 'Phòng B101',
            'qr_code' => 'B101',
        ]);

        $staff = User::factory()->create([
            'role' => 'staff',
            'email_verified_at' => now(),
            'is_deleted' => false,
        ]);
        $staff->categories()->sync([$category->id]);

        $ticket = Ticket::create([
            'ticket_number' => 'HD-20261114-TEST01',
            'reporter_id' => $student->id,
            'reporter_name' => $student->name,
            'reporter_email' => $student->email,
            'room_id' => $room->id,
            'category_id' => $category->id,
            'title' => 'Gãy chân bàn',
            'description' => 'Bàn học bị gãy',
            'status' => 'in_progress',
            'assigned_to_id' => $staff->id,
        ]);

        TicketSchedule::create([
            'ticket_id' => $ticket->id,
            'staff_id' => $staff->id,
            'starts_at' => now(),
            'ends_at' => now()->addHour(),
            'status' => 'acknowledged',
        ]);

        $jpegBytes = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=');
        $repairPhoto = UploadedFile::fake()->createWithContent('evidence.jpg', $jpegBytes)->mimeType('image/jpeg');

        $this->actingAs($staff, 'sanctum')->postJson("/api/staff/tickets/{$ticket->id}/status", [
            'status' => 'resolved',
            'note' => 'Đã sửa xong chân bàn',
            'attachments' => [$repairPhoto],
        ]);

        // KỲ VỌNG BẢO MẬT: Ảnh minh chứng sửa chữa KHÔNG ĐƯỢC xuất hiện trên disk 'public'
        $publicPath = "ticket-attachments/repairs/{$ticket->ticket_number}_1.jpg";
        $this->assertFalse(
            Storage::disk('public')->exists($publicPath),
            'LỖI BẢO MẬT (SEC-01): Tệp ảnh sửa chữa đang bị lưu trực tiếp trên disk public và có thể truy cập không cần đăng nhập!'
        );
    }

    /**
     * SEC-02 [KỲ VỌNG AN TOÀN]: Đổi mật khẩu nhân viên thì toàn bộ token cũ PHẢI bị thu hồi (về 0).
     */
    public function test_sec_02_staff_tokens_must_be_revoked_when_admin_changes_password(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
            'is_deleted' => false,
        ]);

        $category = Category::firstOrCreate(['name' => 'Thiết bị mạng'], ['color' => '#10B981']);

        $staff = User::factory()->create([
            'role' => 'staff',
            'email_verified_at' => now(),
            'is_deleted' => false,
            'password' => Hash::make('OldPassword123'),
        ]);
        $staff->categories()->sync([$category->id]);

        // Nhân viên có 1 token đăng nhập
        $staff->createToken('StaffMobileDevice');
        $this->assertDatabaseCount('personal_access_tokens', 1);

        // Admin đổi mật khẩu nhân viên
        $this->actingAs($admin, 'sanctum')->putJson("/api/admin/staff/{$staff->id}", [
            'name' => 'Nhân viên A',
            'phone' => '0901234567',
            'password' => 'NewPassword456',
            'category_ids' => [$category->id],
        ]);

        // KỲ VỌNG BẢO MẬT: Toàn bộ token cũ PHẢI bị xóa (số lượng = 0)
        $remainingTokens = DB::table('personal_access_tokens')->where('tokenable_id', $staff->id)->count();
        $this->assertEquals(
            0,
            $remainingTokens,
            'LỖI BẢO MẬT (SEC-02): Đổi mật khẩu nhân viên nhưng token cũ không bị thu hồi!'
        );
    }

    /**
     * SEC-03 [KỲ VỌNG AN TOÀN]: Hệ thống PHẢI từ chối tệp SVG qua rule 'image'.
     */
    public function test_sec_03_image_rule_must_reject_svg(): void
    {
        $user = User::factory()->create([
            'role' => 'student',
            'email_verified_at' => now(),
            'is_deleted' => false,
        ]);

        $building = Building::firstOrCreate(['code' => 'CS1'], ['name' => 'Cơ sở 1']);
        $room = Room::firstOrCreate(['code' => 'A101'], ['building_id' => $building->id, 'name' => 'Phòng 101', 'qr_code' => 'A101']);
        $category = Category::firstOrCreate(['name' => 'Máy lạnh'], ['color' => '#3B82F6']);

        $svgFile = UploadedFile::fake()->create('malicious.svg', 10, 'image/svg+xml');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/tickets', [
            'room_code' => $room->code,
            'room_id' => $room->id,
            'building_code' => $building->code,
            'title' => 'Máy lạnh hỏng',
            'description' => 'Máy lạnh không hoạt động',
            'category_id' => $category->id,
            'priority' => 'normal',
            'attachments' => [$svgFile],
        ]);

        // KỲ VỌNG BẢO MẬT: Bắt buộc trả về lỗi 422 từ chối tệp SVG
        $response->assertStatus(422);
    }

    /**
     * SEC-04 [KỲ VỌNG AN TOÀN]: Sau 5 lần nhập sai OTP, mã PHẢI bị khóa và tài khoản KHÔNG ĐƯỢC kích hoạt.
     */
    public function test_sec_04_email_otp_must_lockout_after_too_many_failed_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'sinhvien@ut.edu.vn',
            'role' => 'student',
            'email_verified_at' => null,
            'is_deleted' => false,
        ]);

        $correctOtp = '123456';
        DB::table('email_otps')->insert([
            'email' => $user->email,
            'purpose' => 'email_verification',
            'code_hash' => Hash::make($correctOtp),
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
            'verified_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Thử nhập sai liên tiếp 6 lần
        for ($i = 1; $i <= 6; $i++) {
            $this->postJson('/email/verify-otp', [
                'email' => $user->email,
                'otp' => '999999',
            ]);
        }

        // Lần thứ 7: Nhập mã đúng
        $verifyResponse = $this->postJson('/email/verify-otp', [
            'email' => $user->email,
            'otp' => $correctOtp,
        ]);

        // KỲ VỌNG BẢO MẬT: Sau 6 lần nhập sai, mã phải bị khóa -> không được cho phép xác thực!
        $verifyResponse->assertStatus(422);
        $this->assertNull(
            $user->fresh()->email_verified_at,
            'LỖI BẢO MẬT (SEC-04): Mã OTP không bị khóa sau nhiều lần nhập sai!'
        );
    }

    /**
     * SEC-06 [KỲ VỌNG AN TOÀN]: Tuyến đổi mật khẩu PHẢI chặn brute-force bằng Rate Limiting (HTTP 429).
     */
    public function test_sec_06_password_change_must_be_rate_limited(): void
    {
        $user = User::factory()->create([
            'role' => 'student',
            'email_verified_at' => now(),
            'is_deleted' => false,
            'password' => Hash::make('CorrectPassword123'),
        ]);

        $received429 = false;

        // Gửi liên tiếp 15 lần đoán thử mật khẩu cũ
        for ($i = 1; $i <= 15; $i++) {
            $response = $this->actingAs($user, 'sanctum')->postJson('/api/account/password', [
                'current_password' => "WrongPassword_{$i}",
                'password' => 'NewSecretPassword123',
                'password_confirmation' => 'NewSecretPassword123',
            ]);

            if ($response->status() === 429) {
                $received429 = true;
                break;
            }
        }

        // KỲ VỌNG BẢO MẬT: Bắt buộc phải có request bị chặn 429 Too Many Requests
        $this->assertTrue(
            $received429,
            'LỖI BẢO MẬT (SEC-06): Tuyến đổi mật khẩu không có Rate Limiting, cho phép thử mật khẩu liên tục!'
        );
    }
}
