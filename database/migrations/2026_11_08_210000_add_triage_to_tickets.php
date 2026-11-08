<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('tickets', function (Blueprint $table) {
        $table->string('triage_status', 30)->nullable()->index();
        $table->timestamp('triaged_at')->nullable();
    }); }
    public function down(): void { Schema::table('tickets', function (Blueprint $table) {
        $table->dropIndex(['triage_status']); $table->dropColumn(['triage_status', 'triaged_at']);
    }); }
};
