<?php

namespace App\Http\Requests\Import;

use Illuminate\Foundation\Http\FormRequest;

final class ImportCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['file' => ['required', 'file', 'mimetypes:text/csv,text/plain,application/csv', 'extensions:csv', 'max:10240']];
    }

    public function messages(): array
    {
        return ['file.mimetypes' => 'Le fichier doit être un CSV.', 'file.extensions' => 'Le fichier doit porter l’extension .csv.'];
    }
}
