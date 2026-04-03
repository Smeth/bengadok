<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronDown,
    ChevronUp,
    Paperclip,
    Clock,
    FileText,
    ShoppingCart,
    ZoomIn,
    ZoomOut,
    Download,
    RefreshCw,
    X,
    CheckCircle2,
    ShieldCheck,
    Eye,
    AlertCircle,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { usePolling } from '@/composables/usePolling';
import PharmacyLayout from '@/layouts/PharmacyLayout.vue';

usePolling();

type Pivot = {
    quantite: number;
    prix_unitaire: number;
    status: string;
    quantite_confirmee: number | null;
};
type Produit = { id: number; designation: string; pivot: Pivot };

/** Quantité servie / confirmée (0 si ligne indisponible). */
function qteDisponibleNombre(p: Produit): number {
    if (p.pivot.status === 'indisponible') return 0;
    const c = p.pivot.quantite_confirmee;
    if (c !== null && c !== undefined) return c;
    return p.pivot.quantite;
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
    client: { nom: string; prenom: string } | null;
    produits: Produit[];
    ordonnance_id?: number | null;
    ordonnance_url?: string | null;
    commentaire?: string | null;
    prix_total?: number | null;
};
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
}>();

function changeOnglet(o: string) {
    /* preserveState désactivé : après validation POST, garder l’ancien état local
     * (cartes ouvertes, formulaires) provoquait des incohérences et, avec le scroll,
     * des zones pouvaient sembler « mortes » jusqu’au rechargement. */
    router.get(
        '/dok-pharma/commandes',
        { onglet: o },
        { preserveScroll: true },
    );
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
type LigneForm = { prix: string; quantite: string; dispo: boolean };
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
        map[p.id] = {
            prix:
                p.pivot.prix_unitaire > 0 ? String(p.pivot.prix_unitaire) : '',
            quantite: String(
                p.pivot.quantite_confirmee ?? p.pivot.quantite ?? qDem,
            ),
            dispo: p.pivot.status !== 'indisponible',
        };
    });
    if (formCommentaires.value[cmd.id] === undefined) {
        formCommentaires.value[cmd.id] = '';
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
        if (!l?.dispo) return sum;
        const prix = parseNombreFr(l.prix);
        const qte = qteConfirmeeParsee(l);
        if (!Number.isFinite(prix) || !Number.isFinite(qte)) return sum;
        return sum + prix * qte;
    }, 0);
}

function totalLigne(cmdId: number, produit: Produit): string {
    const ligne = formLignes.value[cmdId]?.[produit.id];
    if (!ligne?.dispo) return '';
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
    if (!ligne?.dispo) return false;
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
        if (!ligne?.dispo) return false;
        const px = parseNombreFr(ligne.prix);
        return !Number.isFinite(px) || px <= 0;
    });
}

/** État du bouton Envoyer (même logique que l’action, avec dépendance explicite à la révision). */
function peutEnvoyerDisponibilite(cmd: Commande): boolean {
    void formLignesRevision.value;
    return !hasQteError(cmd) && !hasPrixError(cmd);
}

function envoyer(cmd: Commande) {
    if (!peutEnvoyerDisponibilite(cmd)) return;
    const lignes = cmd.produits.map((p) => {
        const ligne = formLignes.value[cmd.id]?.[p.id];
        const qte = qteConfirmeeParsee(ligne);
        const pxBrut = ligne?.dispo ? parseNombreFr(ligne.prix) : 0;
        const prixUnitaire = Number.isFinite(pxBrut) ? pxBrut : 0;
        return {
            produit_id: p.id,
            status: ligne?.dispo ? 'disponible' : 'indisponible',
            prix_unitaire: prixUnitaire,
            quantite_confirmee: Number.isFinite(qte) ? qte : p.pivot.quantite,
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
        { preserveScroll: true },
    );
}

/* ─── Modal ordonnance ───────────────────────────────────────── */
const ordModal = ref({ open: false, url: '', numero: '' });
const zoom = ref(100);

function openOrdonnance(cmd: Commande) {
    ordModal.value = {
        open: true,
        url: cmd.ordonnance_url ?? '',
        numero: cmd.numero,
    };
    zoom.value = 100;
}
function closeOrdonnance() {
    ordModal.value.open = false;
}
function zoomIn() {
    zoom.value = Math.min(zoom.value + 25, 200);
}
function zoomOut() {
    zoom.value = Math.max(zoom.value - 25, 50);
}
function resetZoom() {
    zoom.value = 100;
}
function downloadOrdonnance() {
    if (!ordModal.value.url) return;
    const a = document.createElement('a');
    a.href = ordModal.value.url;
    a.download = `ordonnance-${ordModal.value.numero}`;
    a.click();
}
</script>

<template>
    <Head title="Commandes - BengaDok" />

    <PharmacyLayout>
        <!-- Même fond et grille que le tableau de bord pharmacie -->
        <div class="pharmacy-content-shell flex min-h-full flex-1 flex-col">
            <!-- Une seule barre d’onglets (compteurs + libellés), style aligné dashboard -->
            <div
                class="pharmacy-card mb-4 flex flex-wrap items-stretch gap-2 p-2 sm:gap-2 sm:p-3"
            >
                <button
                    type="button"
                    class="flex min-h-9 flex-1 items-center justify-center gap-2 rounded-lg px-2.5 py-1.5 text-xs font-semibold shadow-sm transition-all sm:min-w-0 sm:flex-none sm:px-3"
                    :class="
                        onglet === 'nouvelles'
                            ? 'bg-[#3995d2] text-white ring-2 ring-white/40'
                            : 'bg-white/80 text-gray-800 hover:bg-white'
                    "
                    @click="changeOnglet('nouvelles')"
                >
                    <span
                        class="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full px-1 text-[10px] font-bold tabular-nums"
                        :class="
                            onglet === 'nouvelles'
                                ? 'bg-white/25'
                                : 'bg-[#3995d2]/15 text-[#3995d2]'
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
                            : 'bg-white/80 text-gray-800 hover:bg-white'
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
                            : 'bg-white/80 text-gray-800 hover:bg-white'
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
                    type="button"
                    class="flex min-h-9 flex-1 items-center justify-center gap-2 rounded-lg px-2.5 py-1.5 text-xs font-semibold shadow-sm transition-all sm:flex-none sm:px-3"
                    :class="
                        onglet === 'livrees'
                            ? 'bg-[#5bb66e] text-white ring-2 ring-white/40'
                            : 'bg-white/80 text-gray-800 hover:bg-white'
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
                        class="overflow-hidden rounded-2xl bg-white shadow-sm"
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
                                        class="font-mono text-[15px] font-extrabold text-[#2563EB]"
                                        >{{ cmd.numero }}</span
                                    >
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
                            class="border-t border-gray-100 px-5 pb-5 pt-4 space-y-4"
                        >
                            <!-- Box ordonnance -->
                            <div
                                v-if="cmd.ordonnance_id"
                                class="flex items-center justify-between rounded-xl border border-[#BFDBFE] bg-[#EFF6FF] px-4 py-3"
                            >
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex size-9 items-center justify-center rounded-full bg-[#3B82F6]/10"
                                    >
                                        <FileText
                                            class="size-5 text-[#3B82F6]"
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
                                    class="overflow-x-auto rounded-xl border border-gray-100"
                                >
                                    <table
                                        class="w-full min-w-[720px] text-[13px]"
                                    >
                                        <thead class="bg-gray-50">
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
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-50">
                                            <tr
                                                v-for="p in cmd.produits"
                                                :key="p.id"
                                                class="transition-opacity"
                                                :class="
                                                    formLignes[cmd.id]?.[p.id]
                                                        ?.dispo
                                                        ? 'opacity-100'
                                                        : 'opacity-40'
                                                "
                                            >
                                                <!-- Nom -->
                                                <td class="px-4 py-2.5">
                                                    <span
                                                        class="inline-block rounded-md border border-gray-200 bg-white px-2.5 py-1 text-[13px] text-gray-800"
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
                                                                !formLignes[
                                                                    cmd.id
                                                                ]?.[p.id]?.dispo
                                                            "
                                                            class="w-full rounded-md border px-2 py-1 text-center text-[13px] transition-colors"
                                                            :class="
                                                                !formLignes[
                                                                    cmd.id
                                                                ]?.[p.id]?.dispo
                                                                    ? 'border-gray-100 bg-gray-50 text-gray-400 cursor-not-allowed'
                                                                    : qteInvalide(
                                                                            cmd.id,
                                                                            p,
                                                                        )
                                                                      ? 'border-red-400 bg-red-50 text-red-700 focus:outline-none focus:ring-1 focus:ring-red-400'
                                                                      : 'border-gray-200 bg-white text-gray-800 focus:border-[#3B82F6] focus:outline-none focus:ring-1 focus:ring-[#3B82F6]'
                                                            "
                                                        />
                                                        <!-- Hint max -->
                                                        <span
                                                            v-if="
                                                                formLignes[
                                                                    cmd.id
                                                                ]?.[p.id]?.dispo
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
                                                                !formLignes[
                                                                    cmd.id
                                                                ]?.[p.id]?.dispo
                                                            "
                                                            class="w-24 rounded-md border px-2 py-1 text-[13px] transition-colors"
                                                            :class="
                                                                formLignes[
                                                                    cmd.id
                                                                ]?.[p.id]?.dispo
                                                                    ? 'border-gray-200 bg-white text-gray-700 focus:border-[#3B82F6] focus:outline-none focus:ring-1 focus:ring-[#3B82F6]'
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
                                                                    ? 'text-gray-900'
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
                                                <!-- Toggle disponibilité -->
                                                <td class="px-3 py-2.5">
                                                    <button
                                                        type="button"
                                                        class="relative inline-flex h-5 w-9 cursor-pointer items-center rounded-full transition-colors focus:outline-none"
                                                        :class="
                                                            formLignes[cmd.id][
                                                                p.id
                                                            ].dispo
                                                                ? 'bg-[#22C55E]'
                                                                : 'bg-gray-200'
                                                        "
                                                        @click="
                                                            formLignes[cmd.id][
                                                                p.id
                                                            ].dispo =
                                                                !formLignes[
                                                                    cmd.id
                                                                ][p.id].dispo
                                                        "
                                                    >
                                                        <span
                                                            class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition-transform"
                                                            :class="
                                                                formLignes[
                                                                    cmd.id
                                                                ][p.id].dispo
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
                                    class="text-2xl font-bold text-gray-900"
                                    >{{ totalCmd(cmd).toFixed(1) }}</span
                                >
                                <span class="text-[12px] text-gray-500"
                                    >xaf</span
                                >
                            </div>

                            <!-- Commentaires -->
                            <textarea
                                v-model="formCommentaires[cmd.id]"
                                placeholder="Commentaires ..."
                                rows="3"
                                class="w-full resize-none rounded-xl border border-gray-200 px-4 py-3 text-[13px] text-gray-700 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-300"
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
                                        v-if="hasPrixError(cmd)"
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
                                                ? 'bg-[#3995d2] text-white hover:bg-[#3181b8]'
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
                        class="overflow-hidden rounded-2xl bg-white shadow-sm"
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
                                        class="font-mono text-[15px] font-extrabold text-[#4338CA]"
                                        >{{ cmd.numero }}</span
                                    >
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
                            class="border-t border-gray-100 px-5 pb-5 pt-4 space-y-4"
                        >
                            <div
                                v-if="cmd.ordonnance_id"
                                class="flex items-center justify-between rounded-xl border border-[#BFDBFE] bg-[#EFF6FF] px-4 py-3"
                            >
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex size-9 items-center justify-center rounded-full bg-[#3B82F6]/10"
                                    >
                                        <FileText
                                            class="size-5 text-[#3B82F6]"
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
                                    class="flex items-center gap-1.5 rounded-lg border border-[#3B82F6] px-3 py-1.5 text-[11px] font-bold text-[#3B82F6] hover:bg-[#EFF6FF] transition-colors"
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
                                    class="overflow-x-auto rounded-xl border border-gray-100"
                                >
                                    <table
                                        class="w-full min-w-[720px] text-[13px]"
                                    >
                                        <thead class="bg-gray-50">
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
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-50">
                                            <tr
                                                v-for="p in cmd.produits"
                                                :key="p.id"
                                            >
                                                <td class="px-4 py-2.5">
                                                    <span
                                                        class="inline-block rounded-md border border-gray-200 bg-white px-2.5 py-1 text-[13px]"
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
                                                            class="text-[13px] font-semibold text-gray-900"
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
                                                            class="text-[13px] font-bold text-gray-900"
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
                                                        class="relative inline-flex h-5 w-9 items-center rounded-full"
                                                        :class="
                                                            p.pivot.status !==
                                                            'indisponible'
                                                                ? 'bg-[#22C55E]'
                                                                : 'bg-gray-200'
                                                        "
                                                    >
                                                        <span
                                                            class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition-transform"
                                                            :class="
                                                                p.pivot
                                                                    .status !==
                                                                'indisponible'
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
                        class="overflow-hidden rounded-2xl bg-white shadow-sm"
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
                                        class="font-mono text-[15px] font-extrabold text-[#2563EB]"
                                        >{{ cmd.numero }}</span
                                    >
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
                                    Valider l'achat et la récupération
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
                            class="border-t border-gray-100 px-5 pb-5 pt-4 space-y-4"
                        >
                            <div
                                v-if="cmd.ordonnance_id"
                                class="flex items-center justify-between rounded-xl border border-[#BFDBFE] bg-[#EFF6FF] px-4 py-3"
                            >
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex size-9 items-center justify-center rounded-full bg-[#3B82F6]/10"
                                    >
                                        <FileText
                                            class="size-5 text-[#3B82F6]"
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
                                    class="flex items-center gap-1.5 rounded-lg border border-[#3B82F6] px-3 py-1.5 text-[11px] font-bold text-[#3B82F6] hover:bg-[#EFF6FF] transition-colors"
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
                                    class="overflow-x-auto rounded-xl border border-gray-100"
                                >
                                    <table
                                        class="w-full min-w-[720px] text-[13px]"
                                    >
                                        <thead class="bg-gray-50">
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
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-50">
                                            <tr
                                                v-for="p in cmd.produits"
                                                :key="p.id"
                                            >
                                                <td class="px-4 py-2.5">
                                                    <span
                                                        class="inline-block rounded-md border border-gray-200 bg-white px-2.5 py-1 text-[13px] text-gray-800"
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
                                                            class="text-[13px] font-semibold text-gray-900"
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
                                                            class="text-[13px] font-bold text-gray-900"
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
                                                        class="relative inline-flex h-5 w-9 items-center rounded-full"
                                                        :class="
                                                            p.pivot.status !==
                                                            'indisponible'
                                                                ? 'bg-[#22C55E]'
                                                                : 'bg-gray-200'
                                                        "
                                                    >
                                                        <span
                                                            class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow"
                                                            :class="
                                                                p.pivot
                                                                    .status !==
                                                                'indisponible'
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
                                    class="text-2xl font-bold text-gray-900"
                                    >{{
                                        Number(cmd.prix_total || 0).toFixed(1)
                                    }}</span
                                >
                                <span class="text-[12px] text-gray-500"
                                    >xaf</span
                                >
                            </div>

                            <!-- Commentaire (lecture seule) -->
                            <div
                                class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-[13px]"
                                :class="
                                    cmd.commentaire
                                        ? 'text-gray-700'
                                        : 'text-gray-400'
                                "
                            >
                                {{ cmd.commentaire || 'Commentaires ...' }}
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
                        class="overflow-hidden rounded-2xl bg-white shadow-sm"
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
                                        class="font-mono text-[15px] font-extrabold text-gray-700"
                                        >{{ cmd.numero }}</span
                                    >
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
                            class="border-t border-gray-100 px-5 pb-5 pt-4 space-y-4"
                        >
                            <!-- Ordonnance -->
                            <div
                                v-if="cmd.ordonnance_id"
                                class="flex items-center justify-between rounded-xl border border-[#BFDBFE] bg-[#EFF6FF] px-4 py-3"
                            >
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex size-9 items-center justify-center rounded-full bg-[#3B82F6]/10"
                                    >
                                        <FileText
                                            class="size-5 text-[#3B82F6]"
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
                                    class="flex items-center gap-1.5 rounded-lg border border-[#3B82F6] px-3 py-1.5 text-[11px] font-bold text-[#3B82F6] hover:bg-[#EFF6FF] transition-colors"
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
                                    class="overflow-x-auto rounded-xl border border-gray-100"
                                >
                                    <table
                                        class="w-full min-w-[720px] text-[13px]"
                                    >
                                        <thead class="bg-gray-50">
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
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-50">
                                            <tr
                                                v-for="p in cmd.produits"
                                                :key="p.id"
                                            >
                                                <td class="px-4 py-2.5">
                                                    <span
                                                        class="inline-block rounded-md border border-gray-200 bg-white px-2.5 py-1 text-[13px]"
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
                                                            class="text-[13px] font-semibold text-gray-900"
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
                                                            class="text-[13px] font-bold text-gray-900"
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
                                                        class="relative inline-flex h-5 w-9 items-center rounded-full"
                                                        :class="
                                                            p.pivot.status !==
                                                            'indisponible'
                                                                ? 'bg-[#22C55E]'
                                                                : 'bg-gray-200'
                                                        "
                                                    >
                                                        <span
                                                            class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow"
                                                            :class="
                                                                p.pivot
                                                                    .status !==
                                                                'indisponible'
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
                                    class="text-2xl font-bold text-gray-900"
                                    >{{
                                        Number(cmd.prix_total || 0).toFixed(1)
                                    }}</span
                                >
                                <span class="text-[12px] text-gray-500"
                                    >xaf</span
                                >
                            </div>

                            <!-- Commentaire (lecture seule) -->
                            <div
                                class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-[13px]"
                                :class="
                                    cmd.commentaire
                                        ? 'text-gray-700'
                                        : 'text-gray-400'
                                "
                            >
                                {{ cmd.commentaire || 'Commentaires ...' }}
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

            <!-- ── Pagination (lisible sur dégradé, alignée maquette pharmacie) ── -->
            <div
                v-if="commandes.links?.length > 3"
                class="mt-2 flex flex-col gap-4 rounded-[28px] border border-white/90 bg-white/95 px-4 py-4 shadow-[0_8px_32px_rgba(0,0,0,0.14)] backdrop-blur-md sm:flex-row sm:items-center sm:justify-between sm:gap-6 sm:px-6 sm:py-4"
            >
                <p
                    class="text-center text-[13px] leading-snug text-[#5c5959] sm:text-left sm:text-[14px]"
                >
                    Affichage de
                    <span class="tabular-nums font-black text-black">{{
                        commandes.from
                    }}</span>
                    à
                    <span class="tabular-nums font-black text-black">{{
                        commandes.to
                    }}</span>
                    sur
                    <span class="tabular-nums font-black text-[#3995d2]">{{
                        commandes.total
                    }}</span>
                    commandes
                </p>
                <nav
                    class="flex flex-wrap items-center justify-center gap-1.5 sm:justify-end"
                    aria-label="Pagination"
                >
                    <template v-for="(link, i) in commandes.links" :key="i">
                        <span
                            v-if="!link.url"
                            class="flex min-h-9 min-w-9 items-center justify-center rounded-full bg-gray-100 px-2.5 py-2 text-[13px] font-semibold text-gray-400"
                            v-html="link.label"
                        />
                        <Link
                            v-else
                            :href="link.url"
                            class="flex min-h-9 min-w-9 items-center justify-center rounded-full border px-3 py-2 text-[13px] font-bold transition-all duration-150"
                            :class="
                                link.active
                                    ? 'border-[#3995d2] bg-[#3995d2] text-white shadow-[0_4px_12px_rgba(57,149,210,0.45)] ring-2 ring-white/70'
                                    : 'border-gray-200/90 bg-white text-[#5c5959] shadow-sm hover:border-[#3995d2]/50 hover:text-[#3995d2] hover:shadow-md'
                            "
                        >
                            <span v-html="link.label" />
                        </Link>
                    </template>
                </nav>
            </div>
        </div>

        <!-- ═══ MODAL : Confirmation "Valider l'achat" ═══ -->
        <Teleport to="body">
            <div
                v-if="confirmModal.open"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                style="background: rgba(0, 0, 0, 0.5)"
                @click.self="annulerConfirm"
            >
                <div
                    class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl"
                >
                    <!-- Entête -->
                    <div
                        class="flex items-center gap-3 bg-[#FFFBEB] border-b border-[#FCD34D] px-6 py-4"
                    >
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-full bg-[#F59E0B]/10"
                        >
                            <ShoppingCart class="size-5 text-[#F59E0B]" />
                        </div>
                        <div>
                            <p class="text-[15px] font-extrabold text-gray-900">
                                Confirmer la récupération
                            </p>
                            <p class="text-[12px] text-gray-500">
                                Commande {{ confirmModal.cmd?.numero }}
                            </p>
                        </div>
                    </div>
                    <!-- Corps -->
                    <div class="px-6 py-5">
                        <p class="text-[14px] text-gray-700">
                            Vous êtes sur le point de valider que le patient a
                            bien récupéré ou payé sa commande.
                        </p>
                        <p
                            class="mt-2 text-[13px] font-semibold text-[#92400E] bg-[#FFFBEB] rounded-lg px-3 py-2 border border-[#FCD34D]"
                        >
                            ⚠ Cette action est irréversible. La commande passera
                            en statut « Retirée ».
                        </p>
                    </div>
                    <!-- Pied -->
                    <div
                        class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4"
                    >
                        <button
                            class="rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-[13px] font-bold text-gray-700 hover:bg-gray-50 transition-colors"
                            @click="annulerConfirm"
                        >
                            Annuler
                        </button>
                        <button
                            class="flex items-center gap-2 rounded-xl bg-[#F59E0B] px-5 py-2.5 text-[13px] font-bold text-white shadow hover:bg-[#D97706] transition-colors"
                            @click="confirmerAchat"
                        >
                            <ShoppingCart class="size-4" />
                            Confirmer la récupération
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ═══ MODAL : Visionneuse ordonnance ═══ -->
        <Teleport to="body">
            <div
                v-if="ordModal.open"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                style="background: rgba(0, 0, 0, 0.55)"
                @click.self="closeOrdonnance"
            >
                <div
                    class="relative flex w-full max-w-[500px] max-h-[90vh] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl"
                >
                    <!-- Entête -->
                    <div
                        class="flex items-center gap-3 border-b border-gray-100 px-5 py-4"
                    >
                        <div
                            class="flex size-9 shrink-0 items-center justify-center rounded-full bg-[#EFF6FF]"
                        >
                            <FileText class="size-5 text-[#3B82F6]" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-[14px] font-extrabold text-gray-900">
                                Ordonnance — Commande {{ ordModal.numero }}
                            </p>
                            <p class="text-[11px] text-gray-400">
                                Vérification d'authenticité
                            </p>
                        </div>
                        <button
                            class="flex size-8 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 transition-colors"
                            @click="closeOrdonnance"
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                    <!-- Barre zoom -->
                    <div
                        class="flex items-center gap-2 border-b border-gray-100 bg-gray-50 px-4 py-2"
                    >
                        <button
                            class="flex size-7 items-center justify-center rounded-md border border-gray-200 bg-white text-gray-600 hover:bg-gray-100"
                            @click="zoomOut"
                        >
                            <ZoomOut class="size-3.5" />
                        </button>
                        <span
                            class="min-w-[40px] text-center text-[12px] font-semibold text-gray-700"
                            >{{ zoom }}%</span
                        >
                        <button
                            class="flex size-7 items-center justify-center rounded-md border border-gray-200 bg-white text-gray-600 hover:bg-gray-100"
                            @click="zoomIn"
                        >
                            <ZoomIn class="size-3.5" />
                        </button>
                        <button
                            class="flex size-7 items-center justify-center rounded-md border border-gray-200 bg-white text-gray-600 hover:bg-gray-100"
                            @click="resetZoom"
                        >
                            <RefreshCw class="size-3.5" />
                        </button>
                        <div class="flex-1" />
                        <button
                            class="flex items-center gap-1.5 rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-gray-600 hover:bg-gray-100"
                            @click="downloadOrdonnance"
                        >
                            <Download class="size-3.5" />Télécharger
                        </button>
                    </div>
                    <!-- Visionneuse -->
                    <div
                        class="flex-1 overflow-auto bg-gray-100 flex items-start justify-center min-h-[250px] p-4"
                    >
                        <img
                            v-if="ordModal.url"
                            :src="ordModal.url"
                            alt="Ordonnance"
                            class="rounded-lg shadow"
                            :style="{
                                transform: `scale(${zoom / 100})`,
                                transformOrigin: 'top center',
                                maxWidth: '100%',
                            }"
                        />
                        <div v-else class="m-auto text-center text-gray-400">
                            <FileText class="mx-auto mb-2 size-12 opacity-30" />
                            <p class="text-[13px]">Ordonnance non disponible</p>
                        </div>
                    </div>
                    <!-- Section vérification IA -->
                    <div class="border-t border-gray-100 px-5 py-4 space-y-3">
                        <div class="rounded-xl bg-[#EFF6FF] px-4 py-3">
                            <div class="mb-1 flex items-center gap-2">
                                <ShieldCheck class="size-4 text-[#3B82F6]" />
                                <span
                                    class="text-[13px] font-bold text-[#1D4ED8]"
                                    >Prêt à vérifier</span
                                >
                            </div>
                            <p class="mb-3 text-[11px] text-[#3B82F6]">
                                Analyse par IA
                            </p>
                            <button
                                class="w-full rounded-xl bg-[#3B82F6] py-2.5 text-[13px] font-bold text-white shadow hover:bg-[#2563EB] transition-colors"
                            >
                                Lancer l'authentification
                            </button>
                        </div>
                        <div>
                            <p
                                class="mb-2 flex items-center gap-1.5 text-[12px] font-bold text-[#3B82F6]"
                            >
                                <Eye class="size-3.5" />Point de vérification
                            </p>
                            <ul class="space-y-1 text-[12px] text-gray-600">
                                <li class="flex items-center gap-2">
                                    <span
                                        class="size-1.5 rounded-full bg-gray-400 shrink-0"
                                    />Cachet du médecin
                                </li>
                                <li class="flex items-center gap-2">
                                    <span
                                        class="size-1.5 rounded-full bg-gray-400 shrink-0"
                                    />Signature du médecin
                                </li>
                                <li class="flex items-center gap-2">
                                    <span
                                        class="size-1.5 rounded-full bg-gray-400 shrink-0"
                                    />Date de prescription
                                </li>
                                <li class="flex items-center gap-2">
                                    <span
                                        class="size-1.5 rounded-full bg-gray-400 shrink-0"
                                    />Nom du patient
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </PharmacyLayout>
</template>
