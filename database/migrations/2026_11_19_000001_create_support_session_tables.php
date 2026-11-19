<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('equipment_id')->nullable()->constrained('equipment')->nullOnDelete();
            $table->string('room_label');
            $table->string('room_code');
            $table->string('building_label')->nullable();
            $table->string('equipment_label')->nullable();
            $table->string('status', 32)->default('collecting');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('last_activity_at');
            $table->timestamps();
            $table->index(['user_id', 'status', 'id']);
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('support_sessions')->cascadeOnDelete();
            $table->string('role', 16);
            $table->text('content_text');
            $table->uuid('client_message_id')->nullable();
            $table->timestamps();
            $table->unique(['session_id', 'client_message_id']);
            $table->index(['session_id', 'id']);
        });

        Schema::create('support_idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('operation', 64);
            $table->char('key', 64);
            $table->char('request_hash', 64);
            $table->json('response_data');
            $table->unsignedSmallInteger('response_status');
            $table->timestamps();
            $table->unique(['actor_id', 'operation', 'key'], 'support_idempotency_intent_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_idempotency_keys');
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_sessions');
    }
};
