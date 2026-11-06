<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ticket_schedules')) return;

        Schema::create('ticket_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['staff_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_schedules');
    }
};
