<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cookbooks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->index(['owner_id', 'created_at']);
        });

        Schema::create('cookbook_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cookbook_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 32);
            $table->timestamps();

            $table->unique(['cookbook_id', 'user_id']);
            $table->index(['user_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cookbook_members');
        Schema::dropIfExists('cookbooks');
    }
};
