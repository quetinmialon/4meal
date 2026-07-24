<?php

namespace App\Http\Requests\Recipe;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListRecipesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        foreach (['q', 'tag', 'ingredient'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->string($field)->toString())]);
            }
        }

        if (is_string($this->input('favorites'))) {
            $favorites = mb_strtolower(trim($this->input('favorites')));

            if (in_array($favorites, ['true', 'false'], true)) {
                $this->merge(['favorites' => $favorites === 'true']);
            }
        }
    }

    /** @return array<string, list<ValidationRule|string>> */
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'scope' => ['sometimes', 'string', Rule::in(['accessible', 'mine', 'public'])],
            'q' => ['nullable', 'string', 'min:1', 'max:255'],
            'cookbook_id' => [
                'nullable',
                'uuid',
                Rule::exists('cookbooks', 'public_id')->whereNull('deleted_at'),
            ],
            'tag' => ['sometimes', 'string', 'min:1', 'max:64'],
            'ingredient' => ['sometimes', 'string', 'min:1', 'max:255'],
            'max_prep_time' => ['sometimes', 'integer', 'min:0', 'max:10080'],
            'max_cook_time' => ['sometimes', 'integer', 'min:0', 'max:10080'],
            'favorites' => ['sometimes', 'boolean'],
        ];
    }
}
