<?php

namespace App\Http\Requests\Planning;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlannedMealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<ValidationRule|string>> */
    public function rules(): array
    {
        return [
            'date' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'meal_type' => ['sometimes', 'required', 'string', Rule::in(['breakfast', 'lunch', 'dinner', 'snack'])],
            'note' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
