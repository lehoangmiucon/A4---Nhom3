<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Bảng liên kết nhiều-nhiều giữa nhân viên kỹ thuật và danh mục sự cố họ có thể xử lý.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['staff_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_categories');
    }
};
