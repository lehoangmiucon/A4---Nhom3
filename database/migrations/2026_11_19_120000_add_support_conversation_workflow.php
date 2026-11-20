<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_sessions', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('report_requested')->default(false);
        });
        Schema::create('support_commands', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('session_id')->constrained('support_sessions')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('key_hash', 64);
            $table->string('request_hash', 64);
            $table->string('operation', 30);
            $table->string('status', 30)->default('processing');
            $table->unsignedInteger('version');
            $table->json('response_data')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->timestamps();
            $table->unique(['actor_id', 'key_hash']);
            $table->index(['actor_id', 'created_at']);
        });
        Schema::create('support_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->unique()->constrained('support_sessions')->restrictOnDelete();
            $table->foreignId('ticket_id')->constrained()->restrictOnDelete();
            $table->json('transcript');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_reports');
        Schema::dropIfExists('support_commands');
        Schema::table('support_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn('report_requested');
        });
    }
};
