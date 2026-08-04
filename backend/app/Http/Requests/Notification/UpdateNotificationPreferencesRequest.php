<?php

namespace App\Http\Requests\Notification;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<ValidationRule|string>> */
    public function rules(): array
    {
        return [
            'preferences' => ['required', 'array', 'min:1'],
            'preferences.*' => ['required', 'array:type,channel'],
            'preferences.*.type' => ['required', new Enum(NotificationType::class)],
            'preferences.*.channel' => ['required', new Enum(NotificationChannel::class)],
        ];
    }
}
