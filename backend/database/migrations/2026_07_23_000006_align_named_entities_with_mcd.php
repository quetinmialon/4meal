<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->renameColumn('password', 'password_hash');
            $table->renameColumn('remember_token', 'remember_token_hash');
            $table->string('avatar_path')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->softDeletes();
        });

        Schema::table('cookbooks', function (Blueprint $table): void {
            $table->string('slug')->nullable()->unique()->after('name');
            $table->text('description')->nullable()->after('slug');
            $table->string('image_path')->nullable()->after('description');
            $table->softDeletes();
        });

        Schema::table('cookbook_members', function (Blueprint $table): void {
            $table->timestamp('joined_at')->nullable()->after('role');
        });

        Schema::table('recipes', function (Blueprint $table): void {
            $table->renameColumn('name', 'title');
            $table->foreignId('cookbook_id')->nullable()->change();
            $table->string('slug')->nullable()->unique()->after('title');
            $table->unsignedInteger('prep_time_minutes')->nullable();
            $table->unsignedInteger('cook_time_minutes')->nullable();
            $table->unsignedInteger('rest_time_minutes')->nullable();
            $table->unsignedInteger('servings')->nullable();
            $table->string('image_path')->nullable();
            $table->string('visibility')->nullable();
            $table->string('difficulty')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table): void {
            $table->dropSoftDeletes();
            $table->dropUnique(['slug']);
            $table->dropColumn([
                'slug', 'prep_time_minutes', 'cook_time_minutes', 'rest_time_minutes',
                'servings', 'image_path', 'visibility', 'difficulty', 'notes',
            ]);
            $table->foreignId('cookbook_id')->nullable(false)->change();
            $table->renameColumn('title', 'name');
        });

        Schema::table('cookbook_members', function (Blueprint $table): void {
            $table->dropColumn('joined_at');
        });

        Schema::table('cookbooks', function (Blueprint $table): void {
            $table->dropSoftDeletes();
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'description', 'image_path']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropSoftDeletes();
            $table->dropColumn(['avatar_path', 'last_login_at']);
            $table->renameColumn('password_hash', 'password');
            $table->renameColumn('remember_token_hash', 'remember_token');
        });
    }
};
