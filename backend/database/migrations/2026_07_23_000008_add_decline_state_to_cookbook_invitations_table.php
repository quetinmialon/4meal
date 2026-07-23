<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cookbook_invitations', function (Blueprint $table): void {
            $table->timestamp('declined_at')->nullable()->after('accepted_at');
            $table->foreignId('declined_by')->nullable()->after('accepted_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cookbook_invitations', function (Blueprint $table): void {
            $table->dropForeign(['declined_by']);
            $table->dropColumn(['declined_at', 'declined_by']);
        });
    }
};
