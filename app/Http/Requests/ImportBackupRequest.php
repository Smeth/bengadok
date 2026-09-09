<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super_admin') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'confirmation' => ['required', 'string', 'in:RESTAURER SAUVEGARDE'],
            'archive' => ['required', 'file', 'mimes:zip', 'max:524288'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirmation.in' => 'Confirmation invalide : saisissez exactement « RESTAURER SAUVEGARDE ».',
            'archive.required' => 'Sélectionnez un fichier ZIP exporté.',
            'archive.mimes' => 'Le fichier doit être une archive ZIP.',
            'archive.max' => 'L’archive ne doit pas dépasser 512 Mo.',
        ];
    }
}
