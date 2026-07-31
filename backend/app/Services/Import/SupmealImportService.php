<?php

namespace App\Services\Import;

use App\Exceptions\ImportException;
use App\Models\Cookbook;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Opis\JsonSchema\Validator;
use Throwable;

final class SupmealImportService
{
    /** @return array{cookbooks: int, recipes: int, duplicates: list<array{path: string, type: string, reason: string}>} */
    public function import(User $user, UploadedFile $file): array
    {
        $document = $this->decode($file);
        $this->validateSchema($document);
        $this->validateBusinessRules($document);

        try {
            return DB::transaction(fn (): array => $this->persist($user, $document));
        } catch (ImportException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw new ImportException(
                'L’import n’a pas été appliqué. Aucune donnée n’a été créée.',
                [['path' => '', 'code' => 'transaction_failed', 'message' => 'La transaction a été annulée.']],
                'import_failed',
            );
        }
    }

    /** @return array<string, mixed> */
    private function decode(UploadedFile $file): array
    {
        $content = file_get_contents($file->getRealPath());

        if (! is_string($content)) {
            throw new ImportException('Le fichier JSON est illisible.', [
                ['path' => '', 'code' => 'unreadable_file', 'message' => 'Le fichier ne peut pas être lu.'],
            ]);
        }

        try {
            $document = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new ImportException('Le fichier ne contient pas un JSON valide.', [
                ['path' => '', 'code' => 'invalid_json', 'message' => 'Le document JSON est syntaxiquement invalide.'],
            ], 'import_invalid');
        }

        if (! is_array($document)) {
            throw new ImportException('Le document JSON doit être un objet.', [
                ['path' => '', 'code' => 'root_not_object', 'message' => 'La racine JSON doit être un objet.'],
            ]);
        }

        return $document;
    }

    /** @param array<string, mixed> $document */
    private function validateSchema(array $document): void
    {
        $schemaPath = resource_path('schemas'.DIRECTORY_SEPARATOR.'supmeal-1.0.schema.json');
        $schema = json_decode((string) file_get_contents($schemaPath));
        $schemaDocument = json_decode(json_encode($document, JSON_THROW_ON_ERROR));
        $result = (new Validator)->validate($schemaDocument, $schema);

        if ($result->isValid()) {
            return;
        }

        throw new ImportException('Le document ne respecte pas le schéma SUPMEAL 1.0.0.', [
            ['path' => '', 'code' => 'schema_invalid', 'message' => 'La structure du document est invalide.'],
        ], 'schema_invalid');
    }

    /** @param array<string, mixed> $document */
    private function validateBusinessRules(array $document): void
    {
        $errors = [];
        $cookbooks = $document['cookbooks'];
        $recipes = $document['recipes'];
        $cookbookIds = [];
        $recipeIds = [];

        foreach ($cookbooks as $index => $cookbook) {
            $id = $cookbook['id'];
            if (isset($cookbookIds[$id])) {
                $errors[] = ['path' => "cookbooks.{$index}.id", 'code' => 'duplicate_id', 'message' => 'Identifiant de cookbook dupliqué.'];
            }
            $cookbookIds[$id] = true;
        }

        foreach ($recipes as $index => $recipe) {
            $id = $recipe['id'];
            if (isset($recipeIds[$id])) {
                $errors[] = ['path' => "recipes.{$index}.id", 'code' => 'duplicate_id', 'message' => 'Identifiant de recette dupliqué.'];
            }
            $recipeIds[$id] = true;
            foreach ($recipe['cookbook_ids'] as $referenceIndex => $reference) {
                if (! isset($cookbookIds[$reference])) {
                    $errors[] = ['path' => "recipes.{$index}.cookbook_ids.{$referenceIndex}", 'code' => 'unknown_reference', 'message' => 'Référence de cookbook inconnue.'];
                }
            }
        }

        foreach ($cookbooks as $index => $cookbook) {
            foreach ($cookbook['recipe_ids'] as $referenceIndex => $reference) {
                if (! isset($recipeIds[$reference])) {
                    $errors[] = ['path' => "cookbooks.{$index}.recipe_ids.{$referenceIndex}", 'code' => 'unknown_reference', 'message' => 'Référence de recette inconnue.'];
                }
            }
        }

        $recipeReferences = collect($recipes)->mapWithKeys(fn (array $recipe): array => [$recipe['id'] => array_fill_keys($recipe['cookbook_ids'], true)])->all();
        foreach ($cookbooks as $index => $cookbook) {
            foreach ($cookbook['recipe_ids'] as $referenceIndex => $recipeId) {
                if (isset($recipeReferences[$recipeId]) && ! isset($recipeReferences[$recipeId][$cookbook['id']])) {
                    $errors[] = ['path' => "cookbooks.{$index}.recipe_ids.{$referenceIndex}", 'code' => 'asymmetric_reference', 'message' => 'Référence cookbook/recette incohérente.'];
                }
            }
        }

        if ($errors !== []) {
            throw new ImportException('Le document contient des incohérences métier.', $errors, 'business_invalid');
        }
    }

    /** @param array<string, mixed> $document */
    private function persist(User $user, array $document): array
    {
        $cookbookMap = [];
        $duplicates = [];
        $importedCookbooks = 0;

        foreach ($document['cookbooks'] as $index => $input) {
            $cookbook = Cookbook::query()
                ->where('owner_id', $user->getKey())
                ->where(function ($query) use ($input): void {
                    $query->where('slug', $input['slug'] ?? Str::slug($input['name']))
                        ->orWhere('name', $input['name']);
                })
                ->first();

            if ($cookbook === null) {
                $cookbook = Cookbook::create([
                    'owner_id' => $user->getKey(),
                    'name' => $input['name'],
                    'slug' => $input['slug'] ?? null,
                    'description' => $input['description'] ?? null,
                ]);
                $cookbook->members()->attach($user, ['role' => 'owner', 'joined_at' => now()]);
                $importedCookbooks++;
            } else {
                $duplicates[] = ['path' => "cookbooks.{$index}", 'type' => 'cookbook', 'reason' => 'Même nom ou slug déjà présent dans vos cookbooks.'];
            }

            $cookbookMap[$input['id']] = $cookbook;
        }

        $importedRecipes = 0;
        foreach ($document['recipes'] as $index => $input) {
            $cookbooks = collect($input['cookbook_ids'])->map(fn (string $id): Cookbook => $cookbookMap[$id]);
            $primaryCookbook = $cookbooks->first();
            $duplicateQuery = Recipe::query()->where('title', $input['title'])->where('source', $input['source'] ?? null);
            $primaryCookbook !== null
                ? $duplicateQuery->where('cookbook_id', $primaryCookbook->getKey())
                : $duplicateQuery->where('user_id', $user->getKey());

            if ($duplicateQuery->exists()) {
                $duplicates[] = ['path' => "recipes.{$index}", 'type' => 'recipe', 'reason' => 'Même titre et source dans le même périmètre.'];

                continue;
            }

            $recipe = Recipe::create([
                'user_id' => $primaryCookbook === null ? $user->getKey() : null,
                'author_id' => $user->getKey(),
                'cookbook_id' => $primaryCookbook?->getKey(),
                'title' => $input['title'],
                'slug' => $input['slug'] ?? null,
                'description' => $input['description'] ?? null,
                'servings' => $input['servings'],
                'prep_time_minutes' => $input['prep_time_minutes'],
                'cook_time_minutes' => $input['cook_time_minutes'],
                'rest_time_minutes' => $input['rest_time_minutes'],
                'notes' => $input['notes'] ?? null,
                'source' => $input['source'] ?? null,
                'visibility' => 'private',
            ]);

            foreach ($input['ingredients'] as $position => $ingredient) {
                $recipe->ingredients()->create([
                    'position' => $position + 1,
                    'name' => $ingredient['name'],
                    'quantity' => $ingredient['quantity'],
                    'unit' => $ingredient['unit'],
                    'preparation' => $ingredient['preparation'],
                    'is_optional' => $ingredient['optional'],
                    'group_name' => $ingredient['group'],
                ]);
            }
            foreach ($input['steps'] as $step) {
                $recipe->steps()->create([
                    'position' => $step['position'],
                    'instruction' => $step['instruction'],
                    'duration_minutes' => $step['duration_minutes'],
                ]);
            }
            $tagIds = collect($input['tags'])->map(fn (string $tag): int => $user->tags()->firstOrCreate(['slug' => Str::slug($tag)], ['name' => $tag])->getKey())->all();
            $recipe->tags()->sync($tagIds);
            if ($cookbooks->count() > 1) {
                $recipe->cookbooks()->sync($cookbooks->slice(1)->map(fn (Cookbook $cookbook): int => $cookbook->getKey())->all());
            }
            $importedRecipes++;
        }

        return ['cookbooks' => $importedCookbooks, 'recipes' => $importedRecipes, 'duplicates' => $duplicates];
    }
}
