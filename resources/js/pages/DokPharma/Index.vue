<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import {
    ChevronDown,
    ChevronUp,
    Paperclip,
    Clock,
    FileText,
    ShoppingCart,
    X,
    CheckCircle2,
    Eye,
    AlertCircle,
    Search,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';
import PharmaciePieceJointeSection from '@/components/dok-pharma/PharmaciePieceJointeSection.vue';
import ModulePagination from '@/components/shared/ModulePagination.vue';
import AppToast from '@/components/AppToast.vue';
import FlashToastHost from '@/components/FlashToastHost.vue';
import DokPharmaOrdonnanceViewerModal from '@/components/dok-pharma/DokPharmaOrdonnanceViewerModal.vue';
import DokPharmaValiderRetraitModal from '@/components/dok-pharma/DokPharmaValiderRetraitModal.vue';
import { Input } from '@/components/ui/input';
import PharmacyLayout from '@/layouts/PharmacyLayout.vue';
import { modulePaginationWrapperClass, pharmacyOrderCardClass } from '@/lib/bengadokUi';
import { sousTotalCommandeProduits } from '@/lib/commandeTotals';
import {
    classesStatutDisponibiliteLigne,
    libelleStatutDisponibiliteLigne,
    normaliserStatutDisponibiliteLigne,
} from '@/lib/commandeProduitStatus';
import { clientNomAvecCivilite } from '@/lib/clientDisplayName';

type Pivot = {
    quantite: number;
    prix_unitaire: number;
    status: string;
    quantite_confirmee: number | null;
    vente_libre?: boolean;
};
type Produit = { id: number; designation: string; pivot: Pivot };

/** Quantité servie / confirmée (0 si ligne indisponible). */
function qteDisponibleNombre(p: Produit): number {
    if (p.pivot.status === 'indisponible') return 0;
    const c = p.pivot.quantite_confirmee;
    if (c !== null && c !== undefined) return c;
    return p.pivot.quantite;
}

function estVenteLibre(p: Produit): boolean {
    return Boolean(p.pivot.vente_libre);
}

/** Affichage colonne « disponible » en lecture seule. */
function qteDisponibleAffichee(p: Produit): string {
    if (p.pivot.status === 'indisponible') return '—';
    const c = p.pivot.quantite_confirmee;
    if (c !== null && c !== undefined) return String(c);
    return String(p.pivot.quantite);
}

type Commande = {
    id: number;
    numero: string;
    date: string;
    status: string;
    status_pharmacie: string;
    client: { nom: string; prenom: string; sexe?: string | null } | null;
    produits: Produit[];
    ordonnance_id?: number | null;
    ordonnance_url?: string | null;
    ordonnance_is_pdf?: boolean;
    commentaire?: string | null;
    commentaire_pharmacie?: string | null;
    /** Montant médicaments (hors livraison) — seul total visible côté pharmacie */
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

/** Nom client affichable pour l’en-tête de carte (évite « - - BDK… »). */
function nomCommandeVisible(cmd: Commande): boolean {
    if (!cmd.client) return false;
    const n = clientNomAvecCivilite(cmd.client).trim();
    return n !== '' && n !== '-';
}

type PaginatedCommandes = {
    data: Commande[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    from: number;
    to: number;
    total: number;
};

const props = defineProps<{
    commandes: PaginatedCommandes;
    stats: {
        nouvelles: number;
        en_attente: number;
        a_preparer: number;
        livrees: number;
    };
    onglet: string;
    search?: string;
    canViewHistorique?: boolean;
}>();

const searchQuery = ref(props.search ?? '');
watch(
    () => props.search,
    (s) => {
        searchQuery.value = s ?? '';
    },
);

function commandesQueryParams(onglet: string) {
    const q = searchQuery.value.trim();
    return {
        onglet,
        ...(q ? { search: q } : {}),
    };
}

watchDebounced(
    searchQuery,
    (val) => {
        const q = val.trim();
        const serverQ = (props.search ?? '').trim();
        if (q === serverQ) return;
        router.get('/dok-pharma/commandes', commandesQueryParams(props.onglet), {
            preserveScroll: true,
            replace: true,
        });
    },
    { debounce: 400 },
);

function changeOnglet(o: string) {
    /* preserveState désactivé : après validation POST, garder l’ancien état local
     * (cartes ouvertes, formulaires) provoquait des incohérences et, avec le scroll,
     * des zones pouvaient sembler « mortes » jusqu’au rechargement. */
    router.get('/dok-pharma/commandes', commandesQueryParams(o), {
        preserveScroll: true,
    });
}

/* ─── Accordion ─────────────────────────────────────────────── */
const expandedCards = ref<Set<number>>(new Set());

function toggleCard(cmd: Commande) {
    const next = new Set(expandedCards.value);
    if (next.has(cmd.id)) {
        next.delete(cmd.id);
    } else {
        next.add(cmd.id);
        initForm(cmd);
    }
    expandedCards.value = next;
}

/* ─── Formulaire prix / disponibilité (onglet Nouvelles) ─────── */
type LigneForm = {
    prix: string;
    quantite: string;
    /** null = en attente de confirmation pharmacie */
    dispo: boolean | null;
    venteLibre: boolean;
};
const formLignes = ref<Record<number, Record<number, LigneForm>>>({});
const formCommentaires = ref<Record<number, string>>({});

/** Incrémenté à chaque changement du formulaire — force le recalcul disabled / classes du bouton Envoyer. */
const formLignesRevision = ref(0);
watch(
    formLignes,
    () => {
        formLignesRevision.value++;
    },
    { deep: true },
);

/** Nombre saisi (prix) : accepte la virgule française et les espaces — évite NaN qui bloque la validation. */
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

/**
 * Initialise ou complète les lignes du formulaire (sans écraser les saisies).
 * Important : le polling Inertia (reload preserveState) peut ajouter des produits après ouverture de la carte.
 */
function initForm(cmd: Commande) {
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
                p.pivot.prix_unitaire > 0 ? String(p.pivot.prix_unitaire) : '',
            quantite: String(
                p.pivot.quantite_confirmee ?? p.pivot.quantite ?? qDem,
            ),
            dispo:
                st === 'disponible' || st === 'partiel'
                    ? true
                    : st === 'indisponible'
                      ? false
                      : null,
            venteLibre: estVenteLibre(p),
        };
    });
    if (formCommentaires.value[cmd.id] === undefined) {
        formCommentaires.value[cmd.id] = cmd.commentaire_pharmacie ?? '';
    }
}

watch(
    () => props.commandes.data,
    () => {
        for (const cmd of props.commandes.data) {
            if (expandedCards.value.has(cmd.id)) {
                initForm(cmd);
            }
        }
    },
    { deep: true },
);

function totalCmd(cmd: Commande): number {
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

/** Total validé (médicaments + parapharmacie) à partir des prix en base. */
function totalCommandeValidee(cmd: Commande): number {
    return sousTotalCommandeProduits(cmd.produits);
}

function totalLigne(cmdId: number, produit: Produit): string {
    const ligne = formLignes.value[cmdId]?.[produit.id];
    if (ligne?.dispo !== true) return '';
    const prix = parseNombreFr(ligne.prix);
    const qte = qteConfirmeeParsee(ligne);
    if (
        !Number.isFinite(prix) ||
        !Number.isFinite(qte) ||
        prix <= 0 ||
        qte <= 0
    )
        return '';
    return (prix * qte).toLocaleString('fr-FR');
}

/** Retourne true si la quantité confirmée dépasse la quantité demandée */
function qteInvalide(cmdId: number, produit: Produit): boolean {
    const ligne = formLignes.value[cmdId]?.[produit.id];
    if (ligne?.dispo !== true) return false;
    const qte = qteConfirmeeParsee(ligne);
    if (!Number.isFinite(qte)) return true;
    return qte > produit.pivot.quantite || qte < 1;
}

/** Retourne true si au moins une ligne a une quantité invalide */
function hasQteError(cmd: Commande): boolean {
    return cmd.produits.some((p) => qteInvalide(cmd.id, p));
}

/** Retourne true si un produit disponible n'a pas de prix saisi (> 0) */
function hasPrixError(cmd: Commande): boolean {
    return cmd.produits.some((p) => {
        const ligne = formLignes.value[cmd.id]?.[p.id];
        if (ligne?.dispo !== true) return false;
        const px = parseNombreFr(ligne.prix);
        return !Number.isFinite(px) || px <= 0;
    });
}

/** Au moins une ligne sans choix disponibilité / indisponibilité. */
function hasUnresolvedDispo(cmd: Commande): boolean {
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

/** État du bouton Envoyer (même logique que l’action, avec dépendance explicite à la révision). */
function peutEnvoyerDisponibilite(cmd: Commande): boolean {
    void formLignesRevision.value;
    return (
        !hasQteError(cmd) &&
        !hasPrixError(cmd) &&
        !hasUnresolvedDispo(cmd)
    );
}

function envoyer(cmd: Commande) {
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
                const next = new Set(expandedCards.value);
                next.delete(cmd.id);
                expandedCards.value = next;
                showToast(
                    dispoSuccessToast,
                    'Envoyé au back-office',
                    'Disponibilité et prix transmis. La commande est en attente de validation.',
                );
            },
        },
    );
}

/* ─── Confirmation "Valider l'achat" ─────────────────────────── */
const confirmModal = ref<{ open: boolean; cmd: Commande | null }>({
    open: false,
    cmd: null,
});

function askValiderAchat(cmd: Commande) {
    confirmModal.value = { open: true, cmd };
}
function annulerConfirm() {
    confirmModal.value = { open: false, cmd: null };
}
function confirmerAchat() {
    if (!confirmModal.value.cmd) return;
    const id = confirmModal.value.cmd.id;
    confirmModal.value = { open: false, cmd: null };
    router.post(
        `/dok-pharma/${id}/valider-retrait`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                showToast(
                    retraitSuccessToast,
                    'Retrait validé',
                    'La commande a bien été retirée par le livreur en pharmacie.',
                );
            },
        },
    );
}

/* ─── Modal ordonnance ───────────────────────────────────────── */
const ordModal = ref({ open: false, url: '', isPdf: false, numero: '' });

const dispoSuccessToast = ref({ show: false, title: '', description: '' });
const retraitSuccessToast = ref({ show: false, title: '', description: '' });

function showToast(
    target: typeof dispoSuccessToast,
    title: string,
    description?: string,
) {
    target.value = { show: true, title, description: description ?? '' };
}

function openOrdonnance(cmd: Commande) {
    ordModal.value = {
        open: true,
        url: cmd.ordonnance_url ?? '',
        isPdf: cmd.ordonnance_is_pdf ?? false,
        numero: cmd.numero,
    };
}
function closeOrdonnance() {
    ordModal.value.open = false;
}

function peutAjouterPieceJointe(cmd: Commande): boolean {
    return cmd.status !== 'annulee';
}
</script>

<template>
    <Head title="Commandes - BengaDok" />

    <PharmacyLayout>
        <AppToast
            v-model:show="dispoSuccessToast.show"
            :title="dispoSuccessToast.title"
            :description="dispoSuccessToast.description"
        />
        <AppToast
            v-model:show="retraitSuccessToast.show"
            :title="retraitSuccessToast.title"
            :description="retraitSuccessToast.description"
        />
        <!-- Même fond et grille que le tableau de bord pharmacie -->
        <div class="pharmacy-content-shell flex min-h-full flex-1 flex-col">
            <div class="pharmacy-card mb-4 flex flex-col gap-2 p-3 sm:flex-row sm:items-center">
                <div class="relative min-w-0 flex-1">
                    <Search
                        class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-500"
                    />
                    <Input
                        v-model="searchQuery"
                        type="search"
                        placeholder="Rechercher par nom, n° commande ou médicament…"
                        class="h-10 w-full rounded-xl border border-white/80 bg-white/95 pl-10 pr-10 text-sm shadow-sm placeholder:text-gray-500 dark:border-border dark:bg-input/95 dark:text-foreground dark:placeholder:text-muted-foreground"
                        autocomplete="off"
                    />
                    <button
                        v-if="searchQuery.trim()"
                        type="button"
                        class="absolute right-2 top-1/2 -translate-y-1/2 rounded-md p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-800"
                        aria-label="Effacer la recherche"
                        @click="searchQuery = ''"
                    >
                        <X class="size-4" />
                    </button>
                </div>
            </div>
            <!-- Une seule barre d’onglets (compteurs + libellés), style aligné dashboard -->
            <div
                class="pharmacy-card mb-4 flex flex-wrap items-stretch gap-2 p-2 sm:gap-2 sm:p-3"
            >
                <button
                    type="button"
                    class="flex min-h-9 flex-1 items-center justify-center gap-2 rounded-lg px-2.5 py-1.5 text-xs font-semibold shadow-sm transition-all sm:min-w-0 sm:flex-none sm:px-3"
                    :class="
                        onglet === 'nouvelles'
                            ? 'bg-[#459cd1] text-white ring-2 ring-white/40'
                            : 'bg-white/80 text-gray-800 hover:bg-white dark:bg-card/80 dark:text-foreground dark:hover:bg-card'
                    "
                    @click="changeOnglet('nouvelles')"
                >
                    <span
                        class="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full px-1 text-[10px] font-bold tabular-nums"
                        :class="
                            onglet === 'nouvelles'
                                ? 'bg-white/25'
                                : 'bg-[#459cd1]/15 text-[#459cd1]'
                        "
                    >
                        {{ stats.nouvelles }}
                    </span>
                    <span class="hidden sm:inline">Nouvelles</span>
                    <span class="sm:hidden">Nouv.</span>
                </button>

                <button
                    type="button"
                    class="flex min-h-9 flex-1 items-center justify-center gap-2 rounded-lg px-2.5 py-1.5 text-xs font-semibold shadow-sm transition-all sm:flex-none sm:px-3"
                    :class="
                        onglet === 'en_attente'
                            ? 'bg-indigo-600 text-white ring-2 ring-white/40'
                            : 'bg-white/80 text-gray-800 hover:bg-white dark:bg-card/80 dark:text-foreground dark:hover:bg-card'
                    "
                    @click="changeOnglet('en_attente')"
                >
                    <span
                        class="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full px-1 text-[10px] font-bold tabular-nums"
                        :class="
                            onglet === 'en_attente'
                                ? 'bg-white/25'
                                : 'bg-indigo-100 text-indigo-700'
                        "
                    >
                        {{ stats.en_attente }}
                    </span>
                    <span class="hidden sm:inline">En attente</span>
                    <span class="sm:hidden">Att.</span>
                </button>

                <button
                    type="button"
                    class="flex min-h-9 flex-1 items-center justify-center gap-2 rounded-lg px-2.5 py-1.5 text-xs font-semibold shadow-sm transition-all sm:flex-none sm:px-3"
                    :class="
                        onglet === 'a_preparer'
                            ? 'bg-amber-500 text-white ring-2 ring-white/40'
                            : 'bg-white/80 text-gray-800 hover:bg-white dark:bg-card/80 dark:text-foreground dark:hover:bg-card'
                    "
                    @click="changeOnglet('a_preparer')"
                >
                    <span
                        class="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full px-1 text-[10px] font-bold tabular-nums"
                        :class="
                            onglet === 'a_preparer'
                                ? 'bg-white/25'
                                : 'bg-amber-100 text-amber-800'
                        "
                    >
                        {{ stats.a_preparer }}
                    </span>
                    <span class="hidden md:inline">À préparer</span>
                    <span class="md:hidden">Prép.</span>
                </button>

                <button
                    v-if="canViewHistorique !== false"
                    type="button"
                    class="flex min-h-9 flex-1 items-center justify-center gap-2 rounded-lg px-2.5 py-1.5 text-xs font-semibold shadow-sm transition-all sm:flex-none sm:px-3"
                    :class="
                        onglet === 'livrees'
                            ? 'bg-[#5bb66e] text-white ring-2 ring-white/40'
                            : 'bg-white/80 text-gray-800 hover:bg-white dark:bg-card/80 dark:text-foreground dark:hover:bg-card'
                    "
                    @click="changeOnglet('livrees')"
                >
                    <span
                        class="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full px-1 text-[10px] font-bold tabular-nums"
                        :class="
                            onglet === 'livrees'
                                ? 'bg-white/25'
                                : 'bg-emerald-100 text-emerald-800'
                        "
                    >
                        {{ stats.livrees }}
                    </span>
                    Retirées
                </button>
            </div>

            <div class="flex-1 space-y-3 pb-6">
                <!-- ═══ ONGLET : Nouvelles Commandes ═══ -->
                <template v-if="onglet === 'nouvelles'">
                    <div
                        v-for="cmd in commandes.data"
                        :key="cmd.id"
                        :class="pharmacyOrderCardClass"
                    >
                        <!-- En-tête -->
                        <div
                            class="flex cursor-pointer items-start justify-between px-5 py-4"
                            @click="toggleCard(cmd)"
                        >
                            <div class="flex-1 min-w-0">
                                <div
                                    class="mb-1.5 flex flex-wrap items-center gap-2"
                                >
                                    <span
                                        class="text-[15px] font-extrabold text-[#2563EB]"
                                    >
                                        <template v-if="nomCommandeVisible(cmd)">
                                            <span class="font-sans">{{
                                                clientNomAvecCivilite(cmd.client!)
                                            }}
                                                -
                                            </span>
                                        </template>
                                        <span class="font-mono">{{
                                            cmd.numero
                                        }}</span>
                                    </span>
                                    <span
                                        class="rounded-full bg-[#DBEAFE] px-2.5 py-0.5 text-[10px] font-bold text-[#1D4ED8]"
                                        >Nouvelle commande 🔄</span
                                    >
                                    <span
                                        v-if="cmd.ordonnance_id"
                                        class="flex items-center gap-1 rounded-full bg-[#E0F2FE] px-2.5 py-0.5 text-[10px] font-bold text-[#0369A1]"
                                    >
                                        <FileText class="size-3" />Ordonnance
                                    </span>
                                </div>
                                <div
                                    class="flex flex-wrap items-center gap-4 text-[12px] text-gray-500"
                                >
                                    <span class="flex items-center gap-1"
                                        ><Paperclip class="size-3.5" />{{
                                            cmd.produits?.length ?? 0
                                        }}
                                        Médicaments demandés</span
                                    >
                                    <span class="flex items-center gap-1"
                                        ><Clock class="size-3.5" />{{
                                            cmd.date
                                        }}</span
                                    >
                                </div>
                                <p
                                    class="mt-1 flex items-center gap-1 text-[12px] font-medium"
                                    :class="
                                        cmd.ordonnance_id
                                            ? 'text-primary'
                                            : 'text-gray-400'
                                    "
                                >
                                    <FileText class="size-3.5" />
                                    <span v-if="cmd.ordonnance_id"
                                        >Ordonnance à vérifier</span
                                    >
                                    <span v-else>Aucune ordonnance</span>
                                </p>
                            </div>
                            <component
                                :is="
                                    expandedCards.has(cmd.id)
                                        ? ChevronUp
                                        : ChevronDown
                                "
                                class="mt-1 size-5 shrink-0 text-gray-400"
                            />
                        </div>

                        <!-- Corps développé -->
                        <div
                            v-if="expandedCards.has(cmd.id)"
                            class="border-t border-gray-100 px-5 pb-5 pt-4 space-y-4 dark:border-border"
                        >
                            <!-- Box ordonnance -->
                            <div
                                v-if="cmd.ordonnance_id"
                                class="flex items-center justify-between rounded-xl border border-[#BFDBFE] bg-[#EFF6FF] px-4 py-3"
                            >
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex size-9 items-center justify-center rounded-full bg-[#459cd1]/10"
                                    >
                                        <FileText
                                            class="size-5 text-[#459cd1]"
                                        />
                                    </div>
                                    <div>
                                        <p
                                            class="text-[13px] font-semibold text-gray-800"
                                        >
                                            Ordonnance médicale jointe
                                        </p>
                                        <p
                                            class="text-[11px] text-[#F59E0B] font-medium"
                                        >
                                            À vérifier
                                        </p>
                                    </div>
                                </div>
                                <button
                                    class="flex items-center gap-1.5 rounded-lg bg-primary px-3 py-1.5 text-[11px] font-bold text-white shadow hover:bg-primary/90 transition-colors"
                                    @click.stop="openOrdonnance(cmd)"
                                >
                                    <Eye class="size-3.5" />Voir et vérifier
                                </button>
                            </div>

                            <!-- Tableau médicaments éditable -->
                            <div>
                                <p
                                    class="mb-2.5 flex items-center gap-1.5 text-[12px] font-semibold text-gray-500"
                                >
                                    <Paperclip class="size-3.5" />Médicaments
                                    demandés
                                </p>
                                <div
                                    class="overflow-x-auto rounded-xl border border-gray-100 dark:border-border"
                                >
                                    <table
                                        class="w-full min-w-[820px] text-[13px]"
                                    >
                                        <thead class="bg-gray-50 dark:bg-muted/40">
                                            <tr>
                                                <th
                                                    class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500"
                                                >
                                                    Nom Médicament
                                                </th>
                                                <th
                                                    class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500"
                                                >
                                                    Qté demandée
                                                </th>
                                                <th
                                                    class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500"
                                                >
                                                    Qté disponible
                                                </th>
                                                <th
                                                    class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500"
                                                >
                                                    Prix unitaire
                                                </th>
                                                <th
                                                    class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500"
                                                >
                                                    Total
                                                </th>
                                                <th
                                                    class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500"
                                                >
                                                    Disponibilité
                                                </th>
                                                <th
                                                    class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500"
                                                >
                                                    En vente libre
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-50">
                                            <tr
                                                v-for="p in cmd.produits"
                                                :key="p.id"
                                                class="transition-opacity"
                                                :class="
                                                    formLignes[cmd.id]?.[p.id]
                                                        ?.dispo === false
                                                        ? 'opacity-40'
                                                        : 'opacity-100'
                                                "
                                            >
                                                <!-- Nom -->
                                                <td class="px-4 py-2.5">
                                                    <span
                                                        class="inline-block rounded-md border border-gray-200 bg-white px-2.5 py-1 text-[13px] text-gray-800 dark:border-border dark:bg-muted/30 dark:text-foreground"
                                                    >
                                                        {{ p.designation }}
                                                    </span>
                                                </td>
                                                <td
                                                    class="px-3 py-2.5 text-[13px] font-medium tabular-nums text-gray-700"
                                                >
                                                    {{ p.pivot.quantite }}
                                                </td>
                                                <!-- Quantité disponible (saisie si dispo) -->
                                                <td class="px-3 py-2.5">
                                                    <div
                                                        class="flex w-16 flex-col gap-0.5"
                                                    >
                                                        <input
                                                            v-model="
                                                                formLignes[
                                                                    cmd.id
                                                                ][p.id].quantite
                                                            "
                                                            type="number"
                                                            min="1"
                                                            :max="
                                                                p.pivot.quantite
                                                            "
                                                            :disabled="
                                                                formLignes[
                                                                    cmd.id
                                                                ]?.[p.id]
                                                                    ?.dispo !==
                                                                true
                                                            "
                                                            class="w-full rounded-md border px-2 py-1 text-center text-[13px] transition-colors"
                                                            :class="
                                                                formLignes[
                                                                    cmd.id
                                                                ]?.[p.id]
                                                                    ?.dispo !==
                                                                true
                                                                    ? 'border-gray-100 bg-gray-50 text-gray-400 cursor-not-allowed'
                                                                    : qteInvalide(
                                                                            cmd.id,
                                                                            p,
                                                                        )
                                                                      ? 'border-red-400 bg-red-50 text-red-700 focus:outline-none focus:ring-1 focus:ring-red-400'
                                                                      : 'border-[#2563eb]/70 bg-[#eff6ff] text-[#1e3a8a] focus:border-[#1d4ed8] focus:outline-none focus:ring-2 focus:ring-[#3b82f6]/40'
                                                            "
                                                        />
                                                        <!-- Hint max -->
                                                        <span
                                                            v-if="
                                                                formLignes[
                                                                    cmd.id
                                                                ]?.[p.id]
                                                                    ?.dispo ===
                                                                true
                                                            "
                                                            class="w-full text-center text-[10px] leading-none"
                                                            :class="
                                                                qteInvalide(
                                                                    cmd.id,
                                                                    p,
                                                                )
                                                                    ? 'text-red-500 font-semibold'
                                                                    : 'text-gray-400'
                                                            "
                                                        >
                                                            max
                                                            {{
                                                                p.pivot.quantite
                                                            }}
                                                        </span>
                                                    </div>
                                                </td>
                                                <!-- Prix unitaire (éditable si dispo) -->
                                                <td class="px-3 py-2.5">
                                                    <div
                                                        class="flex items-center gap-1"
                                                    >
                                                        <input
                                                            v-model="
                                                                formLignes[
                                                                    cmd.id
                                                                ][p.id].prix
                                                            "
                                                            type="number"
                                                            min="0"
                                                            placeholder="Ex : 1000"
                                                            :disabled="
                                                                formLignes[
                                                                    cmd.id
                                                                ]?.[p.id]
                                                                    ?.dispo !==
                                                                true
                                                            "
                                                            class="w-24 rounded-md border px-2 py-1 text-[13px] transition-colors"
                                                            :class="
                                                                formLignes[
                                                                    cmd.id
                                                                ]?.[p.id]
                                                                    ?.dispo ===
                                                                true
                                                                    ? 'border-[#2563eb]/70 bg-[#eff6ff] text-[#1e3a8a] focus:border-[#1d4ed8] focus:outline-none focus:ring-2 focus:ring-[#3b82f6]/40'
                                                                    : 'border-gray-100 bg-gray-50 text-gray-400 cursor-not-allowed'
                                                            "
                                                        />
                                                        <span
                                                            class="text-[11px] text-gray-400 shrink-0"
                                                            >xaf</span
                                                        >
                                                    </div>
                                                </td>
                                                <!-- Total calculé -->
                                                <td class="px-3 py-2.5">
                                                    <div
                                                        class="flex items-center gap-1"
                                                    >
                                                        <span
                                                            class="text-[13px] font-semibold"
                                                            :class="
                                                                totalLigne(
                                                                    cmd.id,
                                                                    p,
                                                                )
                                                                    ? 'text-gray-900 dark:text-foreground'
                                                                    : 'text-gray-300'
                                                            "
                                                        >
                                                            {{
                                                                totalLigne(
                                                                    cmd.id,
                                                                    p,
                                                                ) || 'Ex : 1000'
                                                            }}
                                                        </span>
                                                        <span
                                                            class="text-[11px] text-gray-400 shrink-0"
                                                            >xaf</span
                                                        >
                                                    </div>
                                                </td>
                                                <!-- Disponibilité -->
                                                <td class="px-3 py-2.5">
                                                    <div
                                                        class="flex flex-col items-start gap-1.5"
                                                    >
                                                        <span
                                                            class="inline-flex w-fit rounded-full border px-2.5 py-0.5 text-[11px] font-semibold"
                                                            :class="
                                                                classesStatutDisponibiliteLigne(
                                                                    statutDispoForm(
                                                                        cmd.id,
                                                                        p.id,
                                                                    ),
                                                                )
                                                            "
                                                        >
                                                            {{
                                                                libelleStatutDisponibiliteLigne(
                                                                    statutDispoForm(
                                                                        cmd.id,
                                                                        p.id,
                                                                    ),
                                                                )
                                                            }}
                                                        </span>
                                                        <button
                                                            type="button"
                                                            class="relative inline-flex h-5 w-9 cursor-pointer items-center rounded-full transition-colors focus:outline-none"
                                                            :class="
                                                                formLignes[
                                                                    cmd.id
                                                                ][p.id]
                                                                    .dispo ===
                                                                true
                                                                    ? 'bg-[#22C55E]'
                                                                    : 'bg-gray-200'
                                                            "
                                                            :title="
                                                                formLignes[
                                                                    cmd.id
                                                                ][p.id]
                                                                    .dispo ===
                                                                null
                                                                    ? 'En attente — cliquer pour indiquer disponible ou indisponible'
                                                                    : undefined
                                                            "
                                                            @click="
                                                                toggleDispo(
                                                                    cmd.id,
                                                                    p.id,
                                                                )
                                                            "
                                                        >
                                                            <span
                                                                class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition-transform"
                                                                :class="
                                                                    formLignes[
                                                                        cmd.id
                                                                    ][p.id]
                                                                        .dispo ===
                                                                    true
                                                                        ? 'translate-x-4'
                                                                        : 'translate-x-0.5'
                                                                "
                                                            />
                                                        </button>
                                                    </div>
                                                </td>
                                                <!-- Toggle vente libre -->
                                                <td class="px-3 py-2.5">
                                                    <button
                                                        type="button"
                                                        class="relative inline-flex h-5 w-9 cursor-pointer items-center rounded-full transition-colors focus:outline-none"
                                                        :class="
                                                            formLignes[cmd.id][
                                                                p.id
                                                            ].venteLibre
                                                                ? 'bg-[#22C55E]'
                                                                : 'bg-gray-200'
                                                        "
                                                        @click="
                                                            formLignes[cmd.id][
                                                                p.id
                                                            ].venteLibre =
                                                                !formLignes[
                                                                    cmd.id
                                                                ][p.id]
                                                                    .venteLibre
                                                        "
                                                    >
                                                        <span
                                                            class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition-transform"
                                                            :class="
                                                                formLignes[
                                                                    cmd.id
                                                                ][p.id]
                                                                    .venteLibre
                                                                    ? 'translate-x-4'
                                                                    : 'translate-x-0.5'
                                                            "
                                                        />
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Total montant commande -->
                            <div class="flex items-baseline gap-2 px-1">
                                <span
                                    class="text-[13px] font-bold text-gray-700"
                                    >Total montant commande :</span
                                >
                                <span
                                    class="text-2xl font-bold text-gray-900 dark:text-foreground"
                                    >{{ totalCmd(cmd).toFixed(1) }}</span
                                >
                                <span class="text-[12px] text-gray-500"
                                    >xaf</span
                                >
                            </div>

                            <!-- Commentaire commande (back-office, lecture seule) -->
                            <div
                                class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-[13px] dark:border-border dark:bg-muted/30"
                            >
                                <p
                                    class="mb-1.5 text-[12px] font-bold uppercase tracking-wide text-gray-500"
                                >
                                    Commentaire (commande)
                                </p>
                                <p
                                    class="whitespace-pre-wrap text-gray-800"
                                    :class="
                                        cmd.commentaire?.trim()
                                            ? ''
                                            : 'text-gray-400'
                                    "
                                >
                                    {{
                                        cmd.commentaire?.trim() ||
                                        'Aucun commentaire du back-office.'
                                    }}
                                </p>
                            </div>

                            <!-- Commentaires pharmacien -->
                            <div class="space-y-1.5">
                                <label
                                    :for="`comment-pharma-${cmd.id}`"
                                    class="block text-[13px] font-bold tracking-wide text-[#1d4ed8]"
                                >
                                    Commentaires du pharmacien
                                </label>
                                <textarea
                                    :id="`comment-pharma-${cmd.id}`"
                                    v-model="formCommentaires[cmd.id]"
                                    placeholder="Informations utiles pour le back-office (facultatif)…"
                                    rows="3"
                                    class="w-full resize-none rounded-xl border border-[#93c5fd]/80 bg-white px-4 py-3 text-[13px] text-gray-800 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#3b82f6]/35 dark:border-border dark:bg-input dark:text-foreground dark:placeholder:text-muted-foreground"
                                />
                            </div>

                            <!-- Photos jointes (images) -->
                            <PharmaciePieceJointeSection
                                :commande-id="cmd.id"
                                :pieces="cmd.pieces_jointes ?? []"
                                :editable="peutAjouterPieceJointe(cmd)"
                            />

                            <div class="flex items-center justify-between">
                                <!-- Message d'erreur quantité -->
                                <p
                                    v-if="hasQteError(cmd)"
                                    class="flex items-center gap-1.5 text-[12px] font-semibold text-red-500"
                                >
                                    <AlertCircle class="size-4 shrink-0" />
                                    La quantité confirmée ne peut pas dépasser
                                    la quantité demandée.
                                </p>
                                <div v-else class="flex-1" />
                                <div class="flex flex-col items-end gap-1">
                                    <p
                                        v-if="hasUnresolvedDispo(cmd)"
                                        class="text-[11px] font-medium text-amber-700"
                                    >
                                        Indiquez la disponibilité de chaque
                                        médicament (y compris parapharmacie)
                                        avant envoi.
                                    </p>
                                    <p
                                        v-else-if="hasPrixError(cmd)"
                                        class="text-[11px] font-medium text-red-500"
                                    >
                                        Saisissez le prix de tous les
                                        médicaments disponibles
                                    </p>
                                    <button
                                        type="button"
                                        class="rounded-xl px-6 py-2.5 text-[13px] font-bold shadow transition-colors"
                                        :class="
                                            peutEnvoyerDisponibilite(cmd)
                                                ? 'bg-[#459cd1] text-white hover:bg-[#3a87b8]'
                                                : 'cursor-not-allowed bg-gray-300 text-gray-600'
                                        "
                                        :disabled="
                                            !peutEnvoyerDisponibilite(cmd)
                                        "
                                        @click="envoyer(cmd)"
                                    >
                                        Envoyer
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p
                        v-if="!commandes.data?.length"
                        class="rounded-2xl bg-white/20 py-14 text-center text-[14px] font-medium text-white"
                    >
                        Aucune nouvelle commande.
                    </p>
                </template>

                <!-- ═══ ONGLET : En attente ═══ -->
                <template v-else-if="onglet === 'en_attente'">
                    <!-- Bandeau info violet -->
                    <div
                        class="rounded-xl border border-[#C7D2FE] bg-[#EEF2FF] px-4 py-3"
                    >
                        <p
                            class="flex items-center gap-2 text-[13px] font-bold text-[#3730A3]"
                        >
                            <AlertCircle class="size-4 shrink-0" />
                            Commandes en attente de confirmation
                        </p>
                        <p class="mt-0.5 text-[12px] text-[#4338CA]">
                            Ces commandes ont été envoyées. En attente de
                            validation par bengadock avant préparation.
                        </p>
                    </div>

                    <div
                        v-for="cmd in commandes.data"
                        :key="cmd.id"
                        :class="pharmacyOrderCardClass"
                    >
                        <div
                            class="flex cursor-pointer items-start justify-between px-5 py-4"
                            @click="toggleCard(cmd)"
                        >
                            <div class="flex-1 min-w-0">
                                <div
                                    class="mb-1.5 flex flex-wrap items-center gap-2"
                                >
                                    <span
                                        class="text-[15px] font-extrabold text-[#4338CA]"
                                    >
                                        <template v-if="nomCommandeVisible(cmd)">
                                            <span class="font-sans">{{
                                                clientNomAvecCivilite(cmd.client!)
                                            }}
                                                -
                                            </span>
                                        </template>
                                        <span class="font-mono">{{
                                            cmd.numero
                                        }}</span>
                                    </span>
                                    <span
                                        v-if="
                                            cmd.status_pharmacie ===
                                            'indisponible'
                                        "
                                        class="rounded-full bg-[#FEE2E2] px-2.5 py-0.5 text-[10px] font-bold text-[#991B1B]"
                                    >
                                        Indisponible ⚠
                                    </span>
                                    <span
                                        v-else
                                        class="rounded-full bg-[#EEF2FF] px-2.5 py-0.5 text-[10px] font-bold text-[#3730A3]"
                                    >
                                        En attente de confirmation ⏳
                                    </span>
                                    <span
                                        v-if="cmd.ordonnance_id"
                                        class="flex items-center gap-1 rounded-full bg-[#E0F2FE] px-2.5 py-0.5 text-[10px] font-bold text-[#0369A1]"
                                    >
                                        <FileText class="size-3" />Ordonnance
                                    </span>
                                </div>
                                <div
                                    class="flex flex-wrap items-center gap-4 text-[12px] text-gray-500"
                                >
                                    <span class="flex items-center gap-1"
                                        ><Paperclip class="size-3.5" />{{
                                            cmd.produits?.length ?? 0
                                        }}
                                        Médicaments</span
                                    >
                                    <span class="flex items-center gap-1"
                                        ><Clock class="size-3.5" />{{
                                            cmd.date
                                        }}</span
                                    >
                                </div>
                                <p
                                    class="mt-1 flex items-center gap-1 text-[12px] font-medium"
                                    :class="
                                        cmd.ordonnance_id
                                            ? 'text-[#22C55E]'
                                            : 'text-gray-400'
                                    "
                                >
                                    <FileText class="size-3.5" />
                                    <span v-if="cmd.ordonnance_id"
                                        >Ordonnance soumise</span
                                    >
                                    <span v-else>Aucune ordonnance</span>
                                </p>
                            </div>
                            <component
                                :is="
                                    expandedCards.has(cmd.id)
                                        ? ChevronUp
                                        : ChevronDown
                                "
                                class="mt-1 size-5 shrink-0 text-gray-400"
                            />
                        </div>

                        <!-- Corps développé (lecture seule) -->
                        <div
                            v-if="expandedCards.has(cmd.id)"
                            class="border-t border-gray-100 px-5 pb-5 pt-4 space-y-4 dark:border-border"
                        >
                            <div
                                v-if="cmd.ordonnance_id"
                                class="flex items-center justify-between rounded-xl border border-[#BFDBFE] bg-[#EFF6FF] px-4 py-3"
                            >
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex size-9 items-center justify-center rounded-full bg-[#459cd1]/10"
                                    >
                                        <FileText
                                            class="size-5 text-[#459cd1]"
                                        />
                                    </div>
                                    <div>
                                        <p
                                            class="text-[13px] font-semibold text-gray-800"
                                        >
                                            Ordonnance médicale jointe
                                        </p>
                                        <p
                                            class="text-[11px] text-[#22C55E] font-medium"
                                        >
                                            Soumise
                                        </p>
                                    </div>
                                </div>
                                <button
                                    class="flex items-center gap-1.5 rounded-lg border border-[#459cd1] px-3 py-1.5 text-[11px] font-bold text-[#459cd1] hover:bg-[#459cd1]/10 transition-colors"
                                    @click.stop="openOrdonnance(cmd)"
                                >
                                    <Eye class="size-3.5" />Voir
                                </button>
                            </div>
                            <div>
                                <p
                                    class="mb-2.5 flex items-center gap-1.5 text-[12px] font-semibold text-gray-500"
                                >
                                    <Paperclip class="size-3.5" />Médicaments
                                    demandés
                                </p>
                                <div
                                    class="overflow-x-auto rounded-xl border border-gray-100 dark:border-border"
                                >
                                    <table
                                        class="w-full min-w-[820px] text-[13px]"
                                    >
                                        <thead class="bg-gray-50 dark:bg-muted/40">
                                            <tr>
                                                <th
                                                    class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500"
                                                >
                                                    Nom Médicament
                                                </th>
                                                <th
                                                    class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500"
                                                >
                                                    Qté demandée
                                                </th>
                                                <th
                                                    class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500"
                                                >
                                                    Qté disponible
                                                </th>
                                                <th
                                                    class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500"
                                                >
                                                    Prix unitaire
                                                </th>
                                                <th
                                                    class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500"
                                                >
                                                    Total
                                                </th>
                                                <th
                                                    class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500"
                                                >
                                                    Disponibilité
                                                </th>
                                                <th
                                                    class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500"
                                                >
                                                    En vente libre
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-50">
                                            <tr
                                                v-for="p in cmd.produits"
                                                :key="p.id"
                                            >
                                                <td class="px-4 py-2.5">
                                                    <span
                                                        class="inline-block rounded-md border border-gray-200 bg-white px-2.5 py-1 text-[13px] dark:border-border dark:bg-muted/30 dark:text-foreground"
                                                        :class="
                                                            p.pivot.status ===
                                                            'indisponible'
                                                                ? 'text-red-400 line-through'
                                                                : 'text-gray-800'
                                                        "
                                                    >
                                                        {{ p.designation }}
                                                    </span>
                                                </td>
                                                <td
                                                    class="px-3 py-2.5 text-[13px] font-medium tabular-nums text-gray-600"
                                                >
                                                    {{ p.pivot.quantite }}
                                                </td>
                                                <td
                                                    class="px-3 py-2.5 text-[13px] font-medium tabular-nums text-gray-800"
                                                >
                                                    {{
                                                        qteDisponibleAffichee(p)
                                                    }}
                                                </td>
                                                <td class="px-3 py-2.5">
                                                    <div
                                                        class="flex items-center gap-1"
                                                    >
                                                        <span
                                                            class="text-[13px] font-semibold text-gray-900 dark:text-foreground"
                                                            >{{
                                                                Number(
                                                                    p.pivot
                                                                        .prix_unitaire,
                                                                ).toLocaleString(
                                                                    'fr-FR',
                                                                )
                                                            }}</span
                                                        >
                                                        <span
                                                            class="text-[11px] text-gray-400"
                                                            >xaf</span
                                                        >
                                                    </div>
                                                </td>
                                                <td class="px-3 py-2.5">
                                                    <div
                                                        class="flex items-center gap-1"
                                                    >
                                                        <span
                                                            class="text-[13px] font-bold text-gray-900 dark:text-foreground"
                                                            >{{
                                                                Number(
                                                                    p.pivot
                                                                        .prix_unitaire *
                                                                        qteDisponibleNombre(
                                                                            p,
                                                                        ),
                                                                ).toLocaleString(
                                                                    'fr-FR',
                                                                )
                                                            }}</span
                                                        >
                                                        <span
                                                            class="text-[11px] text-gray-400"
                                                            >xaf</span
                                                        >
                                                    </div>
                                                </td>
                                                <td class="px-3 py-2.5">
                                                    <span
                                                        class="inline-flex rounded-full border px-2.5 py-0.5 text-[11px] font-semibold"
                                                        :class="
                                                            classesStatutDisponibiliteLigne(
                                                                p.pivot.status,
                                                            )
                                                        "
                                                    >
                                                        {{
                                                            libelleStatutDisponibiliteLigne(
                                                                p.pivot.status,
                                                            )
                                                        }}
                                                    </span>
                                                </td>
                                                <td class="px-3 py-2.5">
                                                    <span
                                                        class="relative inline-flex h-5 w-9 items-center rounded-full"
                                                        :class="
                                                            estVenteLibre(p)
                                                                ? 'bg-[#22C55E]'
                                                                : 'bg-gray-200'
                                                        "
                                                    >
                                                        <span
                                                            class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition-transform"
                                                            :class="
                                                                estVenteLibre(p)
                                                                    ? 'translate-x-4'
                                                                    : 'translate-x-0.5'
                                                            "
                                                        />
                                                    </span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p
                        v-if="!commandes.data?.length"
                        class="rounded-2xl bg-white/20 py-14 text-center text-[14px] font-medium text-white"
                    >
                        Aucune commande en attente de confirmation.
                    </p>
                </template>

                <!-- ═══ ONGLET : Validées - À préparer ═══ -->
                <template v-else-if="onglet === 'a_preparer'">
                    <div
                        class="rounded-xl border border-[#FCD34D] bg-[#FFFBEB] px-4 py-3"
                    >
                        <p class="text-[13px] font-bold text-[#92400E]">
                            Commandes validées par les clients
                        </p>
                        <p class="mt-0.5 text-[12px] text-[#78350F]">
                            Ces commandes ont été confirmées par les patients.
                            Veuillez les préparer pour la livraison ou le
                            retrait.
                        </p>
                    </div>

                    <div
                        v-for="cmd in commandes.data"
                        :key="cmd.id"
                        :class="pharmacyOrderCardClass"
                    >
                        <div class="flex items-start justify-between px-5 py-4">
                            <div
                                class="flex-1 min-w-0 cursor-pointer"
                                @click="toggleCard(cmd)"
                            >
                                <div
                                    class="mb-1.5 flex flex-wrap items-center gap-2"
                                >
                                    <span
                                        class="text-[15px] font-extrabold text-[#2563EB]"
                                    >
                                        <template v-if="nomCommandeVisible(cmd)">
                                            <span class="font-sans">{{
                                                clientNomAvecCivilite(cmd.client!)
                                            }}
                                                -
                                            </span>
                                        </template>
                                        <span class="font-mono">{{
                                            cmd.numero
                                        }}</span>
                                    </span>
                                    <span
                                        class="rounded-full bg-[#FEF3C7] px-2.5 py-0.5 text-[10px] font-bold text-[#92400E]"
                                        >Commande Validée À préparer 🔄</span
                                    >
                                    <span
                                        v-if="cmd.ordonnance_id"
                                        class="flex items-center gap-1 rounded-full bg-[#E0F2FE] px-2.5 py-0.5 text-[10px] font-bold text-[#0369A1]"
                                    >
                                        <FileText class="size-3" />Ordonnance
                                    </span>
                                </div>
                                <div
                                    class="flex flex-wrap items-center gap-4 text-[12px] text-gray-500"
                                >
                                    <span class="flex items-center gap-1"
                                        ><Paperclip class="size-3.5" />{{
                                            cmd.produits?.length ?? 0
                                        }}
                                        Médicaments demandés</span
                                    >
                                    <span class="flex items-center gap-1"
                                        ><Clock class="size-3.5" />{{
                                            cmd.date
                                        }}</span
                                    >
                                </div>
                                <p
                                    class="mt-1 flex items-center gap-1 text-[12px] font-medium"
                                    :class="
                                        cmd.ordonnance_id
                                            ? 'text-[#22C55E]'
                                            : 'text-gray-400'
                                    "
                                >
                                    <CheckCircle2 class="size-3.5" />
                                    <span v-if="cmd.ordonnance_id"
                                        >Ordonnance vérifiée</span
                                    >
                                    <span v-else>Aucune ordonnance</span>
                                </p>
                            </div>
                            <div class="ml-3 flex shrink-0 items-center gap-2">
                                <button
                                    class="flex items-center gap-2 rounded-xl bg-[#F59E0B] px-4 py-2 text-[12px] font-bold text-white shadow hover:bg-[#D97706] transition-colors whitespace-nowrap"
                                    @click.stop="askValiderAchat(cmd)"
                                >
                                    <ShoppingCart class="size-4" />
                                    Valider l'achat et la remise au livreur
                                </button>
                                <button @click="toggleCard(cmd)">
                                    <component
                                        :is="
                                            expandedCards.has(cmd.id)
                                                ? ChevronUp
                                                : ChevronDown
                                        "
                                        class="size-5 text-gray-400"
                                    />
                                </button>
                            </div>
                        </div>

                        <!-- Corps développé (lecture seule) -->
                        <div
                            v-if="expandedCards.has(cmd.id)"
                            class="border-t border-gray-100 px-5 pb-5 pt-4 space-y-4 dark:border-border"
                        >
                            <div
                                v-if="cmd.ordonnance_id"
                                class="flex items-center justify-between rounded-xl border border-[#BFDBFE] bg-[#EFF6FF] px-4 py-3"
                            >
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex size-9 items-center justify-center rounded-full bg-[#459cd1]/10"
                                    >
                                        <FileText
                                            class="size-5 text-[#459cd1]"
                                        />
                                    </div>
                                    <div>
                                        <p
                                            class="text-[13px] font-semibold text-gray-800"
                                        >
                                            Ordonnance médicale jointe
                                        </p>
                                        <p
                                            class="text-[11px] text-[#22C55E] font-medium"
                                        >
                                            Vérifier
                                        </p>
                                    </div>
                                </div>
                                <button
                                    class="flex items-center gap-1.5 rounded-lg border border-[#459cd1] px-3 py-1.5 text-[11px] font-bold text-[#459cd1] hover:bg-[#459cd1]/10 transition-colors"
                                    @click.stop="openOrdonnance(cmd)"
                                >
                                    <Eye class="size-3.5" />Voir
                                </button>
                            </div>
                            <div>
                                <p
                                    class="mb-2.5 flex items-center gap-1.5 text-[12px] font-semibold text-gray-500"
                                >
                                    <Paperclip class="size-3.5" />Médicaments
                                    demandés
                                </p>
                                <div
                                    class="overflow-x-auto rounded-xl border border-gray-100 dark:border-border"
                                >
                                    <table
                                        class="w-full min-w-[820px] text-[13px]"
                                    >
                                        <thead class="bg-gray-50 dark:bg-muted/40">
                                            <tr>
                                                <th
                                                    class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500"
                                                >
                                                    Nom Médicament
                                                </th>
                                                <th
                                                    class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500"
                                                >
                                                    Qté demandée
                                                </th>
                                                <th
                                                    class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500"
                                                >
                                                    Qté disponible
                                                </th>
                                                <th
                                                    class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500"
                                                >
                                                    Prix unitaire
                                                </th>
                                                <th
                                                    class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500"
                                                >
                                                    Total
                                                </th>
                                                <th
                                                    class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500"
                                                >
                                                    Disponibilité
                                                </th>
                                                <th
                                                    class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500"
                                                >
                                                    En vente libre
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-50">
                                            <tr
                                                v-for="p in cmd.produits"
                                                :key="p.id"
                                            >
                                                <td class="px-4 py-2.5">
                                                    <span
                                                        class="inline-block rounded-md border border-gray-200 bg-white px-2.5 py-1 text-[13px] text-gray-800 dark:border-border dark:bg-muted/30 dark:text-foreground"
                                                        >{{
                                                            p.designation
                                                        }}</span
                                                    >
                                                </td>
                                                <td
                                                    class="px-3 py-2.5 text-[13px] font-medium tabular-nums text-gray-700"
                                                >
                                                    {{ p.pivot.quantite }}
                                                </td>
                                                <td
                                                    class="px-3 py-2.5 text-[13px] font-medium tabular-nums text-gray-800"
                                                >
                                                    {{
                                                        qteDisponibleAffichee(p)
                                                    }}
                                                </td>
                                                <td class="px-3 py-2.5">
                                                    <div
                                                        class="flex items-center gap-1"
                                                    >
                                                        <span
                                                            class="text-[13px] font-semibold text-gray-900 dark:text-foreground"
                                                            >{{
                                                                Number(
                                                                    p.pivot
                                                                        .prix_unitaire,
                                                                ).toLocaleString(
                                                                    'fr-FR',
                                                                )
                                                            }}</span
                                                        >
                                                        <span
                                                            class="text-[11px] text-gray-400"
                                                            >xaf</span
                                                        >
                                                    </div>
                                                </td>
                                                <td class="px-3 py-2.5">
                                                    <div
                                                        class="flex items-center gap-1"
                                                    >
                                                        <span
                                                            class="text-[13px] font-bold text-gray-900 dark:text-foreground"
                                                            >{{
                                                                Number(
                                                                    p.pivot
                                                                        .prix_unitaire *
                                                                        qteDisponibleNombre(
                                                                            p,
                                                                        ),
                                                                ).toLocaleString(
                                                                    'fr-FR',
                                                                )
                                                            }}</span
                                                        >
                                                        <span
                                                            class="text-[11px] text-gray-400"
                                                            >xaf</span
                                                        >
                                                    </div>
                                                </td>
                                                <td class="px-3 py-2.5">
                                                    <span
                                                        class="inline-flex rounded-full border px-2.5 py-0.5 text-[11px] font-semibold"
                                                        :class="
                                                            classesStatutDisponibiliteLigne(
                                                                p.pivot.status,
                                                            )
                                                        "
                                                    >
                                                        {{
                                                            libelleStatutDisponibiliteLigne(
                                                                p.pivot.status,
                                                            )
                                                        }}
                                                    </span>
                                                </td>
                                                <td class="px-3 py-2.5">
                                                    <span
                                                        class="relative inline-flex h-5 w-9 items-center rounded-full"
                                                        :class="
                                                            estVenteLibre(p)
                                                                ? 'bg-[#22C55E]'
                                                                : 'bg-gray-200'
                                                        "
                                                    >
                                                        <span
                                                            class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow"
                                                            :class="
                                                                estVenteLibre(p)
                                                                    ? 'translate-x-4'
                                                                    : 'translate-x-0.5'
                                                            "
                                                        />
                                                    </span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Total montant commande -->
                            <div class="flex items-baseline gap-2 px-1">
                                <span
                                    class="text-[13px] font-bold text-gray-700"
                                    >Total montant commande :</span
                                >
                                <span
                                    class="text-2xl font-bold text-gray-900 dark:text-foreground"
                                    >{{ totalCommandeValidee(cmd).toFixed(1) }}</span
                                >
                                <span class="text-[12px] text-gray-500"
                                    >xaf</span
                                >
                            </div>

                            <!-- Commentaires commande / pharmacien (lecture seule) -->
                            <div class="space-y-2 text-[13px]">
                                <div
                                    class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3"
                                >
                                    <p
                                        class="mb-1 text-[11px] font-bold uppercase tracking-wide text-gray-500"
                                    >
                                        Commentaire (commande)
                                    </p>
                                    <p
                                        class="whitespace-pre-wrap"
                                        :class="
                                            cmd.commentaire?.trim()
                                                ? 'text-gray-700'
                                                : 'text-gray-400'
                                        "
                                    >
                                        {{
                                            cmd.commentaire?.trim() ||
                                            'Aucun.'
                                        }}
                                    </p>
                                </div>
                                <div
                                    v-if="(cmd.commentaire_pharmacie ?? '').trim()"
                                    class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-gray-700 dark:border-border dark:bg-muted/30 dark:text-foreground"
                                >
                                    <p
                                        class="mb-1 text-[11px] font-bold uppercase tracking-wide text-gray-500"
                                    >
                                        Commentaires du pharmacien
                                    </p>
                                    <p class="whitespace-pre-wrap">
                                        {{ cmd.commentaire_pharmacie }}
                                    </p>
                                </div>
                                <PharmaciePieceJointeSection
                                    :commande-id="cmd.id"
                                    :pieces="cmd.pieces_jointes ?? []"
                                />
                            </div>
                        </div>
                    </div>

                    <p
                        v-if="!commandes.data?.length"
                        class="rounded-2xl bg-white/20 py-14 text-center text-[14px] font-medium text-white"
                    >
                        Aucune commande à préparer.
                    </p>
                </template>

                <!-- ═══ ONGLET : Retirées ═══ -->
                <template v-else-if="onglet === 'livrees'">
                    <div
                        v-for="cmd in commandes.data"
                        :key="cmd.id"
                        :class="pharmacyOrderCardClass"
                    >
                        <!-- En-tête cliquable -->
                        <div
                            class="flex cursor-pointer items-start justify-between px-5 py-4"
                            @click="toggleCard(cmd)"
                        >
                            <div class="flex-1 min-w-0">
                                <div
                                    class="mb-1.5 flex flex-wrap items-center gap-2"
                                >
                                    <span
                                        class="text-[15px] font-extrabold text-gray-700"
                                    >
                                        <template v-if="nomCommandeVisible(cmd)">
                                            <span class="font-sans">{{
                                                clientNomAvecCivilite(cmd.client!)
                                            }}
                                                -
                                            </span>
                                        </template>
                                        <span class="font-mono">{{
                                            cmd.numero
                                        }}</span>
                                    </span>
                                    <span
                                        class="rounded-full bg-[#DCFCE7] px-2.5 py-0.5 text-[10px] font-bold text-[#15803D]"
                                        >Retirée ✓</span
                                    >
                                </div>
                                <div
                                    class="flex flex-wrap items-center gap-4 text-[12px] text-gray-500"
                                >
                                    <span class="flex items-center gap-1"
                                        ><Paperclip class="size-3.5" />{{
                                            cmd.produits?.length ?? 0
                                        }}
                                        Médicaments</span
                                    >
                                    <span class="flex items-center gap-1"
                                        ><CheckCircle2
                                            class="size-3.5 text-[#22C55E]"
                                        />{{ cmd.date }}</span
                                    >
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span
                                    class="rounded-lg bg-[#DCFCE7] px-3 py-1 text-[11px] font-bold text-[#15803D]"
                                    >Retirée</span
                                >
                                <component
                                    :is="
                                        expandedCards.has(cmd.id)
                                            ? ChevronUp
                                            : ChevronDown
                                    "
                                    class="size-5 text-gray-400"
                                />
                            </div>
                        </div>

                        <!-- Corps développé (lecture seule) -->
                        <div
                            v-if="expandedCards.has(cmd.id)"
                            class="border-t border-gray-100 px-5 pb-5 pt-4 space-y-4 dark:border-border"
                        >
                            <!-- Ordonnance -->
                            <div
                                v-if="cmd.ordonnance_id"
                                class="flex items-center justify-between rounded-xl border border-[#BFDBFE] bg-[#EFF6FF] px-4 py-3"
                            >
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex size-9 items-center justify-center rounded-full bg-[#459cd1]/10"
                                    >
                                        <FileText
                                            class="size-5 text-[#459cd1]"
                                        />
                                    </div>
                                    <div>
                                        <p
                                            class="text-[13px] font-semibold text-gray-800"
                                        >
                                            Ordonnance médicale jointe
                                        </p>
                                        <p
                                            class="text-[11px] text-[#22C55E] font-medium"
                                        >
                                            Vérifier
                                        </p>
                                    </div>
                                </div>
                                <button
                                    class="flex items-center gap-1.5 rounded-lg border border-[#459cd1] px-3 py-1.5 text-[11px] font-bold text-[#459cd1] hover:bg-[#459cd1]/10 transition-colors"
                                    @click.stop="openOrdonnance(cmd)"
                                >
                                    <Eye class="size-3.5" />Voir
                                </button>
                            </div>

                            <!-- Tableau médicaments -->
                            <div>
                                <p
                                    class="mb-2.5 flex items-center gap-1.5 text-[12px] font-semibold text-gray-500"
                                >
                                    <Paperclip class="size-3.5" />Médicaments
                                    demandés
                                </p>
                                <div
                                    class="overflow-x-auto rounded-xl border border-gray-100 dark:border-border"
                                >
                                    <table
                                        class="w-full min-w-[820px] text-[13px]"
                                    >
                                        <thead class="bg-gray-50 dark:bg-muted/40">
                                            <tr>
                                                <th
                                                    class="px-4 py-2.5 text-left text-[11px] font-semibold text-gray-500"
                                                >
                                                    Nom Médicament
                                                </th>
                                                <th
                                                    class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500"
                                                >
                                                    Qté demandée
                                                </th>
                                                <th
                                                    class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500"
                                                >
                                                    Qté disponible
                                                </th>
                                                <th
                                                    class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500"
                                                >
                                                    Prix unitaire
                                                </th>
                                                <th
                                                    class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500"
                                                >
                                                    Total
                                                </th>
                                                <th
                                                    class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500"
                                                >
                                                    Disponibilité
                                                </th>
                                                <th
                                                    class="px-3 py-2.5 text-left text-[11px] font-semibold text-gray-500"
                                                >
                                                    En vente libre
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-50">
                                            <tr
                                                v-for="p in cmd.produits"
                                                :key="p.id"
                                            >
                                                <td class="px-4 py-2.5">
                                                    <span
                                                        class="inline-block rounded-md border border-gray-200 bg-white px-2.5 py-1 text-[13px] dark:border-border dark:bg-muted/30 dark:text-foreground"
                                                        :class="
                                                            p.pivot.status ===
                                                            'indisponible'
                                                                ? 'text-red-400 line-through'
                                                                : 'text-gray-800'
                                                        "
                                                    >
                                                        {{ p.designation }}
                                                    </span>
                                                </td>
                                                <td
                                                    class="px-3 py-2.5 text-[13px] font-medium tabular-nums text-gray-600"
                                                >
                                                    {{ p.pivot.quantite }}
                                                </td>
                                                <td
                                                    class="px-3 py-2.5 text-[13px] font-medium tabular-nums text-gray-800"
                                                >
                                                    {{
                                                        qteDisponibleAffichee(p)
                                                    }}
                                                </td>
                                                <td class="px-3 py-2.5">
                                                    <div
                                                        class="flex items-center gap-1"
                                                    >
                                                        <span
                                                            class="text-[13px] font-semibold text-gray-900 dark:text-foreground"
                                                            >{{
                                                                Number(
                                                                    p.pivot
                                                                        .prix_unitaire,
                                                                ).toLocaleString(
                                                                    'fr-FR',
                                                                )
                                                            }}</span
                                                        >
                                                        <span
                                                            class="text-[11px] text-gray-400"
                                                            >xaf</span
                                                        >
                                                    </div>
                                                </td>
                                                <td class="px-3 py-2.5">
                                                    <div
                                                        class="flex items-center gap-1"
                                                    >
                                                        <span
                                                            class="text-[13px] font-bold text-gray-900 dark:text-foreground"
                                                            >{{
                                                                Number(
                                                                    p.pivot
                                                                        .prix_unitaire *
                                                                        qteDisponibleNombre(
                                                                            p,
                                                                        ),
                                                                ).toLocaleString(
                                                                    'fr-FR',
                                                                )
                                                            }}</span
                                                        >
                                                        <span
                                                            class="text-[11px] text-gray-400"
                                                            >xaf</span
                                                        >
                                                    </div>
                                                </td>
                                                <td class="px-3 py-2.5">
                                                    <span
                                                        class="inline-flex rounded-full border px-2.5 py-0.5 text-[11px] font-semibold"
                                                        :class="
                                                            classesStatutDisponibiliteLigne(
                                                                p.pivot.status,
                                                            )
                                                        "
                                                    >
                                                        {{
                                                            libelleStatutDisponibiliteLigne(
                                                                p.pivot.status,
                                                            )
                                                        }}
                                                    </span>
                                                </td>
                                                <td class="px-3 py-2.5">
                                                    <span
                                                        class="relative inline-flex h-5 w-9 items-center rounded-full"
                                                        :class="
                                                            estVenteLibre(p)
                                                                ? 'bg-[#22C55E]'
                                                                : 'bg-gray-200'
                                                        "
                                                    >
                                                        <span
                                                            class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow"
                                                            :class="
                                                                estVenteLibre(p)
                                                                    ? 'translate-x-4'
                                                                    : 'translate-x-0.5'
                                                            "
                                                        />
                                                    </span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Total montant commande -->
                            <div class="flex items-baseline gap-2 px-1">
                                <span
                                    class="text-[13px] font-bold text-gray-700"
                                    >Total montant commande :</span
                                >
                                <span
                                    class="text-2xl font-bold text-gray-900 dark:text-foreground"
                                    >{{ totalCommandeValidee(cmd).toFixed(1) }}</span
                                >
                                <span class="text-[12px] text-gray-500"
                                    >xaf</span
                                >
                            </div>

                            <!-- Commentaires commande / pharmacien (lecture seule) -->
                            <div class="space-y-2 text-[13px]">
                                <div
                                    class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3"
                                >
                                    <p
                                        class="mb-1 text-[11px] font-bold uppercase tracking-wide text-gray-500"
                                    >
                                        Commentaire (commande)
                                    </p>
                                    <p
                                        class="whitespace-pre-wrap"
                                        :class="
                                            cmd.commentaire?.trim()
                                                ? 'text-gray-700'
                                                : 'text-gray-400'
                                        "
                                    >
                                        {{
                                            cmd.commentaire?.trim() ||
                                            'Aucun.'
                                        }}
                                    </p>
                                </div>
                                <div
                                    v-if="(cmd.commentaire_pharmacie ?? '').trim()"
                                    class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-gray-700 dark:border-border dark:bg-muted/30 dark:text-foreground"
                                >
                                    <p
                                        class="mb-1 text-[11px] font-bold uppercase tracking-wide text-gray-500"
                                    >
                                        Commentaires du pharmacien
                                    </p>
                                    <p class="whitespace-pre-wrap">
                                        {{ cmd.commentaire_pharmacie }}
                                    </p>
                                </div>
                                <PharmaciePieceJointeSection
                                    :commande-id="cmd.id"
                                    :pieces="cmd.pieces_jointes ?? []"
                                />
                            </div>
                        </div>
                    </div>

                    <p
                        v-if="!commandes.data?.length"
                        class="rounded-2xl bg-white/20 py-14 text-center text-[14px] font-medium text-white"
                    >
                        Aucune commande retirée.
                    </p>
                </template>
            </div>

            <div
                v-if="(commandes.links?.length ?? 0) > 3"
                :class="[modulePaginationWrapperClass, 'mt-2']"
            >
                <ModulePagination
                    :links="commandes.links"
                    :from="commandes.from"
                    :to="commandes.to"
                    :total="commandes.total"
                />
            </div>
        </div>

        <DokPharmaValiderRetraitModal
            :open="confirmModal.open"
            :numero="confirmModal.cmd?.numero"
            @cancel="annulerConfirm"
            @confirm="confirmerAchat"
        />

        <DokPharmaOrdonnanceViewerModal
            :open="ordModal.open"
            :url="ordModal.url"
            :is-pdf="ordModal.isPdf"
            :numero="ordModal.numero"
            @close="closeOrdonnance"
        />

        <FlashToastHost />
    </PharmacyLayout>
</template>
