<?php

namespace App\Http\Requests\Recipe;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        foreach (['title', 'source'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->string($field)->toString())]);
            }
        }
    }

    /** @return array<string, list<ValidationRule|string>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:1', 'max:255'],
            'description' => ['nullable', 'string'],
            'prep_time_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'cook_time_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'servings' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'source' => ['nullable', 'string', 'max:2048'],
            'cookbook_id' => [
                'nullable',
                'uuid',
                Rule::exists('cookbooks', 'public_id')->whereNull('deleted_at'),
            ],
            'ingredients' => ['required', 'array', 'min:1'],
            'ingredients.*' => ['required', 'array'],
            'ingredients.*.position' => ['nullable', 'integer', 'min:1'],
            'ingredients.*.name' => ['required', 'string', 'min:1', 'max:255'],
            'ingredients.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'ingredients.*.unit' => ['nullable', 'string', 'max:64'],
            'ingredients.*.preparation' => ['nullable', 'string', 'max:255'],
            'ingredients.*.is_optional' => ['sometimes', 'boolean'],
            'ingredients.*.group_name' => ['nullable', 'string', 'max:255'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*' => ['required', 'array'],
            'steps.*.position' => ['nullable', 'integer', 'min:1'],
            'steps.*.instruction' => ['required', 'string', 'min:1'],
            'steps.*.duration_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'tags' => ['sometimes', 'array', 'max:50'],
            'tags.*' => ['required', 'string', 'min:1', 'max:64'],
        ];
    }
}
