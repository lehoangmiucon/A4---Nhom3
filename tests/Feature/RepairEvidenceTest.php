<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Services\ImageStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RepairEvidenceTest extends TestCase
{
    use RefreshDatabase;

    private function evidence(): TicketAttachment
    {
        Storage::fake('repair-evidence');
        Storage::fake('public');
        $reporter = User::factory()->create(['role' => 'student']);
        $staff = User::factory()->create(['role' => 'staff']);
        $ticket = Ticket::create([
            'ticket_number' => 'SEC-01', 'reporter_id' => $reporter->id,
            'reporter_name' => $reporter->name, 'reporter_email' => $reporter->email,
            'assigned_to_id' => $staff->id, 'title' => 'Repair', 'description' => 'Evidence',
            'status' => 'resolved', 'priority' => 'normal',
        ]);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a9uoAAAAASUVORK5CYII=');
        $path = ImageStorage::attachment(UploadedFile::fake()->createWithContent('repair.png', $png), $ticket, 'repair');

        return TicketAttachment::create([
            'ticket_id' => $ticket->id, 'uploaded_by_id' => $staff->id,
            'path' => $path, 'original_name' => basename($path), 'attachment_type' => 'repair',
        ]);
    }

    public function test_repair_upload_is_private_and_returns_a_protected_url(): void
    {
        $attachment = $this->evidence();
        Storage::disk('public')->assertMissing($attachment->path);
        Storage::disk('repair-evidence')->assertExists($attachment->path);
        $this->assertSame(url("/api/tickets/{$attachment->ticket_id}/attachments/{$attachment->id}"), $attachment->url);
    }

    public function test_guests_invalid_tokens_and_unrelated_accounts_cannot_read_evidence(): void
    {
        $attachment = $this->evidence();
        $url = "/api/tickets/{$attachment->ticket_id}/attachments/{$attachment->id}";
        $this->get($url)->assertUnauthorized();
        $this->withToken('invalid')->get($url)->assertUnauthorized();
        foreach ([$attachment->ticket->reporter, User::factory()->create(['role' => 'teacher']), User::factory()->create(['role' => 'staff'])] as $user) {
            Auth::forgetGuards();
            $this->withToken($user->createToken('test')->plainTextToken)->get($url)->assertForbidden();
        }
        $staff = $attachment->ticket->assignee;
        $staff->update(['is_deleted' => true]);
        Auth::forgetGuards();
        $this->withToken($staff->createToken('test')->plainTextToken)->get($url)->assertForbidden();
    }

    public function test_admin_session_and_assigned_staff_token_can_read_without_shared_caching(): void
    {
        $attachment = $this->evidence();
        $this->actingAs(User::factory()->create(['role' => 'admin']), 'web')
            ->get($attachment->url)->assertOk()->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Cache-Control', 'no-store, private');
        Auth::forgetGuards();
        $this->withToken($attachment->ticket->assignee->createToken('test')->plainTextToken)
            ->get($attachment->url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_attachment_cannot_be_read_through_another_ticket_or_after_reassignment(): void
    {
        $attachment = $this->evidence();
        $otherTicket = $attachment->ticket->replicate();
        $otherTicket->ticket_number = 'OTHER';
        $otherTicket->save();
        $this->actingAs(User::factory()->create(['role' => 'admin']), 'web')
            ->get("/api/tickets/{$otherTicket->id}/attachments/{$attachment->id}")->assertNotFound();
        $staff = $attachment->ticket->assignee;
        $attachment->ticket->update(['assigned_to_id' => null]);
        Auth::forgetGuards();
        $this->actingAs($staff, 'web')->get($attachment->url)->assertForbidden();
    }

    public function test_reopening_deletes_private_evidence_and_invalidates_its_url(): void
    {
        $attachment = $this->evidence();
        $this->actingAs(User::factory()->create(['role' => 'admin']), 'web')
            ->postJson("/api/admin/tickets/{$attachment->ticket_id}/reopen", ['note' => 'Check again'])->assertOk();
        Storage::disk('repair-evidence')->assertMissing($attachment->path);
        $this->get($attachment->url)->assertNotFound();
    }

    public function test_missing_private_file_does_not_fall_back_to_public_storage(): void
    {
        $attachment = $this->evidence();
        Storage::disk('repair-evidence')->delete($attachment->path);
        Storage::disk('public')->put($attachment->path, 'legacy copy');
        $this->actingAs(User::factory()->create(['role' => 'admin']), 'web')
            ->get($attachment->url)->assertNotFound();
    }

    public function test_web_pages_render_protected_evidence_urls(): void
    {
        $attachment = $this->evidence();
        $this->actingAs(User::factory()->create(['role' => 'admin']), 'web');
        foreach (['ticket-detail', 'ticket-review'] as $page) {
            $this->get("/admin/pages/{$page}?ticket={$attachment->ticket_id}")
                ->assertOk()->assertSee($attachment->url, false)
                ->assertDontSee('/storage/'.$attachment->path, false);
        }
    }

    public function test_migration_verifies_copies_removes_public_files_and_is_repeatable(): void
    {
        Storage::fake('public');
        Storage::fake('repair-evidence');
        $path = 'ticket-attachments/repairs/old.png';
        Storage::disk('public')->put($path, 'original evidence');
        $this->artisan('attachments:privatize-repairs', ['--dry-run' => true])->assertSuccessful();
        Storage::disk('public')->assertExists($path);
        Storage::disk('repair-evidence')->assertMissing($path);
        $this->artisan('attachments:privatize-repairs')->assertSuccessful();
        Storage::disk('public')->assertMissing($path);
        $this->assertSame('original evidence', Storage::disk('repair-evidence')->get($path));
        $this->artisan('attachments:privatize-repairs')->assertSuccessful();
    }

    public function test_migration_preserves_both_files_when_destination_differs(): void
    {
        Storage::fake('public');
        Storage::fake('repair-evidence');
        $path = 'ticket-attachments/repairs/conflict.png';
        Storage::disk('public')->put($path, 'original');
        Storage::disk('repair-evidence')->put($path, 'different');
        $this->artisan('attachments:privatize-repairs')->assertFailed();
        $this->assertSame('original', Storage::disk('public')->get($path));
        $this->assertSame('different', Storage::disk('repair-evidence')->get($path));
    }
}
