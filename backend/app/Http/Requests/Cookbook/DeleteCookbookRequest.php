<?php

namespace App\Http\Requests\Cookbook;

use App\Models\Cookbook;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DeleteCookbookRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cookbook = $this->route('cookbook');

        return $cookbook instanceof Cookbook
            && Gate::forUser($this->user())->allows('delete', $cookbook);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('confirmation'))) {
            $this->merge(['confirmation' => trim($this->string('confirmation')->toString())]);
        }
    }

    /**
     * @return array<string, list<ValidationRule|string|Rule>>
     */
    public function rules(): array
    {
        $cookbook = $this->route('cookbook');

        return [
            'confirmation' => [
                'required',
                'string',
                Rule::in($cookbook instanceof Cookbook ? [$cookbook->name] : []),
            ],
        ];
    }
}
