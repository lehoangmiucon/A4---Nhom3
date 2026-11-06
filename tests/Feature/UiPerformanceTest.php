<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Room;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class UiPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 6)->setTime(10, 0));
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'local-test-password', 'role' => 'admin', 'email_verified_at' => now()]);
        $building = Building::create(['code' => 'A', 'name' => 'Building A']);
        $this->room = Room::create(['building_id' => $building->id, 'code' => 'A101', 'name' => 'Room A101']);
        $this->actingAs($this->admin);
    }

    private function ticket(string $status, array $attributes = []): Ticket
    {
        return Ticket::forceCreate(array_merge([
            'ticket_number' => (string) Str::uuid(), 'reporter_id' => $this->admin->id,
            'reporter_name' => 'Reporter', 'room_id' => $this->room->id,
            'title' => 'Test issue', 'description' => 'Test description', 'status' => $status,
            'created_at' => now(), 'updated_at' => now(),
        ], $attributes));
    }

    public function test_location_counts_open_tickets_without_loading_ticket_records(): void
    {
        $this->ticket('new');
        $this->ticket('resolved');
        $this->ticket('closed');
        $loaded = 0;
        Ticket::retrieved(function () use (&$loaded) {
            $loaded++;
        });

        $this->get('/admin/pages/locations')->assertOk()->assertSee('2 phiếu đang mở');
        $this->assertSame(0, $loaded, 'A summary page must count tickets without hydrating every ticket.');
    }

    public function test_dashboard_preserves_period_counts_without_loading_tickets_twice(): void
    {
        $this->ticket('new');
        $this->ticket('closed');
        $this->ticket('new', ['created_at' => now()->subMonth()]);
        $loaded = 0;
        Ticket::retrieved(function () use (&$loaded) {
            $loaded++;
        });

        $this->get('/admin/index')->assertOk()->assertViewHas('dashboardTotal', 2);
        $this->assertSame(2, $loaded, 'Only the two recent tickets should be hydrated, not another copy for building totals.');
    }

    public function test_notifications_reuses_the_list_for_the_same_preview_window(): void
    {
        for ($i = 0; $i < 105; $i++) {
            $this->admin->notifications()->create([
                'id' => (string) Str::uuid(), 'type' => 'App\\Notifications\\TicketActivity',
                'data' => ['ticket_id' => intdiv($i, 20) + 1, 'title' => 'Notice '.$i],
                'read_at' => $i % 2 ? now() : null, 'created_at' => now()->subSeconds(105 - $i),
            ]);
        }
        $loaded = 0;
        DatabaseNotification::retrieved(function () use (&$loaded) {
            $loaded++;
        });

        $response = $this->get('/admin/pages/notifications')->assertOk();
        $this->assertCount(105, $response->viewData('notifications'));
        $preview = $response->viewData('notificationPreviewGroups');
        $this->assertSame([6, 5, 4, 3, 2], $preview->pluck('ticket_id')->all());
        $this->assertSame([5, 20, 20, 20, 20], $preview->pluck('count')->all());
        $this->assertSame(105, $loaded, 'The list and header must reuse the same notification records.');
    }

    public function test_incident_summary_does_not_repeat_the_pending_count_query(): void
    {
        $this->ticket('new');
        $this->ticket('reopened');
        DB::enableQueryLog();
        $response = $this->get('/admin/pages/incidents')->assertOk();
        $this->assertSame(2, $response->viewData('incidentCounts')['pending']);
        $queries = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'count(*)') && $query['bindings'] === ['new', 'reopened']);
        $this->assertCount(1, $queries);
    }

    public function test_reports_preserves_resolved_and_total_counts(): void
    {
        $this->ticket('new');
        $this->ticket('resolved', ['resolved_at' => now()]);
        $this->ticket('closed', ['resolved_at' => now()]);
        $this->get('/admin/pages/reports')->assertOk()
            ->assertViewHas('totalTickets', 3)->assertViewHas('resolvedTickets', 2);
    }
}
