<?php

namespace App\Http\Requests\Recipe;

use App\Models\Recipe;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DuplicateRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('confirmation'))) {
            $this->merge(['confirmation' => trim($this->string('confirmation')->toString())]);
        }
    }

    /** @return array<string, list<ValidationRule|string>> */
    public function rules(): array
    {
        $recipe = $this->route('recipe');

        return [
            'confirmation' => [
                'required',
                'string',
                Rule::in($recipe instanceof Recipe ? [$recipe->title] : []),
            ],
            'cookbook_id' => [
                'nullable',
                'uuid',
                Rule::exists('cookbooks', 'public_id')->whereNull('deleted_at'),
            ],
        ];
    }
}
