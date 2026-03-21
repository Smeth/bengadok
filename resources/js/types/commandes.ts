export type CommandeDetail = {
    id: number;
    numero: string;
    date: string;
    heurs?: string;
    status: string;
    prix_total: number;
    commentaire?: string;
    motif_annulation?: string;
    note_annulation?: string;
    client: {
        id?: number;
        nom: string;
        prenom: string;
        tel: string;
        adresse?: string;
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
        pivot: { quantite: number; prix_unitaire: number; status: string };
    }>;
    mode_paiement?: { designation: string };
    montant_livraison?: { designation: number };
    ordonnance?: { urlfile?: string } | null;
    acceptation_client?: boolean;
};

export type CommandeListItem = {
    id: number;
    numero: string;
    date: string;
    status: string;
    prix_total: number;
    client: { nom: string; prenom: string; tel: string; adresse?: string; sexe?: string };
    pharmacie?: { designation: string };
    produits: Array<{ designation: string; dosage?: string; pivot: { quantite: number } }>;
    montant_livraison?: { designation: number };
    mode_paiement?: { designation: string };
};

export type CommandeListResponse = {
    data: CommandeListItem[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
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
    { key: 'nouvelle', label: 'Nouvelles Commandes', statsKey: 'nouvelles', color: '#0d6efd', textColor: 'white' },
    { key: 'en_attente', label: 'En Attente', statsKey: 'en_attente', color: '#fd7e14', textColor: 'white' },
    { key: 'annulee', label: 'Annulée', statsKey: 'annulees', color: '#e7000b', textColor: 'white' },
    { key: 'validee', label: 'Validée', statsKey: 'validees', color: '#198754', textColor: 'white' },
    { key: 'retiree', label: 'Livrée', statsKey: 'livrees', color: 'white', textColor: '#016630', borderColor: '#016630' },
] as const;

/** Motif d’annulation (données partagées Inertia `motifs_annulation`) */
export type MotifAnnulationOption = {
    slug: string;
    label: string;
    autorise_relance: boolean;
};
