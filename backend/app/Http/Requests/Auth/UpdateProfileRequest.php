<?php

namespace App\Http\Requests\Auth;

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
            'avatar_path' => [
                'sometimes',
                'nullable',
                'string',
                'max:2048',
                'regex:/\\A[^\\x00-\\x1F\\x7F]+\\z/u',
            ],
            'current_password' => ['required_with:email', 'string'],
        ];
    }
}
