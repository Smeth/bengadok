<?php

namespace App\Http\Requests;

use App\Models\Commande;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkAnnulerCommandesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('bulkAnnuler', Commande::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:commandes,id',
            'motif_annulation' => ['required', 'string', 'max:100', Rule::exists('motifs_annulation', 'slug')],
            'note_annulation' => 'nullable|string|max:1000',
        ];
    }
}
