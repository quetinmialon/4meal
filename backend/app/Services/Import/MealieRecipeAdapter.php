<?php

namespace App\Services\Import;

use App\Exceptions\ImportException;

/** Converts the Mealie API recipe representation into application import data. */
final class MealieRecipeAdapter
{
    /** @return list<array<string, mixed>> */
    public function adapt(array $document): array
    {
        $recipes = array_is_list($document) ? $document : [$document];
        $result = [];

        foreach ($recipes as $index => $recipe) {
            if (! is_array($recipe)) {
                throw $this->invalid("recipes.{$index}", 'Une recette Mealie doit être un objet JSON.');
            }
            $result[] = $this->recipe($recipe, $index);
        }

        if ($result === []) {
            throw $this->invalid('recipes', 'Le document Mealie doit contenir au moins une recette.');
        }

        return $result;
    }

    /** @param array<string, mixed> $recipe @return array<string, mixed> */
    private function recipe(array $recipe, int $index): array
    {
        $title = $this->string($recipe['name'] ?? null);
        if ($title === null) {
            throw $this->invalid("recipes.{$index}.name", 'Le nom de la recette est obligatoire.');
        }

        $ingredients = $recipe['recipeIngredient'] ?? null;
        $instructions = $recipe['recipeInstructions'] ?? null;
        if (! is_array($ingredients) || $ingredients === []) {
            throw $this->invalid("recipes.{$index}.recipeIngredient", 'La recette doit contenir des ingrédients.');
        }
        if (! is_array($instructions) || $instructions === []) {
            throw $this->invalid("recipes.{$index}.recipeInstructions", 'La recette doit contenir des instructions.');
        }

        return [
            'title' => $title,
            'slug' => $this->string($recipe['slug'] ?? null),
            'description' => $this->string($recipe['description'] ?? null),
            'servings' => $this->servings($recipe['recipeYield'] ?? null),
            'prep_time_minutes' => $this->duration($recipe['prepTime'] ?? null),
            'cook_time_minutes' => $this->duration($recipe['cookTime'] ?? null),
            'rest_time_minutes' => null,
            'notes' => $this->string($recipe['notes'] ?? null),
            'source' => $this->string($recipe['recipeSource'] ?? $recipe['source'] ?? null),
            'ingredients' => array_map(fn (mixed $ingredient, int $position): array => $this->ingredient($ingredient, $position, $index), $ingredients, array_keys($ingredients)),
            'steps' => array_map(fn (mixed $step, int $position): array => $this->step($step, $position, $index), $instructions, array_keys($instructions)),
            'tags' => $this->names(array_merge($recipe['tags'] ?? [], $recipe['recipeCategory'] ?? [])),
        ];
    }

    /** @return array<string, mixed> */
    private function ingredient(mixed $value, int $position, int $recipeIndex): array
    {
        if (! is_array($value)) {
            $name = $this->string($value);
            if ($name === null) {
                throw $this->invalid("recipes.{$recipeIndex}.recipeIngredient.{$position}", 'Ingrédient invalide.');
            }

            return ['name' => $name, 'quantity' => null, 'unit' => null, 'preparation' => null, 'optional' => false, 'group' => null];
        }

        $food = $this->nestedName($value['food'] ?? null);
        $fallback = $this->string($value['originalText'] ?? $value['display'] ?? $value['note'] ?? null);
        $name = $food ?? $fallback;
        if ($name === null) {
            throw $this->invalid("recipes.{$recipeIndex}.recipeIngredient.{$position}", 'Ingrédient sans nom.');
        }

        return [
            'name' => $name,
            'quantity' => is_numeric($value['quantity'] ?? null) ? (float) $value['quantity'] : null,
            'unit' => $this->nestedName($value['unit'] ?? null),
            'preparation' => $this->string($value['note'] ?? null),
            'optional' => false,
            'group' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function step(mixed $value, int $position, int $recipeIndex): array
    {
        $text = is_array($value) ? $this->string($value['text'] ?? null) : $this->string($value);
        if ($text === null) {
            throw $this->invalid("recipes.{$recipeIndex}.recipeInstructions.{$position}", 'Instruction sans texte.');
        }
        $title = is_array($value) ? $this->string($value['title'] ?? null) : null;

        return ['position' => $position + 1, 'instruction' => $title === null ? $text : $title.': '.$text, 'duration_minutes' => null];
    }

    /** @param list<mixed> $values @return list<string> */
    private function names(array $values): array
    {
        return array_values(array_unique(array_filter(array_map(fn (mixed $value): ?string => $this->nestedName($value), $values))));
    }

    private function nestedName(mixed $value): ?string
    {
        return is_array($value) ? $this->string($value['name'] ?? null) : $this->string($value);
    }

    private function string(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function servings(mixed $value): ?int
    {
        if (preg_match('/\d+/', (string) $value, $match) !== 1) {
            return null;
        }

        return (int) $match[0] ?: null;
    }

    private function duration(mixed $value): ?int
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        if (preg_match('/^P(?:(\d+)D)?T?(?:(\d+)H)?(?:(\d+)M)?(?:(\d+(?:\.\d+)?)S)?$/', strtoupper(trim($value)), $match) !== 1) {
            return null;
        }

        return (int) round(((int) ($match[1] ?? 0) * 1440) + ((int) ($match[2] ?? 0) * 60) + (int) ($match[3] ?? 0) + ((float) ($match[4] ?? 0) / 60));
    }

    private function invalid(string $path, string $message): ImportException
    {
        return new ImportException('Le document Mealie est invalide.', [['path' => $path, 'code' => 'schema_invalid', 'message' => $message]], 'schema_invalid');
    }
}
