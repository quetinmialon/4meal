<?php

namespace App\Http\Requests\Planning;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeletePlannedMealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<ValidationRule|string>> */
    public function rules(): array
    {
        return ['scope' => ['sometimes', 'string', Rule::in(['occurrence', 'series'])]];
    }
}
