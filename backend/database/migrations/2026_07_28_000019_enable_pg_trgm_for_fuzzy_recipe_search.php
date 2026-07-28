<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX recipes_title_trgm_idx ON recipes USING GIN (title gin_trgm_ops)');
        DB::statement('CREATE INDEX recipe_ingredients_name_trgm_idx ON recipe_ingredients USING GIN (name gin_trgm_ops)');
        DB::statement('CREATE INDEX recipe_steps_instruction_trgm_idx ON recipe_steps USING GIN (instruction gin_trgm_ops)');
        DB::statement('CREATE INDEX tags_name_trgm_idx ON tags USING GIN (name gin_trgm_ops)');
        DB::statement('CREATE INDEX tags_slug_trgm_idx ON tags USING GIN (slug gin_trgm_ops)');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS recipes_title_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS recipe_ingredients_name_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS recipe_steps_instruction_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS tags_name_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS tags_slug_trgm_idx');
    }
};
