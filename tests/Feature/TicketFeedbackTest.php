<?php
namespace Tests\Feature;
use App\Models\User;
use App\Models\Ticket;
use App\Notifications\TicketActivity;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
class TicketFeedbackTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:']); DB::purge('sqlite');
        foreach (['0001_01_01_000000_create_users_table.php', '2026_11_09_140000_add_deleted_at_to_users_table.php','2026_11_03_110000_add_is_deleted_to_users_table.php','2026_11_02_135002_create_buildings_table.php','2026_11_02_135003_create_rooms_table.php','2026_11_02_135004_create_categories_table.php','2026_11_02_135005_create_equipment_table.php','2026_11_02_135006_create_tickets_table.php','2026_11_02_135010_create_notifications_table.php','2026_11_08_150000_add_feedback_to_tickets.php'] as $file) (require database_path('migrations/'.$file))->up();
    }
    private function member(string $email): User { return User::create(['name'=>'Student','email'=>$email,'password'=>'Password123','role'=>'student']); }
    private function ticket(User $user, string $status='resolved'): Ticket { return Ticket::create(['ticket_number'=>'HD-'.uniqid(),'reporter_id'=>$user->id,'reporter_name'=>$user->name,'title'=>'Wi-Fi hỏng','description'=>'Mất mạng','status'=>$status]); }
    public function test_feedback_owner_completed_only_and_one_submission(): void {
        $owner=$this->member('owner@ut.edu.vn'); $other=$this->member('other@ut.edu.vn'); $ticket=$this->ticket($owner);
        Sanctum::actingAs($other);
        $this->patchJson('/api/tickets/'.$ticket->id.'/review',['satisfied'=>true])->assertForbidden();
        Sanctum::actingAs($owner);
        $pending=$this->ticket($owner,'new');
        $this->patchJson('/api/tickets/'.$pending->id.'/review',['satisfied'=>true])->assertUnprocessable();
        $this->patchJson('/api/tickets/'.$ticket->id.'/review',[])->assertUnprocessable();
        $this->patchJson('/api/tickets/'.$ticket->id.'/review',['satisfied'=>false,'comment'=>'Cần xử lý nhanh hơn'])->assertOk();
        $this->assertFalse($ticket->fresh()->feedback_satisfied);
        $this->assertNotNull($ticket->fresh()->feedback_at);
        $this->patchJson('/api/tickets/'.$ticket->id.'/review',['satisfied'=>true])->assertStatus(409);
    }
    public function test_search_and_status_filter_respect_ticket_ownership(): void {
        $owner=$this->member('owner@ut.edu.vn'); $other=$this->member('other@ut.edu.vn');
        $mine=$this->ticket($owner); $this->ticket($other); $this->ticket($owner,'new');
        Sanctum::actingAs($owner);
        $this->getJson('/api/tickets?search=Wi-Fi&status_group=completed')->assertOk()->assertJsonCount(1,'data')->assertJsonPath('data.0.id',$mine->id);
    }
    public function test_read_all_does_not_touch_other_users_notifications(): void {
        $owner=$this->member('owner@ut.edu.vn'); $other=$this->member('other@ut.edu.vn');
        foreach([$owner,$other] as $user) $user->notify(new TicketActivity(['title'=>'Cập nhật','message'=>'Đã xử lý']));
        Sanctum::actingAs($owner);
        $this->patchJson('/api/notifications/'.$other->notifications()->first()->id.'/read')->assertNotFound();
        $this->patchJson('/api/notifications/read-all')->assertOk();
        $this->assertEquals(0,$owner->unreadNotifications()->count());
        $this->assertEquals(1,$other->unreadNotifications()->count());
    }
}
