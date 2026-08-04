<?php

namespace App\Http\Controllers\Import;

use App\Exceptions\ImportException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Import\ImportRequest;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Import\MealieRecipeAdapter;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class ImportMealieController extends Controller
{
    public function __invoke(ImportRequest $request, MealieRecipeAdapter $adapter): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        try {
            $content = file_get_contents($request->file('file')->getRealPath());
            $document = json_decode((string) $content, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($document)) {
                throw new ImportException('Le document Mealie doit être un objet.', [['path' => '', 'code' => 'root_not_object', 'message' => 'La racine JSON doit être un objet.']], 'import_invalid');
            }
            $recipes = $adapter->adapt($document);
            $report = DB::transaction(function () use ($recipes, $user): array {
                $count = 0;
                $duplicates = [];
                foreach ($recipes as $index => $input) {
                    if (Recipe::query()->where('user_id', $user->id)->where('title', $input['title'])->whereNull('source')->exists()) {
                        $duplicates[] = ['path' => "recipes.{$index}", 'type' => 'recipe', 'reason' => 'Même titre déjà présent.'];

                        continue;
                    }
                    $recipe = Recipe::create(['user_id' => $user->id, 'author_id' => $user->id, ...$input, 'visibility' => 'private']);
                    foreach ($input['ingredients'] as $position => $ingredient) {
                        $recipe->ingredients()->create(['position' => $position + 1, ...$ingredient]);
                    }
                    foreach ($input['steps'] as $step) {
                        $recipe->steps()->create($step);
                    }
                    $recipe->tags()->sync(collect($input['tags'])->map(fn (string $tag): int => (int) $user->tags()->firstOrCreate(['slug' => Str::slug($tag)], ['name' => $tag])->getKey())->all());
                    $count++;
                }

                return ['recipes' => $count, 'duplicates' => $duplicates];
            });
        } catch (ImportException $exception) {
            return ApiResponse::error($request, $exception->errorCode, $exception->getMessage(), 422, ['errors' => $exception->errors]);
        } catch (Throwable $exception) {
            report($exception);

            return ApiResponse::error($request, 'import_failed', 'L’import Mealie n’a pas été appliqué.', 422, ['errors' => [['path' => '', 'code' => 'transaction_failed', 'message' => 'La transaction a été annulée.']]]);
        }

        return ApiResponse::success($request, ['message' => 'Import Mealie terminé.', 'report' => $report], 201);
    }
}
