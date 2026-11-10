<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_otps')) return;

        Schema::table('email_otps', function (Blueprint $table) {
            if (! Schema::hasColumn('email_otps', 'purpose')) {
                $table->string('purpose', 32)->default('email_verification')->after('email')->index();
            }
            if (! Schema::hasColumn('email_otps', 'attempts')) {
                $table->unsignedTinyInteger('attempts')->default(0)->after('verified_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('email_otps')) return;

        Schema::table('email_otps', function (Blueprint $table) {
            if (Schema::hasColumn('email_otps', 'attempts')) $table->dropColumn('attempts');
            if (Schema::hasColumn('email_otps', 'purpose')) {
                $table->dropIndex(['purpose']);
                $table->dropColumn('purpose');
            }
        });
    }
};
