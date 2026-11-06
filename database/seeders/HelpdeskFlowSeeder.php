<?php

namespace Database\Seeders;

use App\Models\Ticket;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HelpdeskFlowSeeder extends Seeder
{
    public function run(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('activity_logs')) {
            return;
        }

        // Make the general activity log readable using the ticket history
        // already stored in this database. This does not create fake tickets.
        DB::table('ticket_logs')->orderBy('id')->get()->each(function ($log): void {
            $createdAt = $log->created_at ?? now();

            $alreadyLogged = DB::table('activity_logs')
                ->where('action', 'ticket.status_changed')
                ->where('subject_type', Ticket::class)
                ->where('subject_id', $log->ticket_id)
                ->where('created_at', $createdAt)
                ->exists();

            if ($alreadyLogged) {
                return;
            }

            DB::table('activity_logs')->insert([
                'user_id' => $log->user_id,
                'action' => 'ticket.status_changed',
                'subject_type' => Ticket::class,
                'subject_id' => $log->ticket_id,
                'description' => $log->note,
                'properties' => json_encode([
                    'from_status' => $log->from_status,
                    'to_status' => $log->to_status,
                    'source' => 'ticket_logs',
                ], JSON_UNESCAPED_UNICODE),
                'ip_address' => null,
                'user_agent' => null,
                'created_at' => $createdAt,
                'updated_at' => $log->updated_at ?? $createdAt,
            ]);
        });
    }
}
