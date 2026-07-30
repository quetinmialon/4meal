<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cookbook_messages', function (Blueprint $table): void {
            $table->timestamp('edited_at')->nullable()->after('content');
            $table->timestamp('deleted_at')->nullable()->after('edited_at');
            $table->foreignId('deleted_by_user_id')->nullable()->after('deleted_at')->constrained('users')->nullOnDelete();
            $table->index(['cookbook_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('cookbook_messages', function (Blueprint $table): void {
            $table->dropForeign(['deleted_by_user_id']);
            $table->dropIndex(['cookbook_id', 'deleted_at']);
            $table->dropColumn(['edited_at', 'deleted_at', 'deleted_by_user_id']);
        });
    }
};
