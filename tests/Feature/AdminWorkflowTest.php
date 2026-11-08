<?php
namespace Tests\Feature;
use App\Models\{User,Ticket,TicketSchedule,Category,Room,Building,TicketAttachment};
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
class AdminWorkflowTest extends TestCase {
    protected function setUp():void {parent::setUp();config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:']);DB::purge('sqlite');foreach(glob(database_path('migrations/*.php')) as $file)(require $file)->up();}
    private function user(string $role,string $email):User{return User::create(['name'=>$role,'email'=>$email,'password'=>'Password123','role'=>$role,'email_verified_at'=>now()]);}
    private function ticket(User $reporter):Ticket{
        $building=Building::create(['code'=>'TEST-'.uniqid(),'name'=>'Tòa kiểm thử']);$room=Room::create(['building_id'=>$building->id,'code'=>'TEST-'.uniqid(),'name'=>'Phòng kiểm thử','qr_code'=>uniqid()]);$category=Category::create(['name'=>'Mạng '.uniqid(),'color'=>'#008050']);
        return Ticket::create(['ticket_number'=>'HD-'.uniqid(),'reporter_id'=>$reporter->id,'reporter_name'=>$reporter->name,'reporter_email'=>$reporter->email,'room_id'=>$room->id,'category_id'=>$category->id,'title'=>'Mất mạng','description'=>'Không kết nối được','status'=>'new']);
    }
    public function test_admin_approval_assignment_schedule_and_close_flow():void{
        $admin=$this->user('admin','admin@ut.edu.vn');$staff=$this->user('staff','staff@ut.edu.vn');$reporter=$this->user('student','sv@ut.edu.vn');$ticket=$this->ticket($reporter);Sanctum::actingAs($staff);
        $this->getJson('/api/admin/summary')->assertForbidden();$this->getJson('/api/admin/users')->assertForbidden();
        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/summary')->assertOk()->assertJsonPath('data.pending_review',1);
        $this->postJson('/api/admin/tickets/'.$ticket->id.'/triage',['decision'=>'needs_info'])->assertUnprocessable();
        $this->postJson('/api/admin/tickets/'.$ticket->id.'/triage',['decision'=>'needs_info','note'=>'Mô tả rõ thiết bị'])->assertOk();
        $this->assertSame('needs_info',$ticket->fresh()->triage_status);
        Sanctum::actingAs($reporter);
        $this->getJson('/api/tickets/'.$ticket->id)->assertOk()->assertJsonPath('data.can_supplement',true);
        $this->patchJson('/api/tickets/'.$ticket->id,['title'=>'Mất mạng sau khi khởi động','description'=>'Đã bổ sung chi tiết thiết bị','room_id'=>$ticket->room_id,'room_code'=>$ticket->room->code,'building_code'=>$ticket->room->building->code,'category_id'=>$ticket->category_id])->assertOk();
        $this->assertNull($ticket->fresh()->triage_status);
        Sanctum::actingAs($admin);
        $this->postJson('/api/admin/tickets/'.$ticket->id.'/triage',['decision'=>'approved'])->assertOk();
        $this->getJson('/api/admin/tickets?queue=assignment')->assertOk()->assertJsonPath('data.0.id',$ticket->id);
        $body=['staff_id'=>$staff->id,'scheduled_at'=>now()->addDay()->toIso8601String(),'priority'=>'high'];
        $this->postJson('/api/admin/tickets/'.$ticket->id.'/assign',$body)->assertUnprocessable();
        $staff->categories()->sync([$ticket->category_id]);
        $this->postJson('/api/admin/tickets/'.$ticket->id.'/assign',$body)->assertOk();
        $this->assertSame('high',$ticket->fresh()->priority);$this->assertEquals(1,$ticket->schedules()->count());
        $second=$this->ticket($reporter);$staff->categories()->attach($second->category_id);
        $this->postJson('/api/admin/tickets/'.$second->id.'/assign',$body)->assertUnprocessable()->assertJsonValidationErrors('scheduled_at');
        $this->postJson('/api/admin/tickets/'.$ticket->id.'/close')->assertUnprocessable();
        $ticket->update(['status'=>'resolved']);
        $this->postJson('/api/admin/tickets/'.$ticket->id.'/close')->assertUnprocessable();
        $ticket->schedules()->update(['status'=>'acknowledged','acknowledged_at'=>now()]);
        TicketAttachment::create(['ticket_id'=>$ticket->id,'uploaded_by_id'=>$staff->id,'path'=>'test.png','original_name'=>'test.png','attachment_type'=>'repair']);
        $this->postJson('/api/admin/tickets/'.$ticket->id.'/close',['note'=>'Đạt yêu cầu'])->assertOk();$this->assertSame('closed',$ticket->fresh()->status);
        $this->postJson('/api/admin/tickets/'.$ticket->id.'/reopen',['note'=>'Còn lỗi'])->assertOk();$this->assertSame('reopened',$ticket->fresh()->status);
    }
    public function test_admin_user_search_create_staff_and_lock_controls():void{
        $admin=$this->user('admin','admin@ut.edu.vn');$lecturer=$this->user('student','gv@ut.edu.vn');DB::table('users')->where('id',$lecturer->id)->update(['member_type'=>'lecturer']);Sanctum::actingAs($admin);
        $this->getJson('/api/admin/users?member_type=lecturer')->assertOk()->assertJsonCount(1,'data');
        $this->patchJson('/api/admin/users/'.$admin->id.'/status',['is_deleted'=>true])->assertUnprocessable();
        $this->patchJson('/api/admin/users/'.$lecturer->id.'/status',['is_deleted'=>true])->assertOk();
        $this->getJson('/api/admin/users?status=locked')->assertOk()->assertJsonCount(1,'data');
        $category=Category::create(['name'=>'Test category','color'=>'#008050']);
        $this->postJson('/api/admin/staff',['name'=>'KTV mới','email'=>'newstaff@ut.edu.vn','password'=>'Password123','category_ids'=>[$category->id]])->assertCreated();
        $staff=User::where('email','newstaff@ut.edu.vn')->firstOrFail();
        $this->getJson('/api/admin/users/'.$staff->id)->assertOk()->assertJsonPath('data.categories.0.id',$category->id);
        $staff->createToken('test');
        $this->putJson('/api/admin/staff/'.$staff->id,['name'=>'KTV cập nhật','password'=>'UpdatedPass123','category_ids'=>[$category->id]])->assertOk();
        $this->assertEquals(0,$staff->tokens()->count());
    }
}
