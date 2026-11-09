<?php
namespace Tests\Feature;

use App\Models\{User, Building, Room, Category, Ticket};
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

class TeacherRoleTest extends TestCase
{
    protected function setUp(): void {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:', 'mail.default'=>'array']);
        DB::purge('sqlite');
        foreach (glob(database_path('migrations/*.php')) as $file) (require $file)->up();
    }
    private function user(string $role, string $email): User {
        return User::create(['name'=>$role,'email'=>$email,'role'=>$role,'password'=>'Password123','email_verified_at'=>now()]);
    }
    public function test_only_admin_confirms_teacher_and_revokes_old_credentials(): void {
        $admin=$this->user('admin','admin@ut.edu.vn');$student=$this->user('student','member@ut.edu.vn');$staff=$this->user('staff','staff@example.test');
        Sanctum::actingAs($student);
        $this->patchJson('/api/admin/users/'.$student->id.'/teacher',['is_teacher'=>true])->assertForbidden();
        $this->patchJson('/api/account/profile',['name'=>'Member','role'=>'teacher','member_type'=>'lecturer'])->assertOk();
        $this->assertSame('student',$student->fresh()->role);
        $student->createToken('device');
        DB::table('sessions')->insert(['id'=>'old-session','user_id'=>$student->id,'payload'=>'test','last_activity'=>time()]);
        Sanctum::actingAs($admin);
        $this->patchJson('/api/admin/users/'.$staff->id.'/teacher',['is_teacher'=>true])->assertUnprocessable();
        $this->patchJson('/api/admin/users/'.$admin->id.'/teacher',['is_teacher'=>true])->assertUnprocessable();
        $this->patchJson('/api/admin/users/'.$student->id.'/teacher',['is_teacher'=>true])->assertOk()->assertJsonPath('data.role','teacher');
        $this->assertDatabaseMissing('sessions',['user_id'=>$student->id]);
        $this->assertDatabaseMissing('personal_access_tokens',['tokenable_id'=>$student->id]);
        $this->assertDatabaseHas('activity_logs',['action'=>'user.teacher_confirmed','subject_id'=>$student->id]);
        $this->getJson('/api/admin/users?role=teacher')->assertOk()->assertJsonCount(1,'data');
        $this->get('/admin/pages/users')->assertOk()->assertSee('Danh sách người dùng')->assertSee('LOẠI NGƯỜI DÙNG')->assertSee('Giảng viên');
        $this->get('/admin/pages/user-detail?user='.$student->id)->assertOk()->assertSee('Bỏ xác nhận giảng viên');
        $this->patchJson('/api/admin/users/'.$student->id.'/teacher',['is_teacher'=>false])->assertOk()->assertJsonPath('data.role','student');
    }
    public function test_locked_unverified_or_non_school_accounts_cannot_be_confirmed(): void {
        $admin=$this->user('admin','admin@ut.edu.vn');$student=$this->user('student','person@gmail.com');Sanctum::actingAs($admin);
        $this->patchJson('/api/admin/users/'.$student->id.'/teacher',['is_teacher'=>true])->assertUnprocessable();
        $student->update(['email'=>'person@ut.edu.vn','email_verified_at'=>null]);
        $this->patchJson('/api/admin/users/'.$student->id.'/teacher',['is_teacher'=>true])->assertUnprocessable();
        $student->update(['email_verified_at'=>now(),'is_deleted'=>true]);
        $this->patchJson('/api/admin/users/'.$student->id.'/teacher',['is_teacher'=>true])->assertUnprocessable();
    }
    public function test_teacher_can_use_reporter_flows_without_staff_or_admin_access(): void {
        $teacher=$this->user('teacher','teacher@ut.edu.vn');$other=$this->user('student','other@ut.edu.vn');
        $this->postJson('/login',['email'=>$teacher->email,'password'=>'Password123'])->assertOk()->assertJsonPath('redirect',url('/customer/index'));
        $this->get('/customer/portal')->assertOk();
        $this->get('/customer/account')->assertOk()->assertSee('Giảng viên')->assertDontSee('name="member_code"',false);
        $this->postJson('/api/auth/login',['email'=>$teacher->email,'password'=>'Password123','device_name'=>'teacher-phone'])->assertOk()->assertJsonPath('user.role','teacher');
        Sanctum::actingAs($teacher);
        $this->getJson('/api/admin/users')->assertForbidden();$this->getJson('/api/staff/tickets')->assertForbidden();
        $this->patchJson('/api/account/profile',['name'=>'Giảng viên','phone'=>'0912345678'])->assertOk();
        $this->postJson('/api/account/password',[])->assertForbidden();
        $this->postJson('/api/account/password-code')->assertOk();
        $building=Building::create(['code'=>'A','name'=>'Tòa A']);
        $room=Room::create(['building_id'=>$building->id,'code'=>'A101','name'=>'A101','qr_code'=>'qr101']);
        $category=Category::create(['name'=>'Mạng','color'=>'#008050']);
        $data=['building_code'=>'A','room_code'=>'A101','room_id'=>$room->id,'category_id'=>$category->id,'title'=>'Mất mạng','description'=>'Không có mạng'];
        $response=$this->postJson('/api/tickets',$data)->assertCreated();$id=$response->json('data.id');
        $ticket=Ticket::findOrFail($id);
        $ticket->forceFill(['triage_status'=>'needs_info'])->save();
        $this->getJson('/api/tickets/'.$id)->assertOk()->assertJsonPath('data.can_supplement',true);
        $this->patchJson('/api/tickets/'.$id,[...$data,'description'=>'Đã bổ sung'])->assertOk();
        $ticket->update(['status'=>'closed']);
        $this->patchJson('/api/tickets/'.$id.'/review',['satisfied'=>true])->assertOk();
        $otherTicket=Ticket::create(['ticket_number'=>'OTHER','reporter_id'=>$other->id,'reporter_name'=>$other->name,'reporter_email'=>$other->email,'room_id'=>$room->id,'category_id'=>$category->id,'title'=>'Khác','description'=>'Khác','status'=>'new']);
        $this->getJson('/api/tickets/'.$otherTicket->id)->assertForbidden();
        $this->getJson('/api/tickets')->assertOk()->assertJsonCount(1,'data');
    }
}
