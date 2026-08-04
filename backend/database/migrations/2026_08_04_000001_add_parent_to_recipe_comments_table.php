<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipe_comments', function (Blueprint $table): void {
            $table->foreignId('parent_id')->nullable()->after('user_id')->constrained('recipe_comments')->restrictOnDelete();
            $table->index(['recipe_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::table('recipe_comments', function (Blueprint $table): void {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['recipe_id', 'parent_id']);
            $table->dropColumn('parent_id');
        });
    }
};
