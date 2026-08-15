<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cookbook_message_reactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cookbook_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('emoji', 16);
            $table->timestamps();

            $table->unique(['cookbook_message_id', 'user_id', 'emoji']);
            $table->index(['cookbook_message_id', 'emoji']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cookbook_message_reactions');
    }
};
