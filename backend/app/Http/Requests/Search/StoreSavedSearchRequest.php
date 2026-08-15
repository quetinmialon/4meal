<?php

namespace App\Http\Requests\Search;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSavedSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $criteria = $this->input('criteria');
        if (is_array($criteria)) {
            foreach (['q', 'tag', 'ingredient'] as $field) {
                if (is_string($criteria[$field] ?? null)) {
                    $criteria[$field] = trim($criteria[$field]);
                }
            }

            if (is_string($criteria['favorites'] ?? null)) {
                $favorites = mb_strtolower(trim($criteria['favorites']));
                if (in_array($favorites, ['true', 'false'], true)) {
                    $criteria['favorites'] = $favorites === 'true';
                }
            }

            $this->merge(['criteria' => $criteria]);
        }

        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->string('name')->toString())]);
        }
    }

    /** @return array<string, list<ValidationRule|string>> */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'min:1', 'max:100',
                Rule::unique('saved_searches', 'name')->where(fn ($query) => $query->where('user_id', $this->user()->getAuthIdentifier())),
            ],
            'criteria' => ['required', 'array:q,scope,cookbook_id,tag,ingredient,max_prep_time,max_cook_time,favorites,min_rating,sort'],
            'criteria.q' => ['nullable', 'string', 'min:1', 'max:255'],
            'criteria.scope' => ['sometimes', 'string', Rule::in(['accessible', 'mine', 'public'])],
            'criteria.cookbook_id' => ['nullable', 'uuid', Rule::exists('cookbooks', 'public_id')->whereNull('deleted_at')],
            'criteria.tag' => ['sometimes', 'string', 'min:1', 'max:64'],
            'criteria.ingredient' => ['sometimes', 'string', 'min:1', 'max:255'],
            'criteria.max_prep_time' => ['sometimes', 'integer', 'min:0', 'max:10080'],
            'criteria.max_cook_time' => ['sometimes', 'integer', 'min:0', 'max:10080'],
            'criteria.favorites' => ['sometimes', 'boolean'],
            'criteria.min_rating' => ['sometimes', 'numeric', 'min:0', 'max:5'],
            'criteria.sort' => ['sometimes', 'string', Rule::in(['rating_desc', 'rating_asc'])],
        ];
    }
}
