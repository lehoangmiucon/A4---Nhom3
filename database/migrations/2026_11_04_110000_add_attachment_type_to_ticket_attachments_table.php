<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ticket_attachments') || Schema::hasColumn('ticket_attachments', 'attachment_type')) {
            return;
        }

        Schema::table('ticket_attachments', function (Blueprint $table) {
            $table->string('attachment_type', 20)->default('report')->comment('report: ảnh người báo, repair: ảnh nhân viên sửa chữa');
        });

        DB::table('ticket_attachments')
            ->join('users', 'users.id', '=', 'ticket_attachments.uploaded_by_id')
            ->whereIn('users.role', ['staff', 'admin'])
            ->update(['ticket_attachments.attachment_type' => 'repair']);
    }

    public function down(): void
    {
        if (Schema::hasTable('ticket_attachments') && Schema::hasColumn('ticket_attachments', 'attachment_type')) {
            Schema::table('ticket_attachments', function (Blueprint $table) {
                $table->dropColumn('attachment_type');
            });
        }
    }
};
