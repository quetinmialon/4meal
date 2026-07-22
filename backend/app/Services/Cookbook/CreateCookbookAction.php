<?php

namespace App\Services\Cookbook;

use App\Models\Cookbook;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateCookbookAction
{
    /**
     * @param  array{name: string}  $attributes
     */
    public function execute(User $owner, array $attributes): Cookbook
    {
        return DB::transaction(function () use ($owner, $attributes): Cookbook {
            $cookbook = new Cookbook([
                'name' => $attributes['name'],
            ]);
            $cookbook->owner()->associate($owner);
            $cookbook->save();

            $cookbook->members()->attach($owner, [
                'role' => 'owner',
            ]);

            return $cookbook->load('owner');
        });
    }
}
