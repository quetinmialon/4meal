<?php

namespace App\Http\Requests\Auth;

use App\Enums\Diet;
use App\Enums\Theme;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('update', $user);
    }

    protected function prepareForValidation(): void
    {
        $values = [];

        if (is_string($this->input('name'))) {
            $values['name'] = trim($this->string('name')->toString());
        }

        if (is_string($this->input('email'))) {
            $values['email'] = mb_strtolower(trim($this->string('email')->toString()));
        }

        if (is_array($this->input('allergies'))) {
            $allergies = [];

            foreach ($this->input('allergies') as $allergy) {
                $normalized = is_string($allergy) ? trim($allergy) : $allergy;

                if ($normalized === null || $normalized === '') {
                    continue;
                }

                if (! in_array($normalized, $allergies, true)) {
                    $allergies[] = $normalized;
                }
            }

            $values['allergies'] = $allergies;
        }

        if ($values !== []) {
            $this->merge($values);
        }
    }

    /** @return array<string, list<ValidationRule|string>> */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        return [
            'name' => ['sometimes', 'string', 'min:2', 'max:255'],
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->getKey()),
            ],
            'avatar' => [
                'sometimes',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp',
                'extensions:jpg,jpeg,png,webp',
                'max:5120',
                'dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000',
            ],
            'current_password' => ['required_with:email', 'string'],
            'diet' => ['sometimes', 'nullable', Rule::enum(Diet::class)],
            'allergies' => ['sometimes', 'array', 'max:50'],
            'allergies.*' => ['string', 'min:1', 'max:100'],
            'default_servings' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'theme' => ['sometimes', Rule::enum(Theme::class)],
        ];
    }
}
