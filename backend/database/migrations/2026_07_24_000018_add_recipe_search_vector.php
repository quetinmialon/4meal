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

        DB::statement('ALTER TABLE recipes ADD COLUMN search_vector tsvector');

        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION refresh_recipe_search_vector() RETURNS trigger
LANGUAGE plpgsql AS $function$
DECLARE
    recipe_id bigint;
BEGIN
    IF TG_TABLE_NAME = 'tags' THEN
        UPDATE recipes AS r
        SET search_vector =
            setweight(to_tsvector('french', coalesce(r.title, '')), 'A') ||
            setweight(to_tsvector('french', coalesce((SELECT string_agg(concat_ws(' ', i.name, i.preparation, i.group_name), ' ')
                FROM recipe_ingredients i WHERE i.recipe_id = r.id), '')), 'B') ||
            setweight(to_tsvector('french', coalesce((SELECT string_agg(s.instruction, ' ')
                FROM recipe_steps s WHERE s.recipe_id = r.id), '')), 'C') ||
            setweight(to_tsvector('french', coalesce((SELECT string_agg(concat_ws(' ', t.name, t.slug), ' ')
                FROM tags t JOIN recipe_tag rt ON rt.tag_id = t.id WHERE rt.recipe_id = r.id), '')), 'B')
        WHERE EXISTS (SELECT 1 FROM recipe_tag rt WHERE rt.recipe_id = r.id AND rt.tag_id = COALESCE(NEW.id, OLD.id));
        RETURN NEW;
    ELSIF TG_TABLE_NAME = 'recipes' THEN
        recipe_id := CASE WHEN TG_OP = 'DELETE' THEN OLD.id ELSE NEW.id END;
    ELSE
        recipe_id := CASE WHEN TG_OP = 'DELETE' THEN OLD.recipe_id ELSE NEW.recipe_id END;
    END IF;

    UPDATE recipes AS r
    SET search_vector =
        setweight(to_tsvector('french', coalesce(r.title, '')), 'A') ||
        setweight(to_tsvector('french', coalesce((SELECT string_agg(concat_ws(' ', i.name, i.preparation, i.group_name), ' ')
            FROM recipe_ingredients i WHERE i.recipe_id = r.id), '')), 'B') ||
        setweight(to_tsvector('french', coalesce((SELECT string_agg(s.instruction, ' ')
            FROM recipe_steps s WHERE s.recipe_id = r.id), '')), 'C') ||
        setweight(to_tsvector('french', coalesce((SELECT string_agg(concat_ws(' ', t.name, t.slug), ' ')
            FROM tags t JOIN recipe_tag rt ON rt.tag_id = t.id WHERE rt.recipe_id = r.id), '')), 'B')
    WHERE r.id = recipe_id;

    IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
    RETURN NEW;
END;
$function$;
SQL);

        DB::statement(<<<'SQL'
CREATE TRIGGER recipes_search_vector_refresh
AFTER INSERT OR DELETE ON recipes
FOR EACH ROW EXECUTE FUNCTION refresh_recipe_search_vector();
SQL);
        DB::statement(<<<'SQL'
CREATE TRIGGER recipes_search_vector_update
AFTER UPDATE OF title ON recipes
FOR EACH ROW EXECUTE FUNCTION refresh_recipe_search_vector();
SQL);
        DB::statement(<<<'SQL'
CREATE TRIGGER recipe_ingredients_search_vector_refresh
AFTER INSERT OR UPDATE OR DELETE ON recipe_ingredients
FOR EACH ROW EXECUTE FUNCTION refresh_recipe_search_vector();
SQL);
        DB::statement(<<<'SQL'
CREATE TRIGGER recipe_steps_search_vector_refresh
AFTER INSERT OR UPDATE OR DELETE ON recipe_steps
FOR EACH ROW EXECUTE FUNCTION refresh_recipe_search_vector();
SQL);
        DB::statement(<<<'SQL'
CREATE TRIGGER recipe_tag_search_vector_refresh
AFTER INSERT OR UPDATE OR DELETE ON recipe_tag
FOR EACH ROW EXECUTE FUNCTION refresh_recipe_search_vector();
SQL);
        DB::statement(<<<'SQL'
CREATE TRIGGER tags_search_vector_refresh
AFTER UPDATE OF name, slug ON tags
FOR EACH ROW EXECUTE FUNCTION refresh_recipe_search_vector();
SQL);

        DB::statement(<<<'SQL'
UPDATE recipes AS r
SET search_vector =
    setweight(to_tsvector('french', coalesce(r.title, '')), 'A') ||
    setweight(to_tsvector('french', coalesce((SELECT string_agg(concat_ws(' ', i.name, i.preparation, i.group_name), ' ')
        FROM recipe_ingredients i WHERE i.recipe_id = r.id), '')), 'B') ||
    setweight(to_tsvector('french', coalesce((SELECT string_agg(s.instruction, ' ')
        FROM recipe_steps s WHERE s.recipe_id = r.id), '')), 'C') ||
    setweight(to_tsvector('french', coalesce((SELECT string_agg(concat_ws(' ', t.name, t.slug), ' ')
        FROM tags t JOIN recipe_tag rt ON rt.tag_id = t.id WHERE rt.recipe_id = r.id), '')), 'B');
SQL);
        DB::statement('CREATE INDEX recipes_search_vector_gin_idx ON recipes USING GIN (search_vector)');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS recipes_search_vector_refresh ON recipes');
        DB::statement('DROP TRIGGER IF EXISTS recipes_search_vector_update ON recipes');
        DB::statement('DROP TRIGGER IF EXISTS recipe_ingredients_search_vector_refresh ON recipe_ingredients');
        DB::statement('DROP TRIGGER IF EXISTS recipe_steps_search_vector_refresh ON recipe_steps');
        DB::statement('DROP TRIGGER IF EXISTS recipe_tag_search_vector_refresh ON recipe_tag');
        DB::statement('DROP TRIGGER IF EXISTS tags_search_vector_refresh ON tags');
        DB::statement('DROP FUNCTION IF EXISTS refresh_recipe_search_vector()');
        DB::statement('DROP INDEX IF EXISTS recipes_search_vector_gin_idx');
        DB::statement('ALTER TABLE recipes DROP COLUMN search_vector');
    }
};
