<?php

namespace App\Http\Requests\Cookbook;

use App\Models\Cookbook;
use App\Support\CookbookPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateCookbookMemberRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cookbook = $this->route('cookbook');

        return $cookbook instanceof Cookbook
            && Gate::forUser($this->user())->allows(CookbookPermissions::MANAGE_MEMBERS, $cookbook);
    }

    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::in(CookbookPermissions::ROLES)],
        ];
    }
}
