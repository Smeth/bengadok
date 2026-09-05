<?php

namespace App\Http\Requests;

use App\Models\Commande;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCommandeStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $commande = $this->route('commande');

        return $commande instanceof Commande
            && $this->user()?->can('manageStatut', $commande);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => 'required|in:nouvelle,en_attente,validee,retiree,annulee',
            'motif_annulation' => ['required_if:status,annulee', 'nullable', 'string', 'max:100', Rule::exists('motifs_annulation', 'slug')],
            'note_annulation' => 'nullable|string|max:1000',
        ];
    }
}
