<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_comment_reactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recipe_comment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('emoji', 16);
            $table->timestamps();
            $table->unique(['recipe_comment_id', 'user_id', 'emoji']);
            $table->index(['recipe_comment_id', 'emoji']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_comment_reactions');
    }
};
