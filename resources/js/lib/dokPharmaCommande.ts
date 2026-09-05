import { clientNomAvecCivilite } from '@/lib/clientDisplayName';

export type DokPharmaPivot = {
    quantite: number;
    prix_unitaire: number;
    status: string;
    quantite_confirmee: number | null;
    vente_libre?: boolean;
};

export type DokPharmaProduit = {
    id: number;
    designation: string;
    pivot: DokPharmaPivot;
};

export type DokPharmaCommande = {
    id: number;
    numero: string;
    date: string;
    status: string;
    status_pharmacie: string;
    client: { nom: string; prenom: string; sexe?: string | null } | null;
    produits: DokPharmaProduit[];
    ordonnance_id?: number | null;
    ordonnance_url?: string | null;
    ordonnance_is_pdf?: boolean;
    commentaire?: string | null;
    commentaire_pharmacie?: string | null;
    prix_medicaments?: number | null;
    pieces_jointes?: Array<{
        id: number;
        label?: string | null;
        original_name?: string | null;
        file_url?: string | null;
        is_pdf?: boolean;
        created_at?: string | null;
    }>;
};

export type DokPharmaPaginatedCommandes = {
    data: DokPharmaCommande[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    from: number;
    to: number;
    total: number;
};

export function qteDisponibleNombre(p: DokPharmaProduit): number {
    if (p.pivot.status === 'indisponible') return 0;
    const c = p.pivot.quantite_confirmee;
    if (c !== null && c !== undefined) return c;
    return p.pivot.quantite;
}

export function estVenteLibreProduit(p: DokPharmaProduit): boolean {
    return Boolean(p.pivot.vente_libre);
}

export function qteDisponibleAffichee(p: DokPharmaProduit): string {
    if (p.pivot.status === 'indisponible') return '—';
    const c = p.pivot.quantite_confirmee;
    if (c !== null && c !== undefined) return String(c);
    return String(p.pivot.quantite);
}

export function nomCommandeVisible(cmd: DokPharmaCommande): boolean {
    if (!cmd.client) return false;
    const n = clientNomAvecCivilite(cmd.client).trim();
    return n !== '' && n !== '-';
}

export function peutAjouterPieceJointe(cmd: DokPharmaCommande): boolean {
    return cmd.status !== 'annulee';
}
