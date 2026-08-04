<?php

namespace App\Services\Export;

use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\RecipeStep;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class RecipeCsvExportStreamer
{
    public const HEADERS = [
        'format_version', 'record_type', 'recipe_key', 'title', 'description', 'servings',
        'prep_time_minutes', 'cook_time_minutes', 'rest_time_minutes', 'notes', 'source',
        'ingredient_position', 'ingredient_name', 'ingredient_quantity', 'ingredient_unit',
        'ingredient_preparation', 'ingredient_optional', 'ingredient_group',
        'step_position', 'step_instruction', 'step_duration_minutes', 'tag',
    ];

    public function stream(User $user): void
    {
        $this->write(self::HEADERS);
        $this->accessibleRecipes($user)->with(['ingredients', 'steps', 'tags'])->chunkById(100, function (Collection $recipes): void {
            /** @var Collection<int, Recipe> $recipes */
            foreach ($recipes as $recipe) {
                $this->write($this->row($recipe, 'recipe'));
                foreach ($recipe->ingredients as $ingredient) {
                    /** @var RecipeIngredient $ingredient */
                    $this->write($this->row($recipe, 'ingredient', $ingredient));
                }
                foreach ($recipe->steps as $step) {
                    /** @var RecipeStep $step */
                    $this->write($this->row($recipe, 'step', null, $step));
                }
                foreach ($recipe->tags as $tag) {
                    /** @var Tag $tag */
                    $this->write($this->row($recipe, 'tag', null, null, $tag->name));
                }
            }
        });
    }

    private function accessibleRecipes(User $user): Builder
    {
        $cookbooks = $user->cookbooks()->select('cookbooks.id');

        return Recipe::query()->select('recipes.*')->where(function (Builder $query) use ($user, $cookbooks): void {
            $query->where('recipes.user_id', $user->getKey())
                ->orWhereIn('recipes.cookbook_id', $cookbooks)
                ->orWhereHas('cookbooks', fn (Builder $linked): Builder => $linked->whereIn('cookbooks.id', $cookbooks));
        })->orderBy('recipes.id');
    }

    /** @return list<string|null> */
    private function row(Recipe $recipe, string $type, ?RecipeIngredient $ingredient = null, ?RecipeStep $step = null, ?string $tag = null): array
    {
        $values = array_fill(0, count(self::HEADERS), null);
        $values[0] = '1';
        $values[1] = $type;
        $values[2] = (string) $recipe->public_id;

        if ($type === 'recipe') {
            $values[3] = $recipe->title;
            $values[4] = $recipe->description;
            $values[5] = $this->value($recipe->servings);
            $values[6] = $this->value($recipe->prep_time_minutes);
            $values[7] = $this->value($recipe->cook_time_minutes);
            $values[8] = $this->value($recipe->rest_time_minutes);
            $values[9] = $recipe->notes;
            $values[10] = $recipe->source;
        } elseif ($type === 'ingredient' && $ingredient !== null) {
            $values[11] = $this->value($ingredient->position);
            $values[12] = $ingredient->name;
            $values[13] = $this->value($ingredient->quantity);
            $values[14] = $ingredient->unit;
            $values[15] = $ingredient->preparation;
            $values[16] = $ingredient->is_optional ? 'true' : 'false';
            $values[17] = $ingredient->group_name;
        } elseif ($type === 'step' && $step !== null) {
            $values[18] = $this->value($step->position);
            $values[19] = $step->instruction;
            $values[20] = $this->value($step->duration_minutes);
        } elseif ($type === 'tag') {
            $values[21] = $tag;
        }

        return $values;
    }

    private function value(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    /** @param list<string|null> $row */
    private function write(array $row): void
    {
        $handle = fopen('php://memory', 'w+');
        if ($handle === false) {
            throw new \RuntimeException('CSV line buffer could not be created.');
        }
        fputcsv($handle, $row, ',', '"', '\\');
        rewind($handle);
        echo (string) stream_get_contents($handle);
        fclose($handle);
    }
}
