<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('users', function (Blueprint $table) {
        $table->string('member_type', 20)->nullable();
        $table->string('member_code', 80)->nullable()->unique();
    }); }
    public function down(): void { Schema::table('users', function (Blueprint $table) {
        $table->dropUnique(['member_code']); $table->dropColumn(['member_type', 'member_code']);
    }); }
};
