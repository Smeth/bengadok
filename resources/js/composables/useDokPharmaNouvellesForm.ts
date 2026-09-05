import { router } from '@inertiajs/vue3';
import { ref, watch, type Ref } from 'vue';
import type { DokPharmaCommande, DokPharmaProduit } from '@/lib/dokPharmaCommande';
import { estVenteLibreProduit } from '@/lib/dokPharmaCommande';
import { normaliserStatutDisponibiliteLigne } from '@/lib/commandeProduitStatus';

export type LigneForm = {
    prix: string;
    quantite: string;
    dispo: boolean | null;
    venteLibre: boolean;
};

export function useDokPharmaNouvellesForm(options: {
    commandes: Ref<DokPharmaCommande[]>;
    expandedCards: Ref<Set<number>>;
    collapseCard: (cmdId: number) => void;
    onEnvoiSuccess?: () => void;
}) {
    const formLignes = ref<Record<number, Record<number, LigneForm>>>({});
    const formCommentaires = ref<Record<number, string>>({});
    const formLignesRevision = ref(0);

    watch(
        formLignes,
        () => {
            formLignesRevision.value++;
        },
        { deep: true },
    );

    function parseNombreFr(v: string | number | undefined | null): number {
        if (v === undefined || v === null || v === '') return NaN;
        if (typeof v === 'number') return Number.isFinite(v) ? v : NaN;
        const s = String(v)
            .trim()
            .replace(/\s/g, '')
            .replace(/\u00a0/g, '')
            .replace(',', '.');
        if (s === '') return NaN;
        const n = Number(s);
        return Number.isFinite(n) ? n : NaN;
    }

    function qteConfirmeeParsee(ligne: LigneForm | undefined): number {
        if (!ligne?.quantite || String(ligne.quantite).trim() === '') return NaN;
        const n = parseInt(String(ligne.quantite).trim(), 10);
        if (!Number.isFinite(n)) return NaN;
        return n;
    }

    function initForm(cmd: DokPharmaCommande) {
        if (!formLignes.value[cmd.id]) {
            formLignes.value[cmd.id] = {};
        }
        const map = formLignes.value[cmd.id];
        cmd.produits.forEach((p) => {
            if (map[p.id]) return;
            const qDem = Number(p.pivot.quantite) || 1;
            const st = normaliserStatutDisponibiliteLigne(p.pivot.status);
            map[p.id] = {
                prix:
                    p.pivot.prix_unitaire > 0
                        ? String(p.pivot.prix_unitaire)
                        : '',
                quantite: String(
                    p.pivot.quantite_confirmee ?? p.pivot.quantite ?? qDem,
                ),
                dispo:
                    st === 'disponible' || st === 'partiel'
                        ? true
                        : st === 'indisponible'
                          ? false
                          : null,
                venteLibre: estVenteLibreProduit(p),
            };
        });
        if (formCommentaires.value[cmd.id] === undefined) {
            formCommentaires.value[cmd.id] = cmd.commentaire_pharmacie ?? '';
        }
    }

    watch(
        () => options.commandes.value,
        () => {
            for (const cmd of options.commandes.value) {
                if (options.expandedCards.value.has(cmd.id)) {
                    initForm(cmd);
                }
            }
        },
        { deep: true },
    );

    function totalCmd(cmd: DokPharmaCommande): number {
        const lignes = formLignes.value[cmd.id];
        if (!lignes) return 0;
        return cmd.produits.reduce((sum, p) => {
            const l = lignes[p.id];
            if (l?.dispo !== true) return sum;
            const prix = parseNombreFr(l.prix);
            const qte = qteConfirmeeParsee(l);
            if (!Number.isFinite(prix) || !Number.isFinite(qte)) return sum;
            return sum + prix * qte;
        }, 0);
    }

    function totalLigne(cmdId: number, produit: DokPharmaProduit): string {
        const ligne = formLignes.value[cmdId]?.[produit.id];
        if (ligne?.dispo !== true) return '';
        const prix = parseNombreFr(ligne.prix);
        const qte = qteConfirmeeParsee(ligne);
        if (
            !Number.isFinite(prix) ||
            !Number.isFinite(qte) ||
            prix <= 0 ||
            qte <= 0
        ) {
            return '';
        }
        return (prix * qte).toLocaleString('fr-FR');
    }

    function qteInvalide(cmdId: number, produit: DokPharmaProduit): boolean {
        const ligne = formLignes.value[cmdId]?.[produit.id];
        if (ligne?.dispo !== true) return false;
        const qte = qteConfirmeeParsee(ligne);
        if (!Number.isFinite(qte)) return true;
        return qte > produit.pivot.quantite || qte < 1;
    }

    function hasQteError(cmd: DokPharmaCommande): boolean {
        return cmd.produits.some((p) => qteInvalide(cmd.id, p));
    }

    function hasPrixError(cmd: DokPharmaCommande): boolean {
        return cmd.produits.some((p) => {
            const ligne = formLignes.value[cmd.id]?.[p.id];
            if (ligne?.dispo !== true) return false;
            const px = parseNombreFr(ligne.prix);
            return !Number.isFinite(px) || px <= 0;
        });
    }

    function hasUnresolvedDispo(cmd: DokPharmaCommande): boolean {
        return cmd.produits.some(
            (p) => formLignes.value[cmd.id]?.[p.id]?.dispo === null,
        );
    }

    function toggleDispo(cmdId: number, produitId: number): void {
        const ligne = formLignes.value[cmdId]?.[produitId];
        if (!ligne) return;
        if (ligne.dispo === null) {
            ligne.dispo = true;
        } else if (ligne.dispo === true) {
            ligne.dispo = false;
        } else {
            ligne.dispo = true;
        }
    }

    function statutDispoForm(
        cmdId: number,
        produitId: number,
    ): 'en_attente' | 'disponible' | 'indisponible' {
        const d = formLignes.value[cmdId]?.[produitId]?.dispo;
        if (d === null || d === undefined) return 'en_attente';
        return d ? 'disponible' : 'indisponible';
    }

    function peutEnvoyerDisponibilite(cmd: DokPharmaCommande): boolean {
        void formLignesRevision.value;
        return (
            !hasQteError(cmd) &&
            !hasPrixError(cmd) &&
            !hasUnresolvedDispo(cmd)
        );
    }

    function envoyer(cmd: DokPharmaCommande) {
        if (!peutEnvoyerDisponibilite(cmd)) return;
        const lignes = cmd.produits.map((p) => {
            const ligne = formLignes.value[cmd.id]?.[p.id];
            const qte = qteConfirmeeParsee(ligne);
            const pxBrut = ligne?.dispo === true ? parseNombreFr(ligne.prix) : 0;
            const prixUnitaire = Number.isFinite(pxBrut) ? pxBrut : 0;
            return {
                produit_id: p.id,
                status: ligne?.dispo === true ? 'disponible' : 'indisponible',
                prix_unitaire: prixUnitaire,
                quantite_confirmee: Number.isFinite(qte) ? qte : p.pivot.quantite,
                vente_libre: ligne?.venteLibre ?? false,
            };
        });
        router.post(
            `/dok-pharma/${cmd.id}/valider`,
            { lignes, commentaire: formCommentaires.value[cmd.id] ?? '' },
            {
                preserveScroll: true,
                onSuccess: () => {
                    options.collapseCard(cmd.id);
                    options.onEnvoiSuccess?.();
                },
            },
        );
    }

    return {
        formLignes,
        formCommentaires,
        initForm,
        totalCmd,
        totalLigne,
        qteInvalide,
        hasQteError,
        hasPrixError,
        toggleDispo,
        statutDispoForm,
        peutEnvoyerDisponibilite,
        envoyer,
    };
}
