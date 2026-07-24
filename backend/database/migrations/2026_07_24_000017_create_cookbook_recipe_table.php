<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cookbook_recipe', function (Blueprint $table): void {
            $table->foreignId('cookbook_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['cookbook_id', 'recipe_id']);
            $table->index(['recipe_id', 'cookbook_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cookbook_recipe');
    }
};
