<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rooms')) return;

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('qr_code')->nullable()->unique();
            $table->timestamps();
            $table->unique(['building_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
