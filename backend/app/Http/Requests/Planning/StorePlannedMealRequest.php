<?php

namespace App\Http\Requests\Planning;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlannedMealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        foreach (['date', 'meal_type', 'note'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->string($field)->toString())]);
            }
        }
    }

    /** @return array<string, list<ValidationRule|string>> */
    public function rules(): array
    {
        return [
            'recipe_id' => ['required', 'uuid', Rule::exists('recipes', 'public_id')->whereNull('deleted_at')],
            'cookbook_id' => ['nullable', 'uuid', Rule::exists('cookbooks', 'public_id')->whereNull('deleted_at')],
            'date' => ['required', 'date_format:Y-m-d'],
            'meal_type' => ['required', 'string', Rule::in(['breakfast', 'lunch', 'dinner', 'snack'])],
            'note' => ['nullable', 'string', 'max:5000'],
            'servings' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'recurrence' => ['sometimes', 'nullable', 'array'],
            'recurrence.frequency' => ['required_with:recurrence', 'string', Rule::in(['weekly'])],
            'recurrence.until' => ['required_with:recurrence', 'date_format:Y-m-d', 'after_or_equal:date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $recurrence = $this->input('recurrence');
            $date = $this->input('date');
            $until = is_array($recurrence) ? ($recurrence['until'] ?? null) : null;

            if (! is_string($date) || ! is_string($until)) {
                return;
            }

            try {
                $start = CarbonImmutable::createFromFormat('!Y-m-d', $date);
                $end = CarbonImmutable::createFromFormat('!Y-m-d', $until);
            } catch (\Throwable) {
                return;
            }

            if ($start !== null && $end !== null && $start->diffInWeeks($end) > 52) {
                $validator->errors()->add('recurrence.until', 'La récurrence ne peut pas dépasser 52 occurrences.');
            }
        });
    }
}
