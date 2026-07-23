<?php

namespace App\Http\Requests\Cookbook;

use App\Models\Cookbook;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateCookbookRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cookbook = $this->route('cookbook');

        return $cookbook instanceof Cookbook
            && Gate::forUser($this->user())->allows('update', $cookbook);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->string('name')->toString())]);
        }
    }

    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'slug' => [
                'nullable', 'string', 'min:1', 'max:255',
                Rule::unique('cookbooks', 'slug')->ignore($this->route('cookbook')),
            ],
            'description' => ['nullable', 'string'],
            'image_path' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
