<?php

/**
 * Réglages produit BengaDok — commission pharmacie prélevée sur le CA médicaments (pour affichage / estimation).
 */
return [
    /*
    | Pourcentage appliqué au CA médicaments de la période pour estimer les commissions à reverser à BengaDoc.
    | N’existe pas encore de module compta dédié ; ajustez via variable d’environnement ou ce fichier.
    */
    'pharmacy_commission_percent' => (float) env('PHARMACY_COMMISSION_PERCENT', 10),
];
