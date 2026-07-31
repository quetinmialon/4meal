<?php

namespace App\Http\Requests\Import;

use Illuminate\Foundation\Http\FormRequest;

final class ImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimetypes:application/json,application/ld+json,text/json',
                'extensions:json',
                'max:10240',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimetypes' => 'Le fichier doit être un document JSON.',
            'file.extensions' => 'Le fichier doit porter l’extension .json.',
            'file.max' => 'Le fichier JSON ne doit pas dépasser 10 Mo.',
        ];
    }
}
