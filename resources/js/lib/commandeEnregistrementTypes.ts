export type ProduitEnreg = {
    designation: string;
    dosage: string;
    forme: string;
    quantite: number;
    prix_unitaire: number;
};

export type FormEnregPayload = {
    client_nom: string;
    client_prenom: string;
    client_tel: string;
    client_adresse: string;
    client_arrondissement: string;
    /** M ou F — civilité affichée côté liste / détail */
    client_sexe: '' | 'M' | 'F';
    pharmacie_id: string;
    beneficiaire: string;
    montant_livraison_id?: string;
    produits: Array<{
        designation: string;
        dosage: string | null;
        forme: string | null;
        quantite: number;
        prix_unitaire: number;
        type?: string | null;
    }>;
    ordonnance: File | null;
    commentaire: string;
    client_id?: number;
    /** Relance sans nouveau fichier : réutiliser l’ordonnance de cette commande annulée */
    reutiliser_ordonnance_commande_id?: number;
    date?: string;
    heurs?: string;
};

export type CommandeRelance = {
    id?: number;
    /** Référence temporelle pour le délai « même pharmacie » (relance) */
    updated_at?: string;
    client?: {
        id?: number;
        nom?: string;
        prenom?: string;
        tel?: string;
        adresse?: string;
        arrondissement?: string;
        sexe?: string;
    };
    pharmacie?: { id?: number; zone_id?: number; zone?: { id: number } };
    produits?: Array<{
        designation?: string;
        dosage?: string;
        forme?: string | null;
        type?: string | null;
        pivot: { quantite: number; prix_unitaire: number };
    }>;
    ordonnance?: { file_url?: string } | null;
};

export type CommandeReferentielPharmacie = {
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
