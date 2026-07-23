<?php

namespace App\Http\Requests\Cookbook;

use App\Models\Cookbook;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreCookbookInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cookbook = $this->route('cookbook');

        return $cookbook instanceof Cookbook && Gate::forUser($this->user())->allows('update', $cookbook);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'string', Rule::in(['editor', 'viewer'])],
        ];
    }
}
