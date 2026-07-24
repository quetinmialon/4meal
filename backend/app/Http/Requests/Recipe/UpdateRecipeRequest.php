<?php

namespace App\Http\Requests\Recipe;

use Illuminate\Contracts\Validation\ValidationRule;

class UpdateRecipeRequest extends StoreRecipeRequest
{
    /** @return array<string, list<ValidationRule|string>> */
    public function rules(): array
    {
        $rules = parent::rules();

        foreach (['title', 'description', 'prep_time_minutes', 'cook_time_minutes', 'servings', 'source'] as $field) {
            $rules[$field] = array_values(array_diff($rules[$field], ['required']));
            array_unshift($rules[$field], 'sometimes');
        }

        $rules['ingredients'] = ['sometimes', 'array'];
        $rules['steps'] = ['sometimes', 'array'];

        return $rules;
    }
}
