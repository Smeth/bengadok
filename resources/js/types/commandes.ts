export type CommandeDetail = {
    id: number;
    numero: string;
    date: string;
    heurs?: string;
    status: string;
    /** État côté pharmacie (ex. indisponible = aucune ligne disponible après retour pharmacie). */
    status_pharmacie?: string;
    prix_total: number;
    prix_medicaments?: number;
    prix_parapharma?: number;
    commentaire?: string;
    beneficiaire?: string | null;
    /** Note saisie par la pharmacie lors de la validation de disponibilité. */
    commentaire_pharmacie?: string | null;
    motif_annulation?: string;
    note_annulation?: string;
    /** True si une commande a déjà été créée en relance depuis celle-ci (statut annulée). */
    deja_relancee?: boolean;
    /** Pour le délai de relance (même pharmacie), aligné sur le modèle Laravel */
    updated_at?: string;
    created_at?: string;
    client: {
        id?: number;
        nom: string;
        prenom: string;
        tel: string;
        adresse?: string;
        arrondissement?: string;
        sexe?: string;
    };
    pharmacie: {
        id?: number;
        zone_id?: number;
        zone?: { id: number };
        designation: string;
        telephone: string;
        adresse: string;
    };
    produits: Array<{
        id: number;
        designation: string;
        dosage?: string;
        forme?: string | null;
        type?: string | null;
        pivot: {
            quantite: number;
            quantite_confirmee?: number | null;
            prix_unitaire: number;
            status: string;
            vente_libre?: boolean;
        };
    }>;
    mode_paiement?: { id?: number; designation: string };
    montant_livraison?: { designation: number };
    livreur?: { id: number; nom: string; prenom: string; tel: string };
    ordonnance?: {
        file_url?: string;
        is_pdf?: boolean;
        verification?: {
            decision: string;
            score: number | null;
            status: string;
            parsed_prescription_date?: string | null;
            rule_results?: Record<
                string,
                { pass: boolean; label: string }
            > | null;
            flags?: Record<string, boolean> | null;
            error_message?: string | null;
        } | null;
    } | null;
    acceptation_client?: boolean;
    pieces_jointes?: Array<{
        id: number;
        label?: string | null;
        original_name?: string | null;
        file_url?: string | null;
        is_pdf?: boolean;
        created_at?: string | null;
        uploaded_by?: string | null;
    }>;
    /** Livraisons multiples : ce qui sera validé en bloc avec une commande parente. */
    enfants?: Array<{
        id: number;
        numero: string;
        status: string;
        pharmacie?: { designation?: string };
        mode_paiement?: { id?: number; designation: string };
        montant_livraison?: { designation?: number };
    }>;
};

export type CommandeListItem = {
    id: number;
    numero: string;
    date: string;
    status: string;
    prix_total: number;
    medicaments_resume: string;
    client: {
        nom: string;
        prenom: string;
        tel: string;
        adresse?: string;
        arrondissement?: string;
        sexe?: string;
    };
    /** @deprecated Présent uniquement sur d'anciennes réponses — préférer medicaments_resume */
    produits?: Array<{
        designation: string;
        dosage?: string;
        pivot: { quantite: number };
    }>;
    pharmacie?: { designation: string };
    montant_livraison?: { designation: number };
    mode_paiement?: { id?: number; designation: string };
};

export type CommandeListResponse = {
    data: CommandeListItem[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    from?: number | null;
    to?: number | null;
    total?: number;
};

export type PharmacieOption = {
    id: number;
    designation: string;
    adresse: string;
    telephone: string;
    zone_id?: number;
    de_garde?: boolean;
    zone?: { id: number; designation: string };
    type_pharmacie?: { designation: string };
    heurs?: { ouverture: string; fermeture: string };
};

export const STATUTS_COMMANDE = [
    {
        key: 'nouvelle',
        label: 'Nouvelles Commandes',
        statsKey: 'nouvelles',
        color: '#459cd1',
        textColor: 'white',
    },
    {
        key: 'en_attente',
        label: 'En Attente',
        statsKey: 'en_attente',
        color: '#fd7e14',
        textColor: 'white',
    },
    {
        key: 'annulee',
        label: 'Annulée',
        statsKey: 'annulees',
        color: '#e7000b',
        textColor: 'white',
    },
    {
        key: 'validee',
        label: 'Validée',
        statsKey: 'validees',
        color: '#198754',
        textColor: 'white',
    },
    {
        key: 'retiree',
        label: 'Livrée',
        statsKey: 'livrees',
        color: 'white',
        textColor: '#016630',
        borderColor: '#016630',
    },
] as const;

export type CommandeStatutConfig = (typeof STATUTS_COMMANDE)[number];

/** Couleur d’accent (bordure / texte inactif) pour un statut commande. */
export function commandeStatutAccentColor(s: CommandeStatutConfig): string {
    return s.borderColor ?? (s.color === 'white' ? s.textColor : s.color);
}

/** Style pill filtre statut (actif ou inactif), toujours lisible sur fond clair ou dégradé. */
export function commandeStatutFilterStyle(
    s: CommandeStatutConfig,
    active: boolean,
): Record<string, string> {
    const accent = commandeStatutAccentColor(s);
    if (active) {
        return {
            backgroundColor: s.color === 'white' ? '#ffffff' : s.color,
            color: s.textColor,
            border: `2px solid ${accent}`,
        };
    }
    return {
        backgroundColor: '#ffffff',
        color: accent,
        border: `1px solid ${accent}`,
    };
}

/** Style badge statut dans le tableau. */
export function commandeStatutBadgeStyle(statusKey: string): Record<string, string> {
    const key =
        statusKey === 'a_preparer'
            ? 'validee'
            : statusKey === 'retiree'
              ? 'retiree'
              : statusKey;
    const st = STATUTS_COMMANDE.find((x) => x.key === key);
    if (!st) {
        return {
            backgroundColor: '#f3f4f6',
            color: '#374151',
            border: '1px solid #d1d5db',
        };
    }
    const accent = commandeStatutAccentColor(st);
    if (st.key === 'retiree') {
        return {
            backgroundColor: '#ecfdf5',
            color: st.textColor,
            border: `1px solid ${accent}`,
        };
    }
    return {
        backgroundColor: st.color,
        color: st.textColor,
        border: 'none',
    };
}

/** Libellé affiché pour un statut commande. */
export function commandeStatutLabel(statusKey: string): string {
    if (statusKey === 'a_preparer') return 'Validée';
    if (statusKey === 'retiree') return 'Livrée';
    const st = STATUTS_COMMANDE.find((x) => x.key === statusKey);
    return st?.label ?? statusKey;
}

/** Motif d’annulation (données partagées Inertia `motifs_annulation`) */
export type MotifAnnulationOption = {
    slug: string;
    label: string;
    autorise_relance: boolean;
};
