<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tickets')) return;

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reporter_name');
            $table->string('reporter_email')->nullable();
            $table->string('reporter_phone', 40)->nullable();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->string('building_label')->nullable();
            $table->string('room_label')->nullable();
            $table->foreignId('equipment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('equipment_label')->nullable();
            $table->string('title');
            $table->text('description');
            $table->string('priority')->default('normal');
            $table->string('status')->default('new')->index();
            $table->text('resolution_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
