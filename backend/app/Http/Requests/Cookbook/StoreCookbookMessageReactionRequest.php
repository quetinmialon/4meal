<?php

namespace App\Http\Requests\Cookbook;

use App\Models\CookbookMessageReaction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCookbookMessageReactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<ValidationRule|string>> */
    public function rules(): array
    {
        return [
            'emoji' => ['required', 'string', Rule::in(CookbookMessageReaction::ALLOWED_EMOJIS)],
        ];
    }
}
