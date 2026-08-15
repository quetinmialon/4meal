<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planned_meals', function (Blueprint $table): void {
            $table->uuid('recurrence_id')->nullable()->after('public_id');
            $table->string('recurrence_frequency', 16)->nullable()->after('recurrence_id');
            $table->date('recurrence_until')->nullable()->after('recurrence_frequency');
            $table->index(['recurrence_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::table('planned_meals', function (Blueprint $table): void {
            $table->dropIndex(['recurrence_id', 'date']);
            $table->dropColumn(['recurrence_id', 'recurrence_frequency', 'recurrence_until']);
        });
    }
};
