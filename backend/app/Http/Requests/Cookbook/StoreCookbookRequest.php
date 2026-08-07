<?php

namespace App\Http\Requests\Cookbook;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCookbookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->string('name')->toString())]);
        }

        foreach (['slug', 'description'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->string($field)->toString())]);
            }
        }
    }

    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'slug' => ['nullable', 'string', 'min:1', 'max:255', Rule::unique('cookbooks', 'slug')],
            'description' => ['nullable', 'string', 'max:5000'],
            'image' => [
                'nullable',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp',
                'extensions:jpg,jpeg,png,webp',
                'max:5120',
                'dimensions:min_width=200,min_height=200,max_width=4000,max_height=4000',
            ],
        ];
    }
}
