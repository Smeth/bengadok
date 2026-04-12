/** Aligné sur DokPharmaController (envoi disponibilité) et setMontantLivraison. */
export type PivotPourSousTotal = {
    quantite: number;
    quantite_confirmee?: number | null;
    prix_unitaire: number | string | null | undefined;
    status: string;
};

export function montantLigneCommande(pivot: PivotPourSousTotal): number {
    if (pivot.status === 'indisponible') {
        return 0;
    }
    const pu = Number(pivot.prix_unitaire ?? 0);
    if (!Number.isFinite(pu)) {
        return 0;
    }
    const qte =
        pivot.quantite_confirmee != null
            ? pivot.quantite_confirmee
            : pivot.quantite;

    return qte * pu;
}

export function sousTotalCommandeProduits<
    T extends { pivot: PivotPourSousTotal },
>(produits: T[] | undefined): number {
    if (!produits?.length) {
        return 0;
    }
    return produits.reduce((s, p) => s + montantLigneCommande(p.pivot), 0);
}
