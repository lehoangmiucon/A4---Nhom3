<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

// Giữ migration cũ để bảo toàn lịch sử database hiện tại.
// Schema từng bảng hiện được quản lý bằng các migration riêng phía sau.
return new class extends Migration
{
    public function up(): void
    {
        // Bảng đã được tạo bởi migration này ở các database trước khi tách schema.
        // Database mới tạo từng bảng bằng migration riêng phía sau.
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('ticket_schedules');
        Schema::dropIfExists('ticket_attachments');
        Schema::dropIfExists('ticket_logs');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('equipment');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('buildings');
        Schema::dropIfExists('email_otps');
    }
};
