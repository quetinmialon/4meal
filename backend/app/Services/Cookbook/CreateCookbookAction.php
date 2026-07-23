<?php

namespace App\Services\Cookbook;

use App\Models\Cookbook;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CreateCookbookAction
{
    /**
     * @param  array{name: string, slug?: string|null, description?: string|null, image?: UploadedFile|null}  $attributes
     */
    public function execute(User $owner, array $attributes): Cookbook
    {
        $storedImagePath = null;

        try {
            return DB::transaction(function () use ($owner, $attributes, &$storedImagePath): Cookbook {
                $cookbook = new Cookbook([
                    'name' => $attributes['name'],
                    'slug' => $attributes['slug'] ?? null,
                    'description' => $attributes['description'] ?? null,
                ]);

                if (($attributes['image'] ?? null) instanceof UploadedFile) {
                    $storedImagePath = Storage::disk('public')->putFile('cookbooks', $attributes['image']);
                    $cookbook->image_path = $storedImagePath;
                }

                $cookbook->owner()->associate($owner);
                $cookbook->save();

                $cookbook->members()->attach($owner, [
                    'role' => 'owner',
                    'joined_at' => now(),
                ]);

                return $cookbook->load('owner');
            });
        } catch (Throwable $exception) {
            if (is_string($storedImagePath)) {
                Storage::disk('public')->delete($storedImagePath);
            }

            throw $exception;
        }
    }
}
