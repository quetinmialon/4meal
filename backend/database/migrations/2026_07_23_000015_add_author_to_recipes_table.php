<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table): void {
            $table->foreignId('author_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->index(['author_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table): void {
            $table->dropForeign(['author_id']);
            $table->dropIndex('recipes_author_id_created_at_index');
            $table->dropColumn('author_id');
        });
    }
};
