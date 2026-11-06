<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('categories') || DB::table('categories')->where('name', 'Bàn ghế')->exists()) {
            return;
        }

        $category = ['name' => 'Bàn ghế'];
        if (Schema::hasColumn('categories', 'color')) {
            $category['color'] = '#A16207';
        }
        if (Schema::hasColumn('categories', 'created_at')) {
            $category['created_at'] = now();
        }
        if (Schema::hasColumn('categories', 'updated_at')) {
            $category['updated_at'] = now();
        }

        DB::table('categories')->insert($category);
    }

    public function down(): void
    {
        // Keep this category if it has been used or customized after migration.
    }
};
