<?php

/**
 * Réglages produit BengaDok.
 */
return [
    /*
    | Valeurs par défaut parapharmacie / crédits (repli si app_settings absent).
    | Réglage courant : Paramètres → onglet « Parapharmacie & crédits » (/settings/parametres?onglet=parapharma).
    */
    'parapharma' => [
        'commission_percent' => (float) env('PARAPHARMA_COMMISSION_PERCENT', 1),
        'commission_jour_echeance' => (int) env('PARAPHARMA_COMMISSION_JOUR_ECHEANCE', 25),
        'periode_jour_fin' => (int) env('PARAPHARMA_PERIODE_JOUR_FIN', 25),
        'credit_seuil_medicament_xaf' => (int) env('PARAPHARMA_CREDIT_SEUIL_MEDICAMENT_XAF', 5000),
        'credit_prix_unitaire_xaf' => (int) env('PARAPHARMA_CREDIT_PRIX_UNITAIRE_XAF', 150),
        'credit_minimum_achat' => (int) env('PARAPHARMA_CREDIT_MINIMUM_ACHAT', 10),
        'credit_alerte_seuil' => (int) env('PARAPHARMA_CREDIT_ALERTE_SEUIL', 5),
        'credit_deduction_auto' => filter_var(
            env('PARAPHARMA_CREDIT_DEDUCTION_AUTO', true),
            FILTER_VALIDATE_BOOL
        ),
        /*
        | Types produit (colonne produits.type) considérés comme parapharmacie pour le CA et la commission.
        | Liste séparée par des virgules ; si vide, tout type contenant « parapharm » (insensible à la casse).
        */
        'produit_types' => array_values(array_filter(array_map(
            static fn (string $s): string => trim($s),
            explode(',', (string) env('PARAPHARMA_PRODUIT_TYPES', 'Parapharmacie'))
        ))),
    ],
];
