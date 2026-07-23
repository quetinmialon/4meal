<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->index(['user_id', 'created_at']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE recipes ADD CONSTRAINT recipes_single_owner_check CHECK '.
                '((user_id IS NOT NULL AND cookbook_id IS NULL) OR (user_id IS NULL AND cookbook_id IS NOT NULL))'
            );
        }
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table): void {
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement('ALTER TABLE recipes DROP CONSTRAINT recipes_single_owner_check');
            }
            $table->dropForeign(['user_id']);
            $table->dropIndex('recipes_user_id_created_at_index');
            $table->dropColumn('user_id');
        });
    }
};
