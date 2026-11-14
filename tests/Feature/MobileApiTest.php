<?php

namespace Tests\Feature;

use App\Mail\EmailVerificationOtp;
use App\Models\Building;
use App\Models\Category;
use App\Models\Room;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::create([
            'name' => ucfirst($role), 'email' => uniqid($role).'@example.com',
            'password' => 'Password123!', 'role' => $role, 'email_verified_at' => now(),
        ]);
    }

    private function token(User $user): static
    {
        Auth::forgetGuards();
        return $this->withToken($user->createToken('test-device')->plainTextToken);
    }

    private function ticket(User $reporter, ?User $staff = null): Ticket
    {
        $building = Building::firstOrCreate(['code' => 'TEST'], ['name' => 'Test building']);
        $room = Room::firstOrCreate(['code' => 'TEST101'], [
            'name' => 'Test room', 'building_id' => $building->id, 'qr_code' => 'TEST101',
        ]);
        $category = Category::firstOrCreate(['name' => 'Test issue'], ['color' => '#123456']);
        return Ticket::create([
            'ticket_number' => uniqid('TEST-'), 'reporter_id' => $reporter->id,
            'reporter_name' => $reporter->name, 'reporter_email' => $reporter->email,
            'room_id' => $room->id, 'category_id' => $category->id,
            'equipment_label' => $category->name, 'title' => 'Projector broken',
            'description' => 'Does not turn on', 'status' => $staff ? 'assigned' : 'new',
            'assigned_to_id' => $staff?->id, 'priority' => $reporter->role === 'teacher' ? 'high' : 'normal',
        ]);
    }

    public function test_registration_otp_and_bearer_login_work_without_web_session(): void
    {
        Mail::fake();
        $this->postJson('/api/auth/register', [
            'name' => 'Student', 'email' => 'not-school@example.com',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/api/auth/register', [
            'name' => 'Student', 'email' => 'mobile@ut.edu.vn', 'role' => 'admin',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!',
        ])->assertCreated();
        $user = User::where('email', 'mobile@ut.edu.vn')->firstOrFail();
        $this->assertSame('student', $user->role);
        $code = null;
        Mail::assertSent(EmailVerificationOtp::class, function ($mail) use (&$code) {
            $code = $mail->code;
            return !$mail->passwordReset;
        });
        $this->postJson('/api/auth/email/verify-otp', ['email' => $user->email, 'otp' => $code])->assertOk();
        $this->postJson('/api/auth/email/verify-otp', ['email' => $user->email, 'otp' => $code])->assertUnprocessable();
        $login = $this->postJson('/api/auth/login', [
            'email' => $user->email, 'password' => 'Password123!', 'device_name' => 'phone',
        ])->assertOk()->assertJsonPath('token_type', 'Bearer');
        $this->withToken($login->json('access_token'))->getJson('/api/me')
            ->assertOk()->assertJsonPath('data.id', $user->id)->assertJsonMissingPath('data.password');
        $this->postJson('/api/auth/logout')->assertOk();
        Auth::forgetGuards();
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_password_reset_accepts_non_school_admin_email(): void
    {
        Mail::fake();
        $admin = $this->user('admin');
        $this->postJson('/api/auth/password/forgot', ['email' => $admin->email])->assertOk();
        $code = null;
        Mail::assertSent(EmailVerificationOtp::class, function ($mail) use (&$code) {
            $code = $mail->code;
            return $mail->passwordReset;
        });
        $this->postJson('/api/auth/password/reset', [
            'email' => $admin->email, 'otp' => $code,
            'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!',
        ])->assertOk();
        $this->assertTrue(Hash::check('NewPassword123!', $admin->fresh()->password));
    }

    public function test_profile_upload_returns_json_and_cannot_change_role(): void
    {
        $this->freezeTime();
        Storage::fake('public');
        $user = $this->user('student');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a9uoAAAAASUVORK5CYII=');
        $response = $this->token($user)->postJson('/api/account/profile', [
            'name' => 'Updated name', 'role' => 'admin',
            'avatar' => UploadedFile::fake()->createWithContent('avatar.png', $png),
        ])->assertOk()->assertJsonPath('data.name', 'Updated name')->assertJsonPath('data.role', 'student');
        $this->assertStringContainsString('/storage/avatars/', $response->json('data.avatar_url'));
        $firstAvatar = $user->fresh()->avatar_path;
        $this->assertMatchesRegularExpression('/^avatars\/USER-'.$user->id.'_AVATAR_'.now('Asia/Ho_Chi_Minh')->format('Ymd_His').'_[0-9]{4}\.png$/', $firstAvatar);
        Storage::disk('public')->assertExists($firstAvatar);
        $this->postJson('/api/account/profile', [
            'avatar' => UploadedFile::fake()->createWithContent('avatar.png', $png),
        ])->assertOk();
        $this->assertNotSame($firstAvatar, $user->fresh()->avatar_path);
        Storage::disk('public')->assertMissing($firstAvatar);
        Storage::disk('public')->assertExists($user->fresh()->avatar_path);
        $this->patchJson('/api/account/profile', ['avatar' => UploadedFile::fake()->create('bad.txt')])
            ->assertUnprocessable();
    }

    public function test_dashboards_are_scoped_and_admin_charts_use_selected_period(): void
    {
        $admin = $this->user('admin');
        $student = $this->user('student');
        $teacher = $this->user('teacher');
        $staff = $this->user('staff');
        $this->ticket($teacher, $staff);
        $this->ticket($student);
        $old = $this->ticket($teacher);
        $old->forceFill(['created_at' => now()->subYear()])->save();
        $this->token($admin)->getJson('/api/admin/dashboard?period=month')->assertOk()
            ->assertJsonPath('data.total', 2)->assertJsonPath('data.teacher_reports', 1)
            ->assertJsonPath('data.counts.assigned', 1)
            ->assertJsonCount(1, 'data.categories')->assertJsonPath('data.buildings.0.open_tickets_count', 2);
        $this->token($student)->getJson('/api/customer/dashboard')->assertOk()->assertJsonPath('data.total', 1);
        $this->token($staff)->getJson('/api/staff/dashboard')->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_incidents_show_all_tickets_by_latest_update_and_only_count_unassigned_reopens(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::create(2026, 11, 14, 12));
        $reporter = $this->user('student');
        $staff = $this->user('staff');
        $admin = $this->user('admin');
        $assignedReopen = $this->ticket($reporter, $staff);
        $assignedReopen->forceFill(['status' => 'closed', 'created_at' => now()->subDays(5), 'updated_at' => now()->subDays(5)])->save();
        $new = $this->ticket($reporter);
        $new->forceFill(['created_at' => now()->subDays(4), 'updated_at' => now()->subHour()])->save();
        $pendingReopen = $this->ticket($reporter);
        $pendingReopen->forceFill(['status' => 'reopened', 'created_at' => now()->subDays(3), 'updated_at' => now()->subHours(2)])->save();
        $inProgress = $this->ticket($reporter, $staff);
        $inProgress->forceFill(['status' => 'in_progress', 'created_at' => now()->subDays(2), 'updated_at' => now()->subHours(3)])->save();
        $closed = $this->ticket($reporter, $staff);
        $closed->forceFill(['status' => 'closed', 'created_at' => now()->subDay(), 'updated_at' => now()->subHours(4)])->save();
        $olderIds = [];
        for ($index = 0; $index < 16; $index++) {
            $older = $this->ticket($reporter, $staff);
            $older->forceFill(['status' => 'closed', 'created_at' => now()->subDays(6), 'updated_at' => now()->subDays(6)])->save();
            $olderIds[] = $older->id;
        }

        $this->token($admin)->postJson('/api/admin/tickets/'.$assignedReopen->id.'/reopen', ['note' => 'Kiểm tra lại thiết bị.'])
            ->assertOk()->assertJsonPath('data.status', 'reopened')->assertJsonPath('data.assigned_to_id', $staff->id);
        $expected = [$assignedReopen->id, $new->id, $pendingReopen->id, $inProgress->id, $closed->id, ...array_reverse($olderIds)];
        $page = $this->actingAs($admin, 'web')->get('/admin/pages/incidents')->assertOk()->assertSee('CẬP NHẬT');
        $this->assertSame(2, $page->viewData('pendingTicketCount'));
        $this->assertSame(2, $page->viewData('incidentCounts')['pending']);
        $this->assertSame(21, $page->viewData('incidentCounts')['all']);
        $this->assertNull($page->viewData('incidentFocus'));
        $this->assertSame($expected, $page->viewData('tickets')->pluck('id')->all());

        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try { $document->loadHTML($page->getContent()); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        $xpath = new \DOMXPath($document);
        $menuLink = $xpath->query('//aside//a[starts-with(@aria-label,"Phiếu sự cố,")]')->item(0);
        $this->assertSame(url('/admin/pages/incidents'), $menuLink->getAttribute('href'));
        $assignedStatus = $xpath->query('//tr[@data-ticket-id="'.$assignedReopen->id.'"]/td[6]')->item(0)->textContent;
        $this->assertStringContainsString($staff->name, $assignedStatus);
        $this->assertStringNotContainsString('Cần phân công lại', $assignedStatus);
        $pendingStatus = $xpath->query('//tr[@data-ticket-id="'.$pendingReopen->id.'"]/td[6]')->item(0)->textContent;
        $this->assertStringContainsString('Cần phân công lại', $pendingStatus);

        $focused = $this->get('/admin/pages/incidents?focus=unassigned')->assertOk();
        $this->assertSame([$new->id, $pendingReopen->id], $focused->viewData('tickets')->pluck('id')->all());
        $this->token($admin)->getJson('/api/admin/dashboard')->assertOk()->assertJsonPath('data.unassigned', 2);
        $this->getJson('/api/admin/tickets')->assertOk()->assertJsonPath('total', 21)
            ->assertJsonPath('last_page', 2)->assertJsonPath('data.0.id', $assignedReopen->id);
        $this->getJson('/api/admin/tickets?page=2')->assertOk()->assertJsonCount(6, 'data');
        $this->getJson('/api/admin/tickets/filter')->assertOk()->assertJsonPath('data.ids', $expected);

        $staff->categories()->sync([$pendingReopen->category_id]);
        $this->postJson('/api/admin/tickets/'.$pendingReopen->id.'/assign', [
            'staff_id' => $staff->id, 'scheduled_at' => now()->addHour()->toIso8601String(), 'note' => 'Xử lý lại thiết bị.',
        ])->assertOk();
        $updated = $this->actingAs($admin, 'web')->get('/admin/pages/incidents')->assertOk();
        $this->assertSame(1, $updated->viewData('pendingTicketCount'));
        $this->assertSame($pendingReopen->id, $updated->viewData('tickets')->first()->id);
    }

    public function test_menu_badge_is_hidden_when_reopened_ticket_already_has_a_staff_member(): void
    {
        $staff = $this->user('staff');
        $ticket = $this->ticket($this->user('student'), $staff);
        $ticket->update(['status' => 'reopened']);
        $page = $this->actingAs($this->user('admin'))->get('/admin/pages/incidents')->assertOk()->assertSee($ticket->ticket_number);
        $this->assertSame(0, $page->viewData('pendingTicketCount'));
        $this->assertSame(0, $page->viewData('incidentCounts')['pending']);
        $this->assertStringNotContainsString('class="nav-count"', $page->getContent());
        $focused = $this->get('/admin/pages/incidents?focus=unassigned')->assertOk();
        $this->assertCount(0, $focused->viewData('tickets'));
    }

    public function test_incidents_load_only_current_month_by_default_and_other_periods_on_request(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::create(2026, 11, 14, 12));
        $reporter = $this->user('student');
        $current = $this->ticket($reporter);
        $current->forceFill(['created_at' => now()->subDays(2), 'updated_at' => now()->subDay()])->save();
        $previousMonth = $this->ticket($reporter);
        $previousMonth->forceFill(['created_at' => now()->subMonth(), 'updated_at' => now()])->save();
        $previousYear = $this->ticket($reporter);
        $previousYear->forceFill(['created_at' => now()->subYear(), 'updated_at' => now()])->save();
        $this->actingAs($this->user('admin'), 'web');

        $page = $this->get('/admin/pages/incidents')->assertOk();
        $this->assertSame('month', $page->viewData('incidentPeriod'));
        $this->assertSame([$current->id], $page->viewData('tickets')->pluck('id')->all());
        $page->assertSee('<option value="month" selected>Tháng này</option>', false)
            ->assertDontSee($previousMonth->ticket_number)->assertDontSee($previousYear->ticket_number);
        $year = $this->get('/admin/pages/incidents?period=year')->assertOk();
        $this->assertSame('year', $year->viewData('incidentPeriod'));
        $this->assertSame([$previousMonth->id, $current->id], $year->viewData('tickets')->pluck('id')->all());
        $all = $this->get('/admin/pages/incidents?period=all')->assertOk();
        $this->assertSame('all', $all->viewData('incidentPeriod'));
        $this->assertSame([$previousYear->id, $previousMonth->id, $current->id], $all->viewData('tickets')->pluck('id')->all());
        $all->assertSee('<option value="all" selected>Tất cả thời gian</option>', false);
        $focused = $this->get('/admin/pages/incidents?focus=unassigned')->assertOk();
        $this->assertSame([$current->id], $focused->viewData('tickets')->pluck('id')->all());
        $invalid = $this->get('/admin/pages/incidents?period=invalid')->assertOk();
        $this->assertSame('month', $invalid->viewData('incidentPeriod'));
    }

    public function test_web_and_api_week_filters_include_only_monday_through_saturday(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-15 12:00:00'));
        $reporter = $this->user('student');
        $admin = $this->user('admin');
        $tickets = [];
        foreach (['2026-11-08 23:59:59', '2026-11-09 00:00:00', '2026-11-14 23:59:59',
            '2026-11-15 00:00:00', '2026-11-16 00:00:00'] as $date) {
            $ticket = $this->ticket($reporter);
            $ticket->forceFill(['created_at' => $date, 'updated_at' => $date])->save();
            $tickets[] = $ticket;
        }
        $expectedIds = [$tickets[2]->id, $tickets[1]->id];

        $api = $this->token($admin)->getJson('/api/admin/tickets?period=week')->assertOk();
        $this->assertSame($expectedIds, array_column($api->json('data'), 'id'));
        $this->getJson('/api/admin/dashboard?period=week')->assertOk()->assertJsonPath('data.total', 2);
        $this->getJson('/api/admin/reports/summary?period=week')->assertOk()->assertJsonPath('total', 2);

        $this->actingAs($admin, 'web');
        $page = $this->get('/admin/pages/incidents?period=week')->assertOk();
        $this->assertSame($expectedIds, $page->viewData('tickets')->pluck('id')->all());
        $this->get('/admin/index?period=week')->assertOk()->assertViewHas('dashboardTotal', 2);
        $this->get('/admin/pages/reports?period=week')->assertOk()->assertViewHas('totalTickets', 2);
    }

    public function test_api_authentication_permissions_and_validation_always_return_json(): void
    {
        $this->get('/api/me')->assertUnauthorized()->assertHeader('content-type', 'application/json');
        $student = $this->user('student');
        $this->token($student)->getJson('/api/admin/dashboard')->assertForbidden();
        $this->getJson('/api/staff/schedules')->assertForbidden();
        $this->getJson('/api/admin/users/'.$student->id)->assertForbidden();
        $student->update(['is_deleted' => true]);
        Auth::forgetGuards();
        $this->getJson('/api/me')->assertForbidden();
    }

    public function test_every_protected_api_rejects_missing_and_invalid_bearer_tokens(): void
    {
        $checked = 0;
        foreach (Route::getRoutes() as $route) {
            if (!str_starts_with($route->uri(), 'api/')
                || !in_array('auth:sanctum', $route->gatherMiddleware(), true)) continue;
            $uri = '/'.preg_replace('/\{[^}]+\}/', '1', $route->uri());
            foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
                foreach ([null, '999999|invalid-token'] as $bearer) {
                    Auth::forgetGuards();
                    $headers = ['X-Helpdesk-Client' => 'swagger-ui', 'Referer' => 'http://localhost/api'];
                    if ($bearer) $headers['Authorization'] = 'Bearer '.$bearer;
                    $this->json($method, $uri, [], $headers)
                        ->assertUnauthorized()->assertJsonMissingPath('data');
                }
                $checked++;
            }
        }
        $this->assertGreaterThan(60, $checked);
    }

    public function test_swagger_uses_only_bearer_even_with_a_resolved_web_admin_session(): void
    {
        $admin = $this->user('admin');
        $student = $this->user('student');
        $this->actingAs($admin, 'web');
        $headers = ['X-Helpdesk-Client' => 'swagger-ui', 'Referer' => 'http://localhost/api'];
        $this->getJson('/api/admin/dashboard', $headers)->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->actingAs($admin, 'web');
        $this->getJson('/api/admin/dashboard', $headers + ['Authorization' => 'Bearer 999999|invalid-token'])
            ->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->actingAs($admin, 'web');
        $token = $student->createToken('swagger-student')->plainTextToken;
        $this->getJson('/api/me', $headers + ['Authorization' => 'Bearer '.$token])
            ->assertOk()->assertJsonPath('data.id', $student->id);
        $this->app['auth']->forgetGuards();
        $this->actingAs($admin, 'web');
        $this->getJson('/api/admin/dashboard', $headers + ['Authorization' => 'Bearer '.$token])
            ->assertForbidden();
        $this->assertSame(['web'], config('sanctum.guard'));
        $this->app['auth']->forgetGuards();
        $this->actingAs($admin, 'web');
        $this->getJson('/api/admin/dashboard', ['Referer' => 'http://localhost/admin/index'])
            ->assertOk();
    }

    public function test_schedule_id_can_be_used_to_acknowledge_and_other_staff_are_denied(): void
    {
        $staff = $this->user('staff');
        $other = $this->user('staff');
        $ticket = $this->ticket($this->user('student'), $staff);
        $schedule = TicketSchedule::create([
            'ticket_id' => $ticket->id, 'staff_id' => $staff->id,
            'starts_at' => now(), 'status' => 'scheduled', 'note' => 'Internal preparation',
        ]);
        $this->token($staff)->getJson('/api/staff/schedules?to='.today()->toDateString())
            ->assertOk()->assertJsonPath('data.0.id', $schedule->id)->assertJsonPath('data.0.status', 'scheduled');
        $this->postJson("/api/staff/schedules/{$schedule->id}/acknowledge")->assertOk();
        $this->assertSame('in_progress', $ticket->fresh()->status);
        $this->token($other)->getJson('/api/staff/schedules')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/staff/schedules/{$schedule->id}")->assertForbidden();
        $this->postJson("/api/staff/schedules/{$schedule->id}/acknowledge")->assertForbidden();
        $this->token($this->user('admin'))->getJson('/api/admin/schedules?from=2026-11-12&to=2026-11-01')
            ->assertUnprocessable();
    }

    public function test_past_assignment_time_returns_vietnamese_error_without_changing_ticket(): void
    {
        $staff = $this->user('staff');
        $ticket = $this->ticket($this->user('student'));
        $message = 'Thời gian xử lý phải bằng hoặc sau thời điểm hiện tại.';
        $this->token($this->user('admin'))->postJson('/api/admin/tickets/'.$ticket->id.'/assign', [
            'staff_id' => $staff->id,
            'scheduled_at' => now()->subDay()->format('Y-m-d\TH:i'),
            'note' => 'Kiểm tra thiết bị tại phòng.',
        ])->assertUnprocessable()->assertJsonPath('message', $message)
            ->assertJsonPath('errors.scheduled_at.0', $message);
        $this->assertSame('new', $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->assigned_to_id);
        $this->assertDatabaseCount('ticket_schedules', 0);
    }

    public function test_validation_field_names_and_multiple_error_summary_are_vietnamese(): void
    {
        $this->postJson('/api/auth/email/verify-otp', [])->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Vui lòng nhập email.')
            ->assertJsonPath('errors.otp.0', 'Vui lòng nhập mã OTP.')
            ->assertJsonPath('message', 'Vui lòng nhập email. (và 1 lỗi khác)');
        $this->postJson('/api/auth/email/verify-otp', ['email' => 'invalid', 'otp' => '12'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Địa chỉ email không đúng định dạng.')
            ->assertJsonPath('errors.otp.0', 'Trường mã OTP phải gồm đúng 6 chữ số.');
    }

    public function test_past_schedule_is_read_only_while_today_can_still_be_updated(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::create(2026, 11, 13, 12));
        $staff = $this->user('staff');
        $ticket = $this->ticket($this->user('student'), $staff);
        $staff->categories()->sync([$ticket->category_id]);
        $past = TicketSchedule::create([
            'ticket_id' => $ticket->id, 'staff_id' => $staff->id,
            'starts_at' => today()->subDay()->addHours(9), 'status' => 'scheduled',
        ]);
        $this->token($this->user('admin'))->getJson('/api/admin/schedules/'.$past->id)->assertOk();
        $this->getJson('/api/admin/tickets/'.$ticket->id)->assertOk();
        $this->postJson('/api/admin/tickets/'.$ticket->id.'/assign', [
            'schedule_id' => $past->id, 'staff_id' => $staff->id,
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'note' => 'Cập nhật lịch xử lý.',
        ])->assertUnprocessable()->assertJsonPath('message', 'Lịch của ngày đã qua chỉ được xem, không thể thay đổi phân công.');
        $this->postJson('/api/admin/schedules/'.$past->id.'/cancel')->assertUnprocessable()
            ->assertJsonPath('message', 'Lịch của ngày đã qua chỉ được xem, không thể hủy.');
        $this->assertSame('2026-11-12 09:00', $past->fresh()->starts_at->format('Y-m-d H:i'));
        $this->assertSame('scheduled', $past->fresh()->status);
        $this->assertSame('assigned', $ticket->fresh()->status);

        $today = TicketSchedule::create([
            'ticket_id' => $ticket->id, 'staff_id' => $staff->id,
            'starts_at' => today()->addHours(8), 'status' => 'scheduled',
        ]);
        $this->postJson('/api/admin/tickets/'.$ticket->id.'/assign', [
            'schedule_id' => $today->id, 'staff_id' => $staff->id,
            'scheduled_at' => now()->addHour()->toIso8601String(),
            'note' => 'Chuẩn bị dụng cụ kiểm tra.',
        ])->assertOk();
        $this->assertSame('2026-11-13 13:00', $today->fresh()->starts_at->format('Y-m-d H:i'));
        $this->travelTo(now()->addDay()->startOfDay());
        $this->postJson('/api/admin/schedules/'.$today->id.'/cancel')->assertUnprocessable();
        $this->assertSame('scheduled', $today->fresh()->status);
    }

    public function test_reassigning_staff_does_not_rewrite_historical_schedules(): void
    {
        $staff = $this->user('staff');
        $replacement = $this->user('staff');
        $ticket = $this->ticket($this->user('student'), $staff);
        $replacement->categories()->sync([$ticket->category_id]);
        $past = TicketSchedule::create([
            'ticket_id' => $ticket->id, 'staff_id' => $staff->id,
            'starts_at' => today()->subDay(), 'status' => 'scheduled',
        ]);
        $future = TicketSchedule::create([
            'ticket_id' => $ticket->id, 'staff_id' => $staff->id,
            'starts_at' => today()->addDay(), 'status' => 'scheduled',
        ]);
        $this->token($this->user('admin'))->postJson('/api/admin/tickets/'.$ticket->id.'/assign', [
            'staff_id' => $replacement->id,
            'schedule_id' => $future->id,
            'scheduled_at' => $future->starts_at->toIso8601String(),
            'note' => 'Chuyển lịch sắp tới cho nhân viên thay thế.',
        ])->assertOk();
        $this->assertSame($staff->id, $past->fresh()->staff_id);
        $this->assertSame($replacement->id, $future->fresh()->staff_id);
    }

    public function test_calendar_keeps_past_events_without_add_buttons_or_a_date_picker(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::create(2026, 11, 13, 12));
        $staff = $this->user('staff');
        $ticket = $this->ticket($this->user('student'), $staff);
        TicketSchedule::create([
            'ticket_id' => $ticket->id, 'staff_id' => $staff->id,
            'starts_at' => today()->subDay()->addHours(9), 'status' => 'scheduled',
        ]);
        $response = $this->actingAs($this->user('admin'))->get('/admin/pages/schedule')
            ->assertOk()->assertSee($ticket->ticket_number)->assertDontSee('id="modal-assignment-date"', false);
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try { $document->loadHTML($response->getContent()); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        $xpath = new \DOMXPath($document);
        $this->assertSame(0, $xpath->query('//div[@data-date="2026-11-12"]//button[@data-day-add]')->length);
        $this->assertSame(1, $xpath->query('//div[@data-date="2026-11-12"]//div[@data-schedule-id]')->length);
        $this->assertSame(1, $xpath->query('//div[@data-date="2026-11-13"]//button[@data-day-add]')->length);
        $this->assertSame(1, $xpath->query('//div[@data-date="2026-11-14"]//button[@data-day-add]')->length);
    }

    public function test_assignment_requires_all_information_and_rejects_blank_notes_before_writing(): void
    {
        $staff = $this->user('staff');
        $ticket = $this->ticket($this->user('student'));
        $staff->categories()->sync([$ticket->category_id]);
        $payload = [
            'staff_id' => $staff->id,
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'note' => 'Mang dụng cụ và kiểm tra thiết bị tại phòng.',
        ];
        $this->token($this->user('admin'));
        foreach (['staff_id', 'scheduled_at', 'note'] as $missing) {
            $this->postJson('/api/admin/tickets/'.$ticket->id.'/assign', array_diff_key($payload, [$missing => true]))
                ->assertUnprocessable()->assertJsonValidationErrors($missing);
        }
        foreach (['', '   ', "\t\n"] as $blank) {
            $this->postJson('/api/admin/tickets/'.$ticket->id.'/assign', array_replace($payload, ['note' => $blank]))
                ->assertUnprocessable()->assertJsonPath('errors.note.0', 'Vui lòng nhập ghi chú phân công.');
        }
        $this->assertSame('new', $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->assigned_to_id);
        $this->assertDatabaseCount('ticket_schedules', 0);
        $this->postJson('/api/admin/tickets/'.$ticket->id.'/assign', $payload)->assertOk();
        $schedule = $ticket->schedules()->sole();
        $this->assertSame($staff->id, $schedule->staff_id);
        $this->assertSame($payload['note'], $schedule->note);
        $this->postJson('/api/admin/tickets/'.$ticket->id.'/assign', array_replace($payload, [
            'schedule_id' => $schedule->id, 'note' => ' ',
        ]))->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->assertSame($payload['note'], $schedule->fresh()->note);
        $this->assertDatabaseCount('ticket_schedules', 1);
    }

    public function test_directory_details_locations_and_pagination_are_available_only_to_admin(): void
    {
        $teacher = $this->user('teacher');
        $staff = $this->user('staff');
        $ticket = $this->ticket($teacher, $staff);
        $this->token($this->user('admin'))->getJson("/api/admin/users/{$teacher->id}")
            ->assertOk()->assertJsonPath('data.user.reported_tickets_count', 1)->assertJsonMissingPath('data.user.password');
        $this->getJson("/api/admin/users/{$teacher->id}/tickets?per_page=1")
            ->assertOk()->assertJsonPath('total', 1);
        $this->getJson("/api/admin/team/{$staff->id}")->assertOk()->assertJsonPath('data.user.assigned_tickets_count', 1);
        $this->getJson('/api/admin/team?role=staff')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/admin/team/summary')->assertOk()->assertJsonPath('data.staff', 1);
        $this->getJson('/api/admin/users/summary')->assertOk()->assertJsonPath('data.teachers', 1);
        $this->getJson('/api/admin/users?role=teacher&status=active')->assertOk()
            ->assertJsonPath('data.0.reported_tickets_count', 1);
        $this->getJson('/api/admin/buildings/'.$ticket->room->building_id)->assertOk()->assertJsonCount(1, 'data.rooms');
        $this->getJson('/api/admin/rooms/'.$ticket->room_id)->assertOk()->assertJsonPath('data.tickets.total', 1);
        $this->token($teacher)->getJson('/api/buildings')->assertOk();
        $this->getJson("/api/admin/users/{$teacher->id}")->assertForbidden();
    }

    public function test_ticket_filters_return_full_rows_with_scope_and_safe_schedule_attachment_data(): void
    {
        $teacher = $this->user('teacher');
        $staff = $this->user('staff');
        $ticket = $this->ticket($teacher, $staff);
        $this->ticket($this->user('student'));
        TicketSchedule::create([
            'ticket_id' => $ticket->id, 'staff_id' => $staff->id, 'starts_at' => now(),
            'status' => 'scheduled', 'note' => 'Internal note',
        ]);
        TicketAttachment::create([
            'ticket_id' => $ticket->id, 'path' => 'reports/test.png', 'original_name' => 'test.png', 'attachment_type' => 'report',
        ]);
        TicketAttachment::create([
            'ticket_id' => $ticket->id, 'path' => 'repairs/test.png', 'original_name' => 'test.png', 'attachment_type' => 'repair',
        ]);
        $this->token($this->user('admin'))->getJson('/api/admin/tickets?reporter_role=teacher&period=month&category_id='.$ticket->category_id)
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $ticket->id);
        $this->getJson('/api/admin/tickets/filter?reporter_role=teacher')->assertOk()->assertJsonPath('data.ids.0', $ticket->id);
        $this->getJson('/api/tickets?per_page=999')->assertUnprocessable();
        $detail = $this->token($teacher)->getJson('/api/tickets/'.$ticket->id)
            ->assertOk()->assertJsonCount(1, 'data.attachments')->assertJsonMissingPath('data.schedules.0.note')
            ->assertJsonMissingPath('data.repair_attachments')->assertJsonMissingPath('data.eligible_staff');
        $this->assertStringContainsString('/storage/reports/test.png', $detail->json('data.attachments.0.url'));
        $this->getJson('/api/tickets?q=Projector')->assertOk()->assertJsonPath('total', 1);
        $this->token($this->user('student'))->getJson('/api/tickets/'.$ticket->id)->assertForbidden();
    }

    public function test_report_photo_can_be_kept_replaced_and_removed_with_multipart_flags(): void
    {
        Storage::fake('public');
        $reporter = $this->user('teacher');
        $ticket = $this->ticket($reporter);
        $oldPath = 'ticket-attachments/reports/old.png';
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a9uoAAAAASUVORK5CYII=');
        Storage::disk('public')->put($oldPath, $png);
        $old = TicketAttachment::create([
            'ticket_id' => $ticket->id, 'uploaded_by_id' => $reporter->id,
            'path' => $oldPath, 'original_name' => 'old.png', 'attachment_type' => 'report',
        ]);
        $payload = [
            'room_code' => $ticket->room->code, 'room_id' => $ticket->room_id,
            'building_code' => $ticket->room->building->code, 'category_id' => $ticket->category_id,
            'title' => $ticket->title, 'description' => $ticket->description, 'remove_attachments' => '0',
        ];
        $this->token($reporter)->postJson('/api/tickets/'.$ticket->id, $payload)->assertOk()
            ->assertJsonPath('data.report_attachments.0.id', $old->id);
        Storage::disk('public')->assertExists($oldPath);
        $this->postJson('/api/tickets/'.$ticket->id, $payload + [
            'attachments' => [UploadedFile::fake()->createWithContent('new.png', $png)],
        ])->assertOk()->assertJsonCount(1, 'data.report_attachments');
        $replacement = $ticket->reportAttachments()->sole();
        $this->assertSame(basename($replacement->path), $replacement->original_name);
        $this->assertStringStartsWith($ticket->ticket_number.'_REPORT_', $replacement->original_name);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($replacement->path);
        $this->postJson('/api/tickets/'.$ticket->id, $payload + [
            'attachments' => [
                UploadedFile::fake()->createWithContent('new.png', $png),
                UploadedFile::fake()->createWithContent('new.png', $png),
            ],
        ])->assertOk()->assertJsonCount(2, 'data.report_attachments');
        Storage::disk('public')->assertMissing($replacement->path);
        $paths = $ticket->reportAttachments()->pluck('path')->all();
        $this->assertCount(2, array_unique($paths));
        foreach ($paths as $path) {
            $this->assertStringStartsWith($ticket->ticket_number.'_REPORT_', basename($path));
            Storage::disk('public')->assertExists($path);
        }
        $this->postJson('/api/tickets/'.$ticket->id, array_replace($payload, ['remove_attachments' => '1']))
            ->assertOk()->assertJsonCount(0, 'data.report_attachments');
        foreach ($paths as $path) Storage::disk('public')->assertMissing($path);
    }

    public function test_web_profile_still_redirects_and_dashboard_still_renders(): void
    {
        $this->actingAs($this->user('admin'))->post('/account/profile', ['name' => 'Web admin'])->assertRedirect();
        $this->get('/admin/index')->assertOk()->assertSee('Web admin');
    }

    public function test_web_avatar_upload_is_named_by_account_id_and_uses_the_actual_image_format(): void
    {
        Storage::fake('public');
        $user = $this->user('staff');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a9uoAAAAASUVORK5CYII=');
        $temporaryImage = tmpfile();
        fwrite($temporaryImage, $png);
        try {
            $image = new UploadedFile(stream_get_meta_data($temporaryImage)['uri'], 'client-picture.jpg', null, null, true);
            $this->actingAs($user)->post('/account/profile', ['avatar' => $image])->assertRedirect();
            $path = $user->fresh()->avatar_path;
            $this->assertMatchesRegularExpression('/^avatars\/USER-'.$user->id.'_AVATAR_[0-9]{8}_[0-9]{6}_[0-9]{4}\.png$/', $path);
            Storage::disk('public')->assertExists($path);
        } finally {
            fclose($temporaryImage);
        }
    }

    public function test_full_mobile_workflow_upload_assign_acknowledge_resolve_close_and_notifications(): void
    {
        Storage::fake('public');
        $teacher = $this->user('teacher');
        $staff = $this->user('staff');
        $admin = $this->user('admin');
        $seed = $this->ticket($teacher);
        $seed->update(['status' => 'closed']);
        $staff->categories()->sync([$seed->category_id]);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a9uoAAAAASUVORK5CYII=');
        $created = $this->token($teacher)->postJson('/api/tickets', [
            'room_code' => $seed->room->code, 'category_id' => $seed->category_id,
            'title' => 'A different issue', 'description' => 'A different issue needing repair',
            'attachments' => [UploadedFile::fake()->createWithContent('before.png', $png)],
        ])->assertCreated()->assertJsonPath('data.priority', 'high');
        $id = $created->json('data.id');
        $ticket = Ticket::findOrFail($id);
        $reportImage = $ticket->reportAttachments()->sole();
        $this->assertStringStartsWith($ticket->ticket_number.'_REPORT_', $reportImage->original_name);
        $this->assertSame(basename($reportImage->path), $reportImage->original_name);
        Storage::disk('public')->assertExists($reportImage->path);
        $this->token($admin)->postJson("/api/admin/tickets/{$id}/assign", [
            'staff_id' => $staff->id, 'scheduled_at' => now()->addHour()->toIso8601String(),
            'note' => 'Kiểm tra và sửa chữa thiết bị theo báo cáo.',
        ])->assertOk();
        $scheduleId = $this->token($staff)->getJson('/api/staff/schedules')->assertOk()->json('data.0.id');
        $this->postJson("/api/staff/schedules/{$scheduleId}/acknowledge")->assertOk();
        $this->postJson("/api/staff/tickets/{$id}/status", [
            'status' => 'resolved', 'note' => 'Repaired and verified',
            'attachments' => [UploadedFile::fake()->createWithContent('after.png', $png)],
        ])->assertOk()->assertJsonPath('data.status', 'resolved');
        $repairImage = $ticket->repairAttachments()->sole();
        $this->assertStringStartsWith($ticket->ticket_number.'_REPAIR_', $repairImage->original_name);
        $this->assertSame(basename($repairImage->path), $repairImage->original_name);
        $this->assertNotSame($reportImage->original_name, $repairImage->original_name);
        Storage::disk('public')->assertExists($reportImage->path);
        Storage::disk('public')->assertExists($repairImage->path);
        $this->token($admin)->postJson("/api/admin/tickets/{$id}/close")->assertOk()->assertJsonPath('data.status', 'closed');
        $this->token($teacher)->getJson('/api/notifications')->assertOk()
            ->assertJsonStructure(['unread_count', 'data' => [['payload', 'ticket_id']]]);
        $this->patchJson('/api/notifications/read-all')->assertOk();
        $this->getJson('/api/notifications')->assertJsonPath('unread_count', 0);
    }

    public function test_resend_otp_and_expired_code_are_handled_without_session(): void
    {
        Mail::fake();
        $user = User::create([
            'name' => 'Unverified', 'email' => 'resend@ut.edu.vn',
            'password' => 'Password123!', 'role' => 'student',
        ]);
        $this->postJson('/api/auth/email/resend-otp', ['email' => $user->email])->assertOk();
        $code = null;
        Mail::assertSent(EmailVerificationOtp::class, function ($mail) use (&$code) {
            $code = $mail->code;
            return true;
        });
        \Illuminate\Support\Facades\DB::table('email_otps')->where('email', $user->email)
            ->update(['expires_at' => now()->subMinute()]);
        $this->postJson('/api/auth/email/verify-otp', ['email' => $user->email, 'otp' => $code])
            ->assertUnprocessable();
    }
}
