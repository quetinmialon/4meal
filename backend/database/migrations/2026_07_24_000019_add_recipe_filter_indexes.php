<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table): void {
            $table->index('prep_time_minutes', 'recipes_prep_time_idx');
            $table->index('cook_time_minutes', 'recipes_cook_time_idx');
        });

        Schema::table('recipe_ingredients', function (Blueprint $table): void {
            $table->index('name', 'recipe_ingredients_name_idx');
        });

        Schema::table('tags', function (Blueprint $table): void {
            $table->index('name', 'tags_name_idx');
            $table->index('slug', 'tags_slug_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table): void {
            $table->dropIndex('tags_name_idx');
            $table->dropIndex('tags_slug_idx');
        });

        Schema::table('recipe_ingredients', function (Blueprint $table): void {
            $table->dropIndex('recipe_ingredients_name_idx');
        });

        Schema::table('recipes', function (Blueprint $table): void {
            $table->dropIndex('recipes_prep_time_idx');
            $table->dropIndex('recipes_cook_time_idx');
        });
    }
};
