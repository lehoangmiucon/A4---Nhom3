<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Thêm cột color vào categories và gắn danh mục cho các ticket cũ khi có thể.
// Không tạo bảng mới.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('color', 7)->default('#3B82F6')->after('name');
        });

        foreach ([
            'Máy lạnh' => '#3B82F6',
            'Máy chiếu' => '#8B5CF6',
            'Máy tính' => '#10B981',
            'Điện & chiếu sáng' => '#F59E0B',
            'Mạng / Wi-Fi' => '#06B6D4',
        ] as $name => $color) {
            DB::table('categories')->where('name', $name)->update(['color' => $color]);
        }

        DB::table('tickets')->whereNull('category_id')->orderBy('id')->get(['id', 'title', 'equipment_label'])->each(function ($ticket) {
            $categoryName = match (true) {
                str_contains($ticket->title, 'Máy chiếu') => 'Máy chiếu',
                str_contains($ticket->title, 'Máy lạnh') => 'Máy lạnh',
                str_contains($ticket->title, 'Đèn') => 'Điện & chiếu sáng',
                str_contains($ticket->title, 'Wi-Fi') => 'Mạng / Wi-Fi',
                default => $ticket->equipment_label,
            };
            $categoryId = DB::table('categories')->where('name', $categoryName)->value('id');
            if ($categoryId) DB::table('tickets')->where('id', $ticket->id)->update(['category_id' => $categoryId]);
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
