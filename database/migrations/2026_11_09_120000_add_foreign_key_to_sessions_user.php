<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONSTRAINT = 'sessions_user_id_foreign';

    public function up(): void
    {
        if (! Schema::hasTable('sessions') || ! Schema::hasColumn('sessions', 'user_id')) {
            return;
        }

        $alreadyConstrained = collect(Schema::getForeignKeys('sessions'))
            ->contains(fn (array $foreignKey): bool => in_array('user_id', $foreignKey['columns'] ?? [], true));

        if ($alreadyConstrained) {
            return;
        }

        Schema::table('sessions', function (Blueprint $table): void {
            $table->foreign('user_id', self::CONSTRAINT)
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sessions')) {
            return;
        }

        $exists = collect(Schema::getForeignKeys('sessions'))
            ->contains(fn (array $foreignKey): bool => ($foreignKey['name'] ?? null) === self::CONSTRAINT);

        if ($exists) {
            Schema::table('sessions', function (Blueprint $table): void {
                $table->dropForeign(self::CONSTRAINT);
            });
        }
    }
};
