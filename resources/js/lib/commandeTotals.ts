/** Aligné sur DokPharmaController (envoi disponibilité) et setMontantLivraison. */
export type PivotPourSousTotal = {
    quantite: number;
    quantite_confirmee?: number | null;
    prix_unitaire: number | string | null | undefined;
    status: string;
};

export function isParapharmaType(
    type: string | null | undefined,
    types: string[],
): boolean {
    if (!types.length) {
        return (
            typeof type === 'string' &&
            type.toLowerCase().includes('parapharm')
        );
    }

    return types.includes(type ?? '');
}

export function montantLigneCommande(pivot: PivotPourSousTotal): number {
    if (
        pivot.status === 'indisponible' ||
        pivot.status === 'en_attente' ||
        pivot.status == null
    ) {
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

/** Lignes affichées sur le reçu client (hors indisponible). */
export function pivotIncluseDansRecu(status: string | null | undefined): boolean {
    return status !== 'indisponible' && status !== 'en_attente';
}

export function produitsPourRecu<T extends { pivot: PivotPourSousTotal }>(
    produits: T[] | undefined,
): T[] {
    if (!produits?.length) {
        return [];
    }
    return produits.filter((p) => pivotIncluseDansRecu(p.pivot.status));
}

export type ProduitAvecPivotEtType = {
    type?: string | null;
    pivot: PivotPourSousTotal;
};

export function splitProduitsCommande<T extends ProduitAvecPivotEtType>(
    produits: T[] | undefined,
    parapharmaTypes: string[],
) {
    const medicaments: T[] = [];
    const parapharma: T[] = [];
    let sousTotalMedicaments = 0;
    let sousTotalParapharma = 0;

    for (const p of produits ?? []) {
        const montant = montantLigneCommande(p.pivot);
        if (isParapharmaType(p.type, parapharmaTypes)) {
            parapharma.push(p);
            sousTotalParapharma += montant;
        } else {
            medicaments.push(p);
            sousTotalMedicaments += montant;
        }
    }

    return {
        medicaments,
        parapharma,
        sousTotalMedicaments,
        sousTotalParapharma,
        sousTotal: sousTotalMedicaments + sousTotalParapharma,
    };
}
