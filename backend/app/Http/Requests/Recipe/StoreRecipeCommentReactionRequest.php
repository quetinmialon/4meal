<?php

namespace App\Http\Requests\Recipe;

use App\Models\RecipeCommentReaction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecipeCommentReactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<ValidationRule|string>> */
    public function rules(): array
    {
        return ['emoji' => ['required', 'string', Rule::in(RecipeCommentReaction::ALLOWED_EMOJIS)]];
    }
}
