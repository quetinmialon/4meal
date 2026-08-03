<?php

namespace App\Http\Requests\Recipe;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DuplicateRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<ValidationRule|string>> */
    public function rules(): array
    {
        return [
            'cookbook_id' => [
                'nullable',
                'uuid',
                Rule::exists('cookbooks', 'public_id')->whereNull('deleted_at'),
            ],
        ];
    }
}
