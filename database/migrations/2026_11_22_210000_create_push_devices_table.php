<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('push_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->text('token');
            $table->string('platform', 16);
            $table->timestamps();
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_devices');
    }
};
