<?php

namespace App\Http\Requests\Notification;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ListNotificationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<ValidationRule|string>> */
    public function rules(): array
    {
        return ['per_page' => ['sometimes', 'integer', 'min:1', 'max:100']];
    }
}
