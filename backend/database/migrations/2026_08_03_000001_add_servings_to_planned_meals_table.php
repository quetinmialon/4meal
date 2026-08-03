<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planned_meals', function (Blueprint $table): void {
            $table->unsignedInteger('servings')->default(1)->after('initial_servings');
        });
    }

    public function down(): void
    {
        Schema::table('planned_meals', function (Blueprint $table): void {
            $table->dropColumn('servings');
        });
    }
};
