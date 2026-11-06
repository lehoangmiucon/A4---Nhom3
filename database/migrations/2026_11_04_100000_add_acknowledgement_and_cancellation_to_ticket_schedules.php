<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ticket_schedules')) return;

        Schema::table('ticket_schedules', function (Blueprint $table) {
            if (! Schema::hasColumn('ticket_schedules', 'status')) {
                $table->string('status', 20)->default('scheduled');
            }
            if (! Schema::hasColumn('ticket_schedules', 'acknowledged_at')) {
                $table->timestamp('acknowledged_at')->nullable();
            }
            if (! Schema::hasColumn('ticket_schedules', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable();
            }
            if (! Schema::hasColumn('ticket_schedules', 'cancellation_note')) {
                $table->text('cancellation_note')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ticket_schedules')) return;

        Schema::table('ticket_schedules', function (Blueprint $table) {
            $columns = ['status', 'acknowledged_at', 'cancelled_at', 'cancellation_note'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('ticket_schedules', $column)) $table->dropColumn($column);
            }
        });
    }
};
