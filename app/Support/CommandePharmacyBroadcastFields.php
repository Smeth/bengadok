<?php

namespace App\Support;

/**
 * Champs Commande dont la modification doit rafraîchir l’espace pharmacie (WebSocket).
 * Limité aux données visibles dans DokPharma — pas de broadcast sur chaque toucher Eloquent.
 */
final class CommandePharmacyBroadcastFields
{
    /**
     * @var list<string>
     */
    public const FIELDS = [
        'status',
        'status_pharmacie',
        'pharmacie_id',
        'ordonnance_id',
        'commentaire',
        'beneficiaire',
        'client_id',
        'prix_medicaments',
        'prix_parapharma',
    ];
}
