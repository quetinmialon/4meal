<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_favorites', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['user_id', 'recipe_id']);
            $table->index(['recipe_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_favorites');
    }
};
