<?php

namespace App\Services\Cookbook;

use App\Models\Cookbook;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class UpdateCookbookAction
{
    /**
     * @param  array{name: string, slug?: string|null, description?: string|null, image?: UploadedFile|null}  $attributes
     */
    public function execute(Cookbook $cookbook, array $attributes): Cookbook
    {
        $oldImagePath = $cookbook->image_path;
        $newImagePath = null;

        try {
            $updatedCookbook = DB::transaction(function () use ($cookbook, $attributes, &$newImagePath): Cookbook {
                $cookbook->name = $attributes['name'];

                if (array_key_exists('slug', $attributes)) {
                    $cookbook->slug = $attributes['slug'];
                }

                if (array_key_exists('description', $attributes)) {
                    $cookbook->description = $attributes['description'];
                }

                if (($attributes['image'] ?? null) instanceof UploadedFile) {
                    $newImagePath = Storage::disk('public')->putFile('cookbooks', $attributes['image']);
                    $cookbook->image_path = $newImagePath;
                }

                $cookbook->save();

                return $cookbook->load('owner');
            });

            if (is_string($oldImagePath) && $oldImagePath !== $newImagePath) {
                Storage::disk('public')->delete($oldImagePath);
            }

            return $updatedCookbook;
        } catch (Throwable $exception) {
            if (is_string($newImagePath)) {
                Storage::disk('public')->delete($newImagePath);
            }

            throw $exception;
        }
    }
}
