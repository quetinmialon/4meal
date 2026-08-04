<?php

namespace App\Services\Import;

use App\Exceptions\ImportException;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class RecipeCsvImportService
{
    private const HEADERS = [
        'format_version', 'record_type', 'recipe_key', 'title', 'description', 'servings', 'prep_time_minutes', 'cook_time_minutes', 'rest_time_minutes', 'notes', 'source',
        'ingredient_position', 'ingredient_name', 'ingredient_quantity', 'ingredient_unit', 'ingredient_preparation', 'ingredient_optional', 'ingredient_group',
        'step_position', 'step_instruction', 'step_duration_minutes', 'tag',
    ];

    public function import(User $user, UploadedFile $file): array
    {
        $records = $this->parse($file);
        try {
            return DB::transaction(fn (): array => $this->persist($user, $records));
        } catch (ImportException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            throw new ImportException('L’import CSV n’a pas été appliqué.', [['path' => '', 'code' => 'transaction_failed', 'message' => 'La transaction a été annulée.']], 'import_failed');
        }
    }

    /** @return list<array<string, string>> */
    private function parse(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');
        if ($handle === false) {
            throw new ImportException('Le fichier CSV est illisible.', [['path' => '', 'code' => 'unreadable_file', 'message' => 'Le document ne peut pas être lu.']], 'import_invalid');
        }
        $headers = fgetcsv($handle);
        if (is_array($headers) && isset($headers[0])) {
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        }
        if ($headers !== self::HEADERS) {
            throw new ImportException('Les en-têtes CSV sont invalides.', [['path' => 'header', 'code' => 'invalid_header', 'message' => 'Le CSV doit utiliser les en-têtes documentés dans cet ordre.']], 'schema_invalid');
        }

        $records = [];
        $errors = [];
        $line = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $line++;
            if ($row === [null] || (count($row) === 1 && trim((string) $row[0]) === '')) {
                continue;
            }
            if (count($row) !== count(self::HEADERS)) {
                $errors[] = ['path' => "lines.{$line}", 'code' => 'invalid_column_count', 'message' => 'Nombre de colonnes invalide.'];

                continue;
            }
            $record = array_combine(self::HEADERS, array_map(fn ($value): string => trim((string) $value), $row));
            $this->validateRecord($record, $line, $errors);
            $records[] = $record;
        }
        fclose($handle);
        $this->validateReferences($records, $errors);
        if (! collect($records)->contains('record_type', 'recipe')) {
            $errors[] = ['path' => 'lines', 'code' => 'missing_recipe', 'message' => 'Le CSV doit contenir au moins une ligne recipe.'];
        }
        if ($errors !== []) {
            throw new ImportException('Le CSV contient des erreurs de validation.', $errors, 'schema_invalid');
        }

        return $records;
    }

    /** @param array<string,string> $record @param list<array<string,mixed>> $errors */
    private function validateRecord(array $record, int $line, array &$errors): void
    {
        $path = "lines.{$line}";
        if ($record['format_version'] !== '1') {
            $errors[] = ['path' => "$path.format_version", 'code' => 'unsupported_version', 'message' => 'Version CSV non supportée.'];
        }
        if (! in_array($record['record_type'], ['recipe', 'ingredient', 'step', 'tag'], true)) {
            $errors[] = ['path' => "$path.record_type", 'code' => 'invalid_record_type', 'message' => 'Type de ligne inconnu.'];
        }
        if ($record['recipe_key'] === '') {
            $errors[] = ['path' => "$path.recipe_key", 'code' => 'required', 'message' => 'recipe_key est obligatoire.'];
        }
        if ($record['record_type'] === 'recipe' && $record['title'] === '') {
            $errors[] = ['path' => "$path.title", 'code' => 'required', 'message' => 'title est obligatoire pour une recette.'];
        }
        foreach (['servings', 'prep_time_minutes', 'cook_time_minutes', 'rest_time_minutes', 'ingredient_position', 'step_position', 'step_duration_minutes'] as $field) {
            if ($record[$field] !== '' && filter_var($record[$field], FILTER_VALIDATE_INT) === false) {
                $errors[] = ['path' => "$path.$field", 'code' => 'invalid_integer', 'message' => 'La valeur doit être un entier.'];
            }
        }
        foreach (['servings', 'prep_time_minutes', 'cook_time_minutes', 'rest_time_minutes', 'ingredient_position', 'step_position', 'step_duration_minutes'] as $field) {
            if ($record[$field] !== '' && filter_var($record[$field], FILTER_VALIDATE_INT) !== false && (int) $record[$field] < 0) {
                $errors[] = ['path' => "$path.$field", 'code' => 'negative_integer', 'message' => 'La valeur ne peut pas être négative.'];
            }
        }
        foreach (['ingredient_position', 'step_position'] as $field) {
            if ($record[$field] !== '' && filter_var($record[$field], FILTER_VALIDATE_INT) !== false && (int) $record[$field] < 1) {
                $errors[] = ['path' => "$path.$field", 'code' => 'invalid_position', 'message' => 'La position doit commencer à 1.'];
            }
        }
        if ($record['ingredient_quantity'] !== '' && filter_var($record['ingredient_quantity'], FILTER_VALIDATE_FLOAT) === false) {
            $errors[] = ['path' => "$path.ingredient_quantity", 'code' => 'invalid_decimal', 'message' => 'La quantité doit être un nombre décimal.'];
        }
        if ($record['ingredient_quantity'] !== '' && filter_var($record['ingredient_quantity'], FILTER_VALIDATE_FLOAT) !== false && (float) $record['ingredient_quantity'] < 0) {
            $errors[] = ['path' => "$path.ingredient_quantity", 'code' => 'negative_decimal', 'message' => 'La quantité ne peut pas être négative.'];
        }
        if ($record['record_type'] === 'ingredient' && ($record['ingredient_name'] === '' || $record['ingredient_position'] === '')) {
            $errors[] = ['path' => $path, 'code' => 'invalid_ingredient', 'message' => 'Un ingrédient exige un nom et une position.'];
        }
        if ($record['record_type'] === 'step' && ($record['step_instruction'] === '' || $record['step_position'] === '')) {
            $errors[] = ['path' => $path, 'code' => 'invalid_step', 'message' => 'Une étape exige une instruction et une position.'];
        }
        if ($record['record_type'] === 'tag' && $record['tag'] === '') {
            $errors[] = ['path' => "$path.tag", 'code' => 'required', 'message' => 'tag est obligatoire.'];
        }
        if ($record['record_type'] === 'ingredient' && $record['ingredient_optional'] !== '' && ! in_array($record['ingredient_optional'], ['true', 'false'], true)) {
            $errors[] = ['path' => "$path.ingredient_optional", 'code' => 'invalid_boolean', 'message' => 'La valeur doit être true ou false.'];
        }
    }

    /** @param list<array<string,string>> $records @param list<array<string,mixed>> $errors */
    private function validateReferences(array $records, array &$errors): void
    {
        $recipes = [];
        $positions = [];
        foreach ($records as $index => $record) {
            if ($record['record_type'] === 'recipe') {
                if (isset($recipes[$record['recipe_key']])) {
                    $errors[] = ['path' => "lines.{$index}.recipe_key", 'code' => 'duplicate_recipe', 'message' => 'recipe_key dupliqué.'];
                }
                $recipes[$record['recipe_key']] = true;
            }
        }
        foreach ($records as $index => $record) {
            if (! isset($recipes[$record['recipe_key']])) {
                $errors[] = ['path' => "lines.{$index}.recipe_key", 'code' => 'unknown_recipe', 'message' => 'La recette référencée est absente.'];
            }
            if (in_array($record['record_type'], ['ingredient', 'step'], true)) {
                $key = $record['recipe_key'].'|'.$record['record_type'].'|'.($record['ingredient_position'] ?: $record['step_position']);
                if (isset($positions[$key])) {
                    $errors[] = ['path' => "lines.{$index}", 'code' => 'duplicate_position', 'message' => 'Position structurée dupliquée.'];
                }
                $positions[$key] = true;
            }
        }
    }

    /** @param list<array<string,string>> $records */
    private function persist(User $user, array $records): array
    {
        $groups = collect($records)->groupBy('recipe_key');
        $count = 0;
        $duplicates = [];
        foreach ($groups as $recipeKey => $rows) {
            $recipeRow = $rows->firstWhere('record_type', 'recipe');
            if ($recipeRow === null) {
                continue;
            }
            if (Recipe::query()->where('user_id', $user->id)->where('title', $recipeRow['title'])->where('source', $recipeRow['source'] ?: null)->exists()) {
                $duplicates[] = ['recipe_key' => $recipeKey, 'reason' => 'Même titre et source déjà présents.'];

                continue;
            }
            $recipe = Recipe::create(['user_id' => $user->id, 'author_id' => $user->id, 'title' => $recipeRow['title'], 'description' => $recipeRow['description'] ?: null, 'servings' => $this->int($recipeRow['servings']), 'prep_time_minutes' => $this->int($recipeRow['prep_time_minutes']), 'cook_time_minutes' => $this->int($recipeRow['cook_time_minutes']), 'rest_time_minutes' => $this->int($recipeRow['rest_time_minutes']), 'notes' => $recipeRow['notes'] ?: null, 'source' => $recipeRow['source'] ?: null, 'visibility' => 'private']);
            $tags = [];
            foreach ($rows as $row) {
                if ($row['record_type'] === 'ingredient') {
                    $recipe->ingredients()->create(['position' => (int) $row['ingredient_position'], 'name' => $row['ingredient_name'], 'quantity' => $row['ingredient_quantity'] ?: null, 'unit' => $row['ingredient_unit'] ?: null, 'preparation' => $row['ingredient_preparation'] ?: null, 'is_optional' => $row['ingredient_optional'] === 'true', 'group_name' => $row['ingredient_group'] ?: null]);
                } elseif ($row['record_type'] === 'step') {
                    $recipe->steps()->create(['position' => (int) $row['step_position'], 'instruction' => $row['step_instruction'], 'duration_minutes' => $this->int($row['step_duration_minutes'])]);
                } elseif ($row['record_type'] === 'tag') {
                    $tag = $user->tags()->firstOrCreate(['slug' => Str::slug($row['tag'])], ['name' => $row['tag']]);
                    $tags[] = (int) $tag->getKey();
                }
            }
            $recipe->tags()->sync(array_unique($tags));
            $count++;
        }

        return ['recipes' => $count, 'duplicates' => $duplicates];
    }

    private function int(string $value): ?int
    {
        return $value === '' ? null : (int) $value;
    }
}
