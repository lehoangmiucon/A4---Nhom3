<?php
namespace Tests\Feature;
use App\Models\User;
use App\Models\Ticket;
use App\Models\TicketSchedule;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
class StaffWorkflowTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:']);DB::purge('sqlite');
        foreach(['0001_01_01_000000_create_users_table.php','2026_11_03_110000_add_is_deleted_to_users_table.php','2026_11_02_160000_create_personal_access_tokens_table.php','2026_11_02_135002_create_buildings_table.php','2026_11_02_135003_create_rooms_table.php','2026_11_02_135004_create_categories_table.php','2026_11_02_135005_create_equipment_table.php','2026_11_02_135006_create_tickets_table.php','2026_11_02_135007_create_ticket_logs_table.php','2026_11_02_135008_create_ticket_attachments_table.php','2026_11_04_110000_add_attachment_type_to_ticket_attachments_table.php','2026_11_02_135009_create_ticket_schedules_table.php','2026_11_04_100000_add_acknowledgement_and_cancellation_to_ticket_schedules.php','2026_11_02_135010_create_notifications_table.php','2026_11_03_100000_create_activity_logs_table.php','2026_11_08_150000_add_feedback_to_tickets.php']as$file)(require database_path('migrations/'.$file))->up();
    }
    private function staff(string $email): User {return User::create(['name'=>'KTV','email'=>$email,'password'=>'OldPassword123','role'=>'staff','email_verified_at'=>now()]);}
    public function test_staff_can_only_process_assignment_after_schedule_acknowledgement():void{
        Storage::fake('public');$owner=$this->staff('owner@ut.edu.vn');$other=$this->staff('other@ut.edu.vn');
        $ticket=Ticket::create(['ticket_number'=>'HD-TEST','reporter_name'=>'Reporter','title'=>'Máy chiếu hỏng','description'=>'Không lên hình','status'=>'assigned','assigned_to_id'=>$owner->id]);
        $schedule=TicketSchedule::create(['ticket_id'=>$ticket->id,'staff_id'=>$owner->id,'starts_at'=>now()->addHour(),'status'=>'scheduled']);
        Sanctum::actingAs($other);
        $this->getJson('/api/staff/tickets')->assertOk()->assertJsonCount(0,'data');
        $this->getJson('/api/staff/tickets/'.$ticket->id)->assertForbidden();
        $this->postJson('/api/staff/schedules/'.$schedule->id.'/acknowledge')->assertForbidden();
        Sanctum::actingAs($owner);
        $this->getJson('/api/staff/schedules')->assertOk()->assertJsonPath('data.0.id',$schedule->id);
        $this->getJson('/api/staff/tickets/'.$ticket->id)->assertOk()->assertJsonPath('data.staff_schedules.0.id',$schedule->id);
        $this->postJson('/api/staff/tickets/'.$ticket->id.'/status',['status'=>'in_progress','note'=>'Kiểm tra'])->assertUnprocessable();
        $this->postJson('/api/staff/schedules/'.$schedule->id.'/acknowledge')->assertOk();
        $this->assertSame('in_progress',$ticket->fresh()->status);
        $this->postJson('/api/staff/tickets/'.$ticket->id.'/status',['status'=>'in_progress','note'=>'Đang kiểm tra'])->assertOk();
        $this->postJson('/api/staff/tickets/'.$ticket->id.'/status',['status'=>'resolved','note'=>'Đã sửa'])->assertUnprocessable();
        $image=UploadedFile::fake()->createWithContent('repair.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aD1sAAAAASUVORK5CYII='));
        $this->post('/api/staff/tickets/'.$ticket->id.'/status',['status'=>'in_progress','explicit_status'=>'1','note'=>'Đang xử lý, thêm ảnh kiểm tra','attachments'=>[$image]],['Accept'=>'application/json'])->assertOk();
        $this->assertSame('in_progress',$ticket->fresh()->status);
        $this->post('/api/staff/tickets/'.$ticket->id.'/status',['status'=>'resolved','note'=>'Đã sửa','attachments'=>[$image]],['Accept'=>'application/json'])->assertOk();
        $this->assertSame('resolved',$ticket->fresh()->status);
        $this->assertEquals(2,$ticket->repairAttachments()->count());
    }
    public function test_staff_password_requires_current_password_without_email_code():void{
        $staff=$this->staff('staff@ut.edu.vn');$staff->createToken('device');Sanctum::actingAs($staff);
        $body=['current_password'=>'WrongPassword','password'=>'NewPassword123','password_confirmation'=>'NewPassword123'];
        $this->postJson('/api/account/password',$body)->assertUnprocessable();
        $this->postJson('/api/account/password',[...$body,'current_password'=>'OldPassword123'])->assertOk();
        $this->assertTrue(Hash::check('NewPassword123',$staff->fresh()->password));$this->assertDatabaseCount('personal_access_tokens',0);
    }
}
