<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planned_meals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('cookbook_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('meal_type', 32);
            $table->text('note')->nullable();
            $table->unsignedInteger('initial_servings');
            $table->timestamps();

            $table->index(['user_id', 'date']);
            $table->index(['cookbook_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planned_meals');
    }
};
