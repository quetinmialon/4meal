<?php

namespace App\Http\Requests\Planning;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPlannedMealsRequest extends FormRequest
{
    public const MAX_PERIOD_DAYS = 31;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<ValidationRule|string>> */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'cookbook_id' => [
                'nullable',
                'uuid',
                Rule::exists('cookbooks', 'public_id')->whereNull('deleted_at'),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $from = $this->dateValue('from');
            $to = $this->dateValue('to');

            if ($from === null || $to === null || $to->lt($from)) {
                return;
            }

            if ($from->diffInDays($to) + 1 > self::MAX_PERIOD_DAYS) {
                $validator->errors()->add(
                    'to',
                    'La période ne peut pas dépasser '.self::MAX_PERIOD_DAYS.' jours.',
                );
            }
        });
    }

    private function dateValue(string $key): ?CarbonImmutable
    {
        $value = $this->input($key);

        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $value) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}
