<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('diet')->nullable()->after('email');
            $table->json('allergies')->nullable()->after('diet');
            $table->unsignedSmallInteger('default_servings')->default(2)->after('allergies');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['diet', 'allergies', 'default_servings']);
        });
    }
};
