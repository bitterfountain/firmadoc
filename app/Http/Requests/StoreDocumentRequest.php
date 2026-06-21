<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $extensions = implode(',', config('docsigner.allowed_extensions'));
        $maxKb = (int) config('docsigner.max_upload_kb');

        return [
            'file' => "required|file|mimes:{$extensions}|max:{$maxKb}",
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Selecciona un archivo.',
            'file.mimes' => 'Formato no soportado. Acepta: '.implode(', ', config('docsigner.allowed_extensions')).'.',
            'file.max' => 'El archivo supera el tamano maximo permitido.',
        ];
    }
}
