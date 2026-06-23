<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    Plus,
    Search,
    AlertTriangle,
    Truck,
    FileText,
    RefreshCw,
    XCircle,
    X,
    Check,
    CheckCircle2,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import CommandeEnregistrementModal from '@/components/CommandeEnregistrementModal.vue';
import type { FormEnregPayload } from '@/components/CommandeEnregistrementModal.vue';
import CommandesTable from '@/components/commandes/CommandesTable.vue';
import OrdonnanceAnalysisProgressBar from '@/components/OrdonnanceAnalysisProgressBar.vue';
import OrdonnanceViewer from '@/components/OrdonnanceViewer.vue';
import RecuCommandeModal from '@/components/RecuCommandeModal.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    classesStatutDisponibiliteLigne,
    libelleStatutDisponibiliteLigne,
} from '@/lib/commandeProduitStatus';
import { splitProduitsCommande } from '@/lib/commandeTotals';
import {
    formatCommandeDateHeure,
    formatDateFrLocal,
} from '@/lib/formatDateLocal';
import { normalizeInertiaErrors } from '@/lib/validationErrors';
import { dashboard } from '@/routes';
import { STATUTS_COMMANDE } from '@/types';
import type { BreadcrumbItem, CommandeDetail } from '@/types';
import type { MotifAnnulationOption } from '@/types';

const props = withDefaults(
    defineProps<{
        commandes?: {
            data: Array<{
                id: number;
                numero: string;
                date: string;
                status: string;
                prix_total: number;
                client: {
                    nom: string;
                    prenom: string;
                    tel: string;
                    adresse?: string;
                    sexe?: string;
                };
                pharmacie?: { designation: string };
                produits: Array<{
                    designation: string;
                    dosage?: string;
                    pivot: { quantite: number };
                }>;
                montant_livraison?: { designation: number };
                mode_paiement?: { designation: string };
            }>;
            links: Array<{
                url: string | null;
                label: string;
                active: boolean;
            }>;
        };
        stats?: Record<string, number>;
        filters?: {
            search?: string;
            status?: string;
            periode?: string;
            date?: string;
        };
        pharmacies?: Array<{
            id: number;
            designation: string;
            adresse: string;
            telephone: string;
            zone_id?: number;
            de_garde?: boolean;
            zone?: { id: number; designation: string };
            type_pharmacie?: { designation: string };
            heurs?: { ouverture: string; fermeture: string };
        }>;
        zones?: Array<{
            id: number;
            designation: string;
            pharmacies_count: number;
        }>;
        montantsLivraison?: Array<{ id: number; designation: number }>;
        modesPaiement?: Array<{ id: number; designation: string }>;
        livreurs?: Array<{
            id: number;
            nom: string;
            prenom: string;
            tel: string;
        }>;
        /** Ouvre le panneau détail (ex. redirection depuis /commandes/{id} ou lien partagé). */
        openDetailCommandeId?: number | null;
        /** Arrondissements (Brazzaville) pour les formulaires commande. */
        arrondissements?: string[];
        /** Types produit parapharmacie (paramètres app). */
        parapharma_produit_types?: string[];
    }>(),
    {
        commandes: () => ({ data: [], links: [] }),
        stats: () => ({}),
        filters: () => ({}),
        pharmacies: () => [],
        zones: () => [],
        montantsLivraison: () => [],
        modesPaiement: () => [],
        livreurs: () => [],
        openDetailCommandeId: null,
        arrondissements: () => [],
        parapharma_produit_types: () => ['Parapharmacie'],
    },
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: dashboard() },
    { title: 'Commandes', href: '/commandes' },
];

const page = usePage();
const flashError = computed(
    () => (page.props.flash as { error?: string })?.error,
);
const flashStatus = computed(
    () => (page.props.flash as { status?: string })?.status,
);
const canCreateCommande = computed(() => {
    const roles =
        (page.props.auth as { user?: { roles?: string[] } })?.user?.roles ?? [];
    return roles.some((r) =>
        ['admin', 'super_admin', 'agent_call_center'].includes(r),
    );
});

/** Pharmacie a déclaré toutes les lignes indisponibles : seule l'annulation est proposée côté admin. */
const enAttentePharmacieToutIndisponible = computed(() => {
    const c = detailCommande.value;
    return (
        !!c &&
        c.status === 'en_attente' &&
        c.status_pharmacie === 'indisponible'
    );
});

/** Livraison + mode de paiement pour la commande ouverte ; idem pour chaque lien « autre pharmacie » en_attente avant validation groupe. */
const peutValiderCommandeEnAttente = computed(() => {
    const c = detailCommande.value;
    if (!c || c.status !== 'en_attente') {
        return false;
    }
    if (enAttentePharmacieToutIndisponible.value) {
        return false;
    }
    if (!c.montant_livraison || !c.mode_paiement) {
        return false;
    }
    const enfants = c.enfants ?? [];
    for (const e of enfants) {
        if (e.status !== 'en_attente') {
            continue;
        }
        if (!e.montant_livraison || !e.mode_paiement) {
            return false;
        }
    }
    return true;
});

/** Il reste au moins un maillon enfant en attente sans livraison ou sans mode de paiement. */
const enfantEnAttenteSansPaiementComplet = computed(() => {
    const c = detailCommande.value;
    if (!c?.enfants?.length || c.status !== 'en_attente') {
        return false;
    }
    return c.enfants.some(
        (e) =>
            e.status === 'en_attente' &&
            (!e.montant_livraison || !e.mode_paiement),
    );
});

/** Même périmètre que la fiche commande : OCR / vérification ordonnance. */
const isAgent = computed(() => canCreateCommande.value);

const motifsAnnulation = computed(
    () => (page.props.motifs_annulation ?? []) as MotifAnnulationOption[],
);

const motifsRelance = computed(() =>
    Object.fromEntries(
        motifsAnnulation.value.map((m) => [m.slug, m.autorise_relance]),
    ),
);

function motifAutoriseRelance(motif: string | undefined): boolean {
    if (!motif) return false;
    return !!motifsRelance.value[motif];
}

const motifOptions = computed(() =>
    motifsAnnulation.value.map((m) => ({
        key: m.slug,
        label: m.label,
        desc: m.autorise_relance
            ? 'Après annulation, un agent pourra relancer la commande (ex. autre pharmacie).'
            : 'Aucune relance proposée pour ce motif.',
    })),
);

const motifLabelBySlug = computed(() =>
    Object.fromEntries(motifsAnnulation.value.map((m) => [m.slug, m.label])),
);

const searchQuery = ref(props.filters.search ?? '');
const activeTab = ref<'gestion' | 'statistiques'>('gestion');
const detailCommande = ref<CommandeDetail | null>(null);
const showDetailModal = ref(false);
const showEnregistrementModal = ref(false);
const showAnnulerModal = ref(false);
const showValiderModal = ref(false);
const showRecuModal = ref(false);
const showRelancerModal = ref(false);
const loadingDetail = ref(false);
const motifAnnulation = ref('');
const noteAnnulation = ref('');

const selectedIds = ref<Set<number>>(new Set());
const showBulkAnnulerModal = ref(false);
const motifBulkAnnulation = ref('');

const allSelected = computed(() => {
    const data = props.commandes?.data ?? [];
    return data.length > 0 && data.every((c) => selectedIds.value.has(c.id));
});
const someSelected = computed(() => selectedIds.value.size > 0);

function toggleAll() {
    const data = props.commandes?.data ?? [];
    if (allSelected.value) {
        data.forEach((c) => selectedIds.value.delete(c.id));
    } else {
        data.forEach((c) => selectedIds.value.add(c.id));
    }
    selectedIds.value = new Set(selectedIds.value);
}
function toggleOne(id: number) {
    const next = new Set(selectedIds.value);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    selectedIds.value = next;
}
function clearSelection() {
    selectedIds.value = new Set();
}

function exportSelectedCSV() {
    const data = props.commandes?.data ?? [];
    const selected = data.filter((c) => selectedIds.value.has(c.id));
    if (!selected.length) return;
    const headers = [
        'N°',
        'Client',
        'Tél',
        'Date',
        'Adresse',
        'Médicaments',
        'Montant',
        'Statut',
    ];
    const rows = selected.map((c) => [
        c.numero,
        getClientDisplayName(c.client),
        c.client?.tel ?? '',
        c.date ?? '',
        c.client?.adresse ?? '-',
        getMedicamentsText(c.produits),
        Number(c.prix_total).toLocaleString('fr-FR'),
        getStatusLabel(c.status),
    ]);
    const csv = [
        headers.join(';'),
        ...rows.map((r) =>
            r.map((v) => `"${String(v).replace(/"/g, '""')}"`).join(';'),
        ),
    ].join('\n');
    const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `commandes_${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
    URL.revokeObjectURL(a.href);
}

function openBulkAnnulerModal() {
    motifBulkAnnulation.value = '';
    showBulkAnnulerModal.value = true;
}
function confirmBulkAnnuler() {
    if (!motifBulkAnnulation.value || selectedIds.value.size === 0) return;
    const ids = Array.from(selectedIds.value);
    router.post(
        '/commandes/bulk-annuler',
        { ids, motif_annulation: motifBulkAnnulation.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                showBulkAnnulerModal.value = false;
                clearSelection();
            },
        },
    );
}

const errorsRelancer = ref<Record<string, string>>({});

watch(
    () => props.filters.search,
    (v) => {
        searchQuery.value = v ?? '';
    },
);

const statuts = STATUTS_COMMANDE;

function getMotifAnnulationLabel(key: string | undefined): string {
    return (key && motifLabelBySlug.value[key]) || key || 'Non précisé';
}

type CommandeFilters = {
    search?: string;
    status?: string;
    periode?: string;
    date?: string;
};

function filtrer(key: string, value: string) {
    const raw: CommandeFilters = { ...props.filters };
    const v = value.trim() || undefined;

    if (key === 'periode') {
        raw.periode = v;
        if (v) raw.date = undefined;
    } else if (key === 'date') {
        raw.date = v;
        if (v) raw.periode = undefined;
    } else {
        (raw as Record<string, string | undefined>)[key] = v;
    }

    const params: Record<string, string> = {};
    if (raw.search) params.search = raw.search;
    if (raw.status) params.status = raw.status;
    if (raw.periode) params.periode = raw.periode;
    if (raw.date) params.date = raw.date;

    router.get('/commandes', params, {
        preserveState: true,
        preserveScroll: true,
    });
}

function getStatusLabel(s: string) {
    if (s === 'a_preparer') return 'Validée';
    if (s === 'retiree') return 'Livrée';
    return statuts.find((st) => st.key === s)?.label ?? s;
}

function getStatusBadgeStyle(s: string) {
    const key =
        s === 'a_preparer' ? 'validee' : s === 'retiree' ? 'retiree' : s;
    const st = statuts.find((x) => x.key === key);
    if (!st)
        return { backgroundColor: '#6b7280', color: 'white', border: 'none' };
    return {
        backgroundColor: st.color,
        color: st.textColor,
        border: (st as { borderColor?: string }).borderColor
            ? `1px solid ${(st as { borderColor?: string }).borderColor}`
            : 'none',
    };
}

function getMedicamentsText(
    produits: Array<{ designation: string; dosage?: string }> | undefined,
): string {
    return (
        produits
            ?.map((p) => p.designation + (p.dosage ? ' ' + p.dosage : ''))
            .join(', ') || '-'
    );
}

function civiliteFromSexe(sexe?: string | null): string {
    if (sexe === 'F') return 'Mme';
    if (sexe === 'M') return 'Mr';
    return '';
}

function getClientDisplayName(
    client: { nom?: string; prenom?: string; sexe?: string } | undefined,
): string {
    if (!client) return '-';
    const prenom = (client.prenom ?? '').trim();
    const nom = (client.nom ?? '').trim();
    if (!prenom && !nom) return '-';
    const core =
        prenom === nom ? prenom : [prenom, nom].filter(Boolean).join(' ');
    const civ = civiliteFromSexe(client.sexe);
    return civ ? `${civ} ${core}` : core;
}

function formatDate(d: string) {
    return formatDateFrLocal(d);
}

function formatDateHeureCommande(c: CommandeDetail): string {
    return formatCommandeDateHeure(c.date, c.heurs, c.created_at ?? c.updated_at);
}

/** Libellé ligne médicament (pivot commande_produit). */
function libellePivotStatusProduit(status: string | undefined | null): string {
    return libelleStatutDisponibiliteLigne(status);
}

function classesPivotStatusProduit(status: string | undefined | null): string {
    return classesStatutDisponibiliteLigne(status);
}

function estVenteLibrePivot(venteLibre: boolean | undefined | null): boolean {
    return Boolean(venteLibre);
}

/** Libellés FR pour la décision (l’API stocke pass, review, fail, etc.). */
function libelleDecisionVerification(decision: string | undefined): string {
    const map: Record<string, string> = {
        pass: 'Validé',
        review: 'À revoir',
        fail: 'Refusé',
        pending: 'En attente',
        skipped: 'Non analysé',
    };
    const key = (decision ?? '').toLowerCase();
    return map[key] ?? (decision ? decision : '—');
}

/** Texte d’aide affiché au survol du statut de vérification. */
function descriptionDecisionVerification(decision: string | undefined): string {
    const key = (decision ?? '').toLowerCase();
    const map: Record<string, string> = {
        pass:
            'Le score dépasse le seuil de validation : les critères (dates, mots-clés, fichier unique, etc.) sont suffisamment remplis.',
        review:
            'Contrôle manuel conseillé : score moyen ou règle métier (par ex. même fichier déjà utilisé). Vérifiez les détails dans la liste des critères.',
        fail:
            'Score sous le seuil minimum : trop peu de critères validés par l’OCR et les règles. Vérifiez la qualité du scan et le contenu.',
        pending:
            'Analyse en cours ou en file d’attente : le score et le statut final seront mis à jour automatiquement.',
        skipped:
            'Vérification automatique non exécutée : désactivée dans les paramètres ou configuration absente.',
    };
    return map[key] ?? 'Décision de vérification ordonnance (OCR + règles).';
}

/** Ignore les réponses périmées quand plusieurs fiches sont ouvertes vite d’affilée. */
let detailFetchGeneration = 0;

async function openDetail(id: number) {
    const gen = ++detailFetchGeneration;
    loadingDetail.value = true;
    showDetailModal.value = true;
    try {
        const r = await fetch(`/commandes/${id}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });
        if (gen !== detailFetchGeneration) {
            return;
        }
        if (!r.ok) {
            detailCommande.value = null;
            return;
        }
        const json = await r.json();
        if (gen !== detailFetchGeneration) {
            return;
        }
        detailCommande.value = json.commande;
    } catch {
        if (gen !== detailFetchGeneration) {
            return;
        }
        detailCommande.value = null;
    } finally {
        if (gen === detailFetchGeneration) {
            loadingDetail.value = false;
        }
    }
}

async function refreshDetailSilently(id: number): Promise<void> {
    try {
        const r = await fetch(`/commandes/${id}`, {
            headers: { Accept: 'application/json' },
        });
        if (!r.ok) return;
        const json = await r.json();
        if (detailCommande.value?.id === id) {
            detailCommande.value = json.commande;
        }
    } catch {
        /* ignore */
    }
}

let verificationPollTimer: ReturnType<typeof setInterval> | null = null;

function clearVerificationPoll(): void {
    if (verificationPollTimer !== null) {
        clearInterval(verificationPollTimer);
        verificationPollTimer = null;
    }
}

function verificationNeedsPolling(): boolean {
    if (!isAgent.value || !showDetailModal.value) {
        return false;
    }
    const s = detailCommande.value?.ordonnance?.verification?.status;

    return s === 'pending' || s === 'processing';
}

watch(
    () => [
        isAgent.value,
        showDetailModal.value,
        detailCommande.value?.id,
        detailCommande.value?.ordonnance?.verification?.status,
    ],
    () => {
        clearVerificationPoll();
        if (!verificationNeedsPolling()) {
            return;
        }
        const id = detailCommande.value?.id;
        if (!id) return;
        verificationPollTimer = setInterval(() => {
            void refreshDetailSilently(id);
        }, 2500);
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    clearVerificationPoll();
});

watch(
    () => props.openDetailCommandeId,
    (id) => {
        if (id) {
            void openDetail(id);
        }
    },
    { immediate: true },
);

function closeDetail() {
    clearVerificationPoll();
    showDetailModal.value = false;
    detailCommande.value = null;
}

function updateStatus(status: string) {
    if (!detailCommande.value) return;
    const id = detailCommande.value.id;
    router.patch(
        `/commandes/${id}/status`,
        { status },
        {
            preserveScroll: true,
            onSuccess: () => {
                openDetail(id);
            },
        },
    );
}

function openValiderModal() {
    if (!peutValiderCommandeEnAttente.value) {
        return;
    }
    showValiderModal.value = true;
}

function confirmValiderCommande() {
    if (!detailCommande.value || !peutValiderCommandeEnAttente.value) {
        return;
    }
    const id = detailCommande.value.id;
    showValiderModal.value = false;
    router.patch(
        `/commandes/${id}/status`,
        { status: 'validee' },
        {
            preserveScroll: true,
            onSuccess: () => {
                void openDetail(id);
            },
        },
    );
}

function setMontantLivraison(montantId: number) {
    if (!detailCommande.value) return;
    const id = detailCommande.value.id;
    router.patch(
        `/commandes/${id}/montant-livraison`,
        { montant_livraison_id: montantId },
        {
            preserveScroll: true,
            onSuccess: async () => {
                await refreshDetailSilently(id);
                router.reload({
                    only: ['commandes', 'stats'],
                    preserveState: true,
                });
            },
        },
    );
}

function setModePaiementCommande(modePaiementId: number) {
    if (!detailCommande.value) return;
    const id = detailCommande.value.id;
    router.patch(
        `/commandes/${id}/mode-paiement`,
        { mode_paiement_id: modePaiementId },
        {
            preserveScroll: true,
            onSuccess: async () => {
                await refreshDetailSilently(id);
                router.reload({
                    only: ['commandes', 'stats'],
                    preserveState: true,
                });
            },
        },
    );
}

function peutAssignerLivreurDetail(): boolean {
    const s = detailCommande.value?.status;
    return s === 'validee' || s === 'a_preparer' || s === 'retiree';
}

function setLivreurCommande(livreurId: number | null) {
    if (!detailCommande.value) return;
    const id = detailCommande.value.id;
    router.patch(
        `/commandes/${id}/livreur`,
        { livreur_id: livreurId },
        {
            preserveScroll: true,
            onSuccess: async () => {
                await refreshDetailSilently(id);
                router.reload({
                    only: ['commandes', 'stats'],
                    preserveState: true,
                });
            },
        },
    );
}

function openAnnulerModal() {
    motifAnnulation.value = '';
    noteAnnulation.value = '';
    showAnnulerModal.value = true;
}

function confirmAnnuler() {
    if (!detailCommande.value || !motifAnnulation.value) return;
    router.patch(
        `/commandes/${detailCommande.value.id}/status`,
        {
            status: 'annulee',
            motif_annulation: motifAnnulation.value,
            note_annulation: noteAnnulation.value || undefined,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showAnnulerModal.value = false;
                closeDetail();
                router.reload();
            },
        },
    );
}

function confirmAnnulerEtRelancer() {
    if (!detailCommande.value || !motifAnnulation.value) return;
    if (!motifAutoriseRelance(motifAnnulation.value)) return;
    const id = detailCommande.value.id;
    const note = noteAnnulation.value || undefined;
    const motif = motifAnnulation.value;
    router.patch(
        `/commandes/${id}/status`,
        {
            status: 'annulee',
            motif_annulation: motif,
            note_annulation: note,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showAnnulerModal.value = false;
                void (async () => {
                    await openDetail(id);
                    showRelancerModal.value = true;
                    router.reload({
                        only: ['commandes', 'stats'],
                        preserveState: true,
                    });
                })();
            },
        },
    );
}

function openRelancerModal() {
    if (!detailCommande.value) return;
    errorsRelancer.value = {};
    showRelancerModal.value = true;
}

function openEnregistrementModal() {
    apiErrorsEnreg.value = {};
    showEnregistrementModal.value = true;
}

const sousTotal = () => detailSplit.value.sousTotal;

const detailSplit = computed(() =>
    splitProduitsCommande(
        detailCommande.value?.produits,
        props.parapharma_produit_types ?? ['Parapharmacie'],
    ),
);

const livraison = () =>
    Number(detailCommande.value?.montant_livraison?.designation ?? 0);
const totalDetail = () => sousTotal() + livraison();

function parseValidationErrors(e: unknown): Record<string, string> {
    if (e && typeof e === 'object' && 'response' in (e as object)) {
        const data = (
            e as { response?: { data?: { errors?: Record<string, string[]> } } }
        )?.response?.data;
        const err = data?.errors ?? {};
        return Object.fromEntries(
            Object.entries(err).map(([k, v]) => [
                k,
                Array.isArray(v) ? v[0] : String(v),
            ]),
        );
    }
    return normalizeInertiaErrors(e as Record<string, unknown>);
}

const apiErrorsEnreg = ref<Record<string, string>>({});

function appendEnregistrementFields(formData: FormData, payload: FormEnregPayload): void {
    if (payload.client_id) {
        formData.append('client_id', String(payload.client_id));
    } else {
        formData.append('client_nom', payload.client_nom);
        formData.append('client_prenom', payload.client_prenom);
        formData.append('client_tel', payload.client_tel);
        formData.append('client_adresse', payload.client_adresse);
    }
    formData.append('client_arrondissement', payload.client_arrondissement);
    if (payload.client_sexe) {
        formData.append('client_sexe', payload.client_sexe);
    }
    formData.append('pharmacie_id', payload.pharmacie_id);
    if (payload.beneficiaire) {
        formData.append('beneficiaire', payload.beneficiaire);
    }
    formData.append('produits', JSON.stringify(payload.produits));
    if (payload.commentaire) {
        formData.append('commentaire', payload.commentaire);
    }
}

function submitEnregistrementFromModal(payload: FormEnregPayload) {
    apiErrorsEnreg.value = {};
    if (payload.ordonnance) {
        const formData = new FormData();
        appendEnregistrementFields(formData, payload);
        formData.append('ordonnance', payload.ordonnance);

        router.post('/commandes', formData, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                showEnregistrementModal.value = false;
            },
            onError: (e) => {
                apiErrorsEnreg.value = parseValidationErrors(e);
            },
        });
    } else {
        const data: Record<string, unknown> = {
            client_nom: payload.client_nom,
            client_prenom: payload.client_prenom,
            client_tel: payload.client_tel,
            client_adresse: payload.client_adresse,
            client_arrondissement: payload.client_arrondissement,
            client_sexe: payload.client_sexe || undefined,
            pharmacie_id: payload.pharmacie_id,
            beneficiaire: payload.beneficiaire || undefined,
            produits: payload.produits,
            commentaire: payload.commentaire || undefined,
        };
        if (payload.client_id) {
            data.client_id = payload.client_id;
        }
        router.post('/commandes', data, {
            preserveScroll: true,
            onSuccess: () => {
                showEnregistrementModal.value = false;
            },
            onError: (e) => {
                apiErrorsEnreg.value = parseValidationErrors(e);
            },
        });
    }
}

function submitRelancerFromModal(payload: FormEnregPayload) {
    const data: Record<string, unknown> = {
        pharmacie_id: payload.pharmacie_id,
        beneficiaire: payload.beneficiaire || undefined,
        produits: payload.produits,
        commentaire: payload.commentaire || undefined,
    };
    if (payload.client_sexe) {
        data.client_sexe = payload.client_sexe;
    }
    if (payload.client_id) {
        data.client_id = payload.client_id;
        data.client_arrondissement = payload.client_arrondissement;
    } else {
        data.client_nom = payload.client_nom;
        data.client_prenom = payload.client_prenom;
        data.client_tel = payload.client_tel;
        data.client_adresse = payload.client_adresse;
        data.client_arrondissement = payload.client_arrondissement;
    }
    if (payload.ordonnance) {
        const formData = new FormData();
        if (payload.client_id) {
            formData.append('client_id', String(payload.client_id));
            formData.append(
                'client_arrondissement',
                payload.client_arrondissement,
            );
        } else {
            formData.append('client_nom', payload.client_nom);
            formData.append('client_prenom', payload.client_prenom);
            formData.append('client_tel', payload.client_tel);
            formData.append('client_adresse', payload.client_adresse);
            formData.append(
                'client_arrondissement',
                payload.client_arrondissement,
            );
        }
        if (payload.client_sexe)
            formData.append('client_sexe', payload.client_sexe);
        formData.append('pharmacie_id', payload.pharmacie_id);
        if (payload.beneficiaire)
            formData.append('beneficiaire', payload.beneficiaire);
        formData.append('produits', JSON.stringify(payload.produits));
        formData.append('ordonnance', payload.ordonnance);
        if (payload.commentaire)
            formData.append('commentaire', payload.commentaire);
        router.post('/commandes', formData, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                errorsRelancer.value = {};
                showRelancerModal.value = false;
                showAnnulerModal.value = false;
                closeDetail();
            },
            onError: (e) => {
                errorsRelancer.value = parseValidationErrors(e);
            },
        });
    } else {
        if (payload.reutiliser_ordonnance_commande_id) {
            data.reutiliser_ordonnance_commande_id =
                payload.reutiliser_ordonnance_commande_id;
        }
        router.post('/commandes', data, {
            preserveScroll: true,
            onSuccess: () => {
                errorsRelancer.value = {};
                showRelancerModal.value = false;
                showAnnulerModal.value = false;
                closeDetail();
            },
            onError: (e) => {
                errorsRelancer.value = parseValidationErrors(e);
            },
        });
    }
}
</script>

<template>
    <Head title="Gestion des commandes - BengaDok" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="relative min-h-full overflow-x-auto rounded-xl p-6 md:p-8">
            <!-- Carte principale design React -->
            <div
                class="relative overflow-hidden rounded-[30px] bg-white p-8 shadow-[0px_4px_10px_rgba(0,0,0,0.25)]"
            >
                <div class="relative z-10">
                    <!-- Notifications session (création / validation commande, etc.) -->
                    <div
                        v-if="flashError"
                        class="mb-4 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-[14px] font-medium text-red-800 shadow-sm"
                        role="alert"
                    >
                        <AlertTriangle
                            class="size-5 shrink-0 text-red-600"
                            aria-hidden="true"
                        />
                        <span class="min-w-0 flex-1">{{ flashError }}</span>
                    </div>
                    <div
                        v-if="flashStatus"
                        class="mb-4 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-[14px] font-medium text-emerald-900 shadow-sm"
                        role="status"
                    >
                        <CheckCircle2
                            class="size-5 shrink-0 text-emerald-600"
                            aria-hidden="true"
                        />
                        <span class="min-w-0 flex-1">{{ flashStatus }}</span>
                    </div>

                    <!-- Tabs design React -->
                    <div class="mb-6 flex gap-8 border-b-2 border-transparent">
                        <button
                            type="button"
                            class="relative rounded-md pb-3 text-[16px] font-bold transition-colors outline-none focus-visible:ring-2 focus-visible:ring-[#0d6efd]/40 focus-visible:ring-offset-2"
                            :class="
                                activeTab === 'gestion'
                                    ? 'text-[#0d6efd]'
                                    : 'text-gray-600'
                            "
                            @click="activeTab = 'gestion'"
                        >
                            Gestion commandes
                            <div
                                v-if="activeTab === 'gestion'"
                                class="absolute bottom-0 left-0 right-0 h-[3px] bg-[#0d6efd]"
                            />
                        </button>
                        <button
                            type="button"
                            class="relative rounded-md pb-3 text-[16px] font-bold transition-colors outline-none focus-visible:ring-2 focus-visible:ring-[#0d6efd]/40 focus-visible:ring-offset-2"
                            :class="
                                activeTab === 'statistiques'
                                    ? 'text-[#0d6efd]'
                                    : 'text-gray-600'
                            "
                            @click="activeTab = 'statistiques'"
                        >
                            Statistiques
                            <div
                                v-if="activeTab === 'statistiques'"
                                class="absolute bottom-0 left-0 right-0 h-[3px] bg-[#0d6efd]"
                            />
                        </button>
                    </div>

                    <div v-if="activeTab === 'gestion'" class="space-y-6">
                        <!-- Barre recherche et filtres design React -->
                        <form
                            class="mb-6 flex flex-wrap items-center justify-between gap-4"
                            @submit.prevent="filtrer('search', searchQuery)"
                        >
                            <div class="flex flex-wrap items-center gap-4">
                                <div class="relative">
                                    <Search
                                        class="absolute left-3 top-1/2 size-[18px] -translate-y-1/2 text-[#5C5959]"
                                    />
                                    <input
                                        v-model="searchQuery"
                                        type="text"
                                        placeholder="Recherche commandes (Médicaments, téléphone, Noms,...)"
                                        title="Recherche par médicaments, téléphone, nom client..."
                                        class="w-full min-w-[240px] max-w-[420px] rounded-[13px] border-0 bg-white py-2.5 pl-10 pr-4 text-[14px] shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3995d2]"
                                    />
                                </div>
                                <span
                                    class="text-[14px] font-bold text-white/90"
                                    >Période</span
                                >
                                <select
                                    :value="filters.periode ?? ''"
                                    class="rounded-[13px] border-0 bg-white px-4 py-2.5 text-[14px] font-medium shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3995d2]"
                                    @change="
                                        (e: Event) =>
                                            filtrer(
                                                'periode',
                                                (e.target as HTMLSelectElement)
                                                    .value,
                                            )
                                    "
                                >
                                    <option value="">Toutes les dates</option>
                                    <option value="aujourdhui">
                                        Aujourd'hui
                                    </option>
                                    <option value="semaine">
                                        Cette semaine
                                    </option>
                                    <option value="mois">Ce mois</option>
                                </select>
                            </div>
                            <div
                                class="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-4"
                            >
                                <input
                                    :value="filters.date ?? ''"
                                    type="date"
                                    title="Une date précise remplace le filtre période"
                                    class="w-[180px] rounded-[13px] border-0 bg-white px-4 py-2.5 text-[14px] shadow-sm focus:outline-none focus:ring-2 focus:ring-[#3995d2]"
                                    @input="
                                        (e: Event) =>
                                            filtrer(
                                                'date',
                                                (e.target as HTMLInputElement)
                                                    .value,
                                            )
                                    "
                                />
                                <span
                                    class="hidden text-[11px] text-white/70 sm:inline"
                                    >ou date précise (exclut la période)</span
                                >
                                <button
                                    v-if="canCreateCommande"
                                    type="button"
                                    class="flex items-center gap-2 rounded-[11px] bg-[#0d6efd] px-6 py-2.5 text-[16px] font-bold text-white shadow-sm transition-colors hover:bg-[#0b5ed7]"
                                    @click="openEnregistrementModal"
                                >
                                    <Plus class="size-5" />
                                    Nouvelles Commandes
                                </button>
                            </div>
                        </form>

                        <!-- Filtre statut style Figma -->
                        <div class="mb-6 flex flex-wrap items-center gap-3">
                            <button
                                class="rounded-[13px] px-4 py-2 text-[14px] font-bold transition-all"
                                :style="{
                                    backgroundColor: !filters.status
                                        ? '#0d6efd'
                                        : 'transparent',
                                    color: !filters.status
                                        ? 'white'
                                        : '#0d6efd',
                                    border: !filters.status
                                        ? 'none'
                                        : '1px solid #0d6efd',
                                }"
                                @click="filtrer('status', '')"
                            >
                                Toutes
                            </button>
                            <button
                                v-for="s in statuts"
                                :key="s.key"
                                class="shrink-0 rounded-[13px] px-4 py-2 text-[14px] font-bold transition-all whitespace-nowrap"
                                :style="{
                                    backgroundColor:
                                        filters.status === s.key
                                            ? s.color
                                            : 'transparent',
                                    color:
                                        filters.status === s.key
                                            ? s.textColor
                                            : ((s as { borderColor?: string })
                                                  .borderColor ?? s.color),
                                    border:
                                        filters.status === s.key
                                            ? 'none'
                                            : `1px solid ${(s as { borderColor?: string }).borderColor ?? s.color}`,
                                }"
                                @click="
                                    filtrer(
                                        'status',
                                        filters.status === s.key ? '' : s.key,
                                    )
                                "
                            >
                                {{ s.label }} ({{ stats[s.statsKey] ?? 0 }})
                            </button>
                        </div>

                        <CommandesTable
                            :commandes="commandes"
                            :stats="stats ?? {}"
                            :filters="filters ?? {}"
                            :statuts="statuts"
                            :selected-ids="selectedIds"
                            :all-selected="allSelected"
                            :some-selected="someSelected"
                            :can-create-commande="canCreateCommande"
                            @toggle-all="toggleAll"
                            @toggle-one="toggleOne"
                            @clear-selection="clearSelection"
                            @export-csv="exportSelectedCSV"
                            @open-bulk-annuler-modal="openBulkAnnulerModal"
                            @open-detail="openDetail"
                        />
                    </div>

                    <div
                        v-else
                        class="rounded-xl border bg-white p-8 text-center"
                    >
                        <p class="text-muted-foreground">
                            Statistiques – à venir
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Détails -> Remplacée par un Sheet (Tiroir) -->
        <Sheet :open="showDetailModal" @update:open="showDetailModal = $event">
            <SheetContent
                :show-close-button="false"
                class="w-full max-h-[100dvh] min-h-0 sm:max-w-[500px] md:max-w-[540px] overflow-y-auto overflow-x-hidden bg-[#fafafa] p-0 border-l-0 shadow-2xl"
                @pointer-down-outside="closeDetail"
            >
                <SheetHeader class="sr-only">
                    <SheetTitle>Détails de la commande</SheetTitle>
                </SheetHeader>

                <div
                    v-if="loadingDetail"
                    class="animate-in fade-in-0 slide-in-from-right-2 space-y-4 p-6 duration-200"
                    aria-busy="true"
                    aria-label="Chargement du détail commande"
                >
                    <div class="flex items-center gap-3 text-sm text-muted-foreground">
                        <svg
                            class="size-5 shrink-0 animate-spin text-[#0d6efd]"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            />
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            />
                        </svg>
                        <span>Chargement…</span>
                    </div>
                    <div
                        class="h-16 rounded-2xl bg-gradient-to-r from-gray-100 via-gray-50 to-gray-100 dark:from-gray-800 dark:via-gray-900 dark:to-gray-800 animate-pulse"
                    />
                    <div class="space-y-3">
                        <div
                            class="h-36 rounded-2xl bg-muted/70 animate-pulse"
                        />
                        <div
                            class="h-28 rounded-2xl bg-muted/60 animate-pulse"
                        />
                        <div
                            class="h-24 rounded-2xl bg-muted/50 animate-pulse"
                        />
                    </div>
                </div>

                <div v-else-if="detailCommande">
                    <!-- En-tête (défile avec le reste) -->
                    <div
                        class="flex items-center justify-between gap-3 border-b border-gray-100 bg-white px-4 py-4 shadow-sm sm:px-6"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="text-[18px] font-bold text-gray-800">
                                {{ detailCommande.numero }}
                            </p>
                            <p class="text-[13px] font-medium text-gray-500">
                                Date :
                                {{ formatDateHeureCommande(detailCommande) }}
                            </p>
                        </div>
                        <span
                            class="shrink-0 rounded-full px-3 py-1 text-[12px] font-bold"
                            :style="getStatusBadgeStyle(detailCommande.status)"
                        >
                            {{ getStatusLabel(detailCommande.status) }}
                        </span>
                        <button
                            type="button"
                            class="shrink-0 rounded-lg p-2 text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-900"
                            aria-label="Fermer le panneau"
                            @click="closeDetail"
                        >
                            <X class="size-5" />
                        </button>
                    </div>

                    <div class="space-y-4 p-6 pb-8">
                        <!-- Informations du client -->
                        <div
                            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"
                        >
                            <h3
                                class="mb-3 text-[14px] font-bold text-[#b4b4b4]"
                            >
                                Informations du client
                            </h3>
                            <div
                                class="flex flex-col gap-1.5 md:flex-row md:items-center md:justify-between mb-2"
                            >
                                <p class="text-[15px] font-bold text-gray-900">
                                    <span class="font-normal text-gray-500 mr-1"
                                        >Nom :</span
                                    >
                                    {{
                                        getClientDisplayName(
                                            detailCommande.client,
                                        )
                                    }}
                                </p>
                                <p class="text-[14px] font-bold text-gray-800">
                                    <span class="font-normal text-gray-500 mr-1"
                                        >Tél :</span
                                    >
                                    {{ detailCommande.client?.tel || '-' }}
                                </p>
                            </div>
                            <p class="text-[14px] text-gray-600">
                                <span class="text-gray-500">Adresse :</span>
                                {{ detailCommande.client?.adresse || '-' }}
                            </p>
                        </div>

                        <!-- Pharmacie -->
                        <div
                            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"
                        >
                            <h3
                                class="mb-3 text-[14px] font-bold text-[#b4b4b4]"
                            >
                                Pharmacie
                            </h3>
                            <p class="text-[15px] font-bold text-gray-900 mb-1">
                                {{
                                    detailCommande.pharmacie?.designation || '-'
                                }}
                            </p>
                            <p class="text-[13px] text-gray-500">
                                Adresse :
                                {{ detailCommande.pharmacie?.adresse || '-' }}
                            </p>
                        </div>

                        <div
                            v-if="
                                detailCommande.enfants?.length &&
                                detailCommande.status === 'en_attente'
                            "
                            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"
                        >
                            <h3
                                class="mb-2 text-[14px] font-bold text-[#b4b4b4]"
                            >
                                Commandes associées (autres pharmacies)
                            </h3>
                            <p class="mb-3 text-[12px] leading-relaxed text-gray-500">
                                Avant validation globale, le montant de livraison
                                et le mode de paiement doivent être choisis pour
                                chaque maillon encore « en attente ».
                            </p>
                            <ul class="space-y-2">
                                <li
                                    v-for="e in detailCommande.enfants"
                                    :key="e.id"
                                    class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-gray-100 px-3 py-2"
                                >
                                    <div class="min-w-0 flex-1">
                                        <p
                                            class="text-[13px] font-bold text-gray-900"
                                        >
                                            {{ e.numero }}
                                            <span
                                                class="ml-2 inline-block rounded-full px-2 py-0.5 text-[10px] font-bold capitalize"
                                                :class="
                                                    e.status === 'en_attente'
                                                        ? 'bg-amber-100 text-amber-900'
                                                        : 'bg-gray-100 text-gray-600'
                                                "
                                            >
                                                {{
                                                    getStatusLabel(e.status)
                                                }}</span
                                            >
                                        </p>
                                        <p
                                            class="truncate text-[12px] text-gray-600"
                                        >
                                            {{
                                                e.pharmacie?.designation ?? '—'
                                            }}
                                            <span
                                                v-if="
                                                    e.status ===
                                                        'en_attente' &&
                                                    (!e.montant_livraison ||
                                                        !e.mode_paiement)
                                                "
                                                class="ml-1 font-medium text-amber-700"
                                            >
                                                — livraison / paiement manquant
                                            </span>
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        class="shrink-0 rounded-lg bg-[#0d6efd]/10 px-3 py-1.5 text-[12px] font-bold text-[#0d6efd] transition-colors hover:bg-[#0d6efd]/20"
                                        @click="openDetail(e.id)"
                                    >
                                        Ouvrir
                                    </button>
                                </li>
                            </ul>
                        </div>

                        <!-- Médicaments -->
                        <div
                            v-if="detailSplit.medicaments.length"
                            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"
                        >
                            <h3
                                class="mb-3 text-[14px] font-bold text-[#b4b4b4]"
                            >
                                Médicaments
                            </h3>
                            <div class="space-y-4">
                                <div
                                    v-for="p in detailSplit.medicaments"
                                    :key="p.id"
                                    class="border-b border-dashed border-gray-200 pb-3 last:border-0 last:pb-0"
                                >
                                    <p
                                        class="mb-1 text-[15px] font-bold text-gray-900"
                                    >
                                        {{ p.designation }} {{ p.dosage ?? '' }}
                                    </p>
                                    <p
                                        v-if="p.forme"
                                        class="mb-2 text-[13px] text-gray-600"
                                    >
                                        Forme :
                                        <span class="font-medium text-gray-800">{{
                                            p.forme
                                        }}</span>
                                    </p>
                                    <div
                                        class="flex items-center justify-between gap-4"
                                    >
                                        <div class="text-[13px] text-gray-600">
                                            <p>
                                                Quantité :
                                                {{ p.pivot.quantite }}
                                            </p>
                                            <p>
                                                Prix unitaire :
                                                {{
                                                    Number(
                                                        p.pivot.prix_unitaire,
                                                    ).toLocaleString('fr-FR')
                                                }}
                                                FCFA
                                            </p>
                                        </div>
                                        <div
                                            class="flex flex-col items-end gap-2"
                                        >
                                            <span
                                                class="rounded-full border px-3 py-0.5 text-[11px] font-bold"
                                                :class="classesPivotStatusProduit(p.pivot.status)"
                                            >
                                                {{
                                                    libellePivotStatusProduit(
                                                        p.pivot.status,
                                                    )
                                                }}
                                            </span>
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <span
                                                    class="text-[11px] font-medium text-gray-500"
                                                    >En vente libre</span
                                                >
                                                <span
                                                    class="relative inline-flex h-5 w-9 items-center rounded-full"
                                                    :class="
                                                        estVenteLibrePivot(
                                                            p.pivot.vente_libre,
                                                        )
                                                            ? 'bg-[#22C55E]'
                                                            : 'bg-gray-200'
                                                    "
                                                >
                                                    <span
                                                        class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow"
                                                        :class="
                                                            estVenteLibrePivot(
                                                                p.pivot
                                                                    .vente_libre,
                                                            )
                                                                ? 'translate-x-4'
                                                                : 'translate-x-0.5'
                                                        "
                                                    />
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Parapharmacie -->
                        <div
                            v-if="detailSplit.parapharma.length"
                            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"
                        >
                            <h3
                                class="mb-3 text-[14px] font-bold text-[#b4b4b4]"
                            >
                                Parapharmacie
                            </h3>
                            <div class="space-y-4">
                                <div
                                    v-for="p in detailSplit.parapharma"
                                    :key="`para-${p.id}`"
                                    class="border-b border-dashed border-gray-200 pb-3 last:border-0 last:pb-0"
                                >
                                    <p
                                        class="mb-1 text-[15px] font-bold text-gray-900"
                                    >
                                        {{ p.designation }}
                                    </p>
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <div class="text-[13px] text-gray-600">
                                            <p>
                                                Quantité :
                                                {{ p.pivot.quantite }}
                                            </p>
                                            <p>
                                                Prix unitaire :
                                                {{
                                                    Number(
                                                        p.pivot.prix_unitaire,
                                                    ).toLocaleString('fr-FR')
                                                }}
                                                FCFA
                                            </p>
                                        </div>
                                        <span
                                            class="rounded-full border px-3 py-0.5 text-[11px] font-bold"
                                            :class="classesPivotStatusProduit(p.pivot.status)"
                                        >
                                            {{
                                                libellePivotStatusProduit(
                                                    p.pivot.status,
                                                )
                                            }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Ordonnance -->
                        <div
                            class="rounded-2xl border border-dashed border-gray-300 bg-white p-5 shadow-sm transition-colors hover:border-[#0d6efd]"
                        >
                            <h3
                                class="mb-3 text-[14px] font-bold text-[#b4b4b4]"
                            >
                                Ordonnance
                            </h3>
                            <div
                                v-if="detailCommande.ordonnance?.file_url"
                                class="flex justify-center"
                            >
                                <OrdonnanceViewer
                                    :file-url="detailCommande.ordonnance.file_url"
                                    :is-pdf="detailCommande.ordonnance.is_pdf"
                                    max-height="15rem"
                                />
                            </div>
                            <div
                                v-else
                                class="flex h-24 items-center justify-center text-[13px] font-medium text-gray-400"
                            >
                                Aucune ordonnance fournie
                            </div>
                            <div
                                v-if="
                                    isAgent && detailCommande.ordonnance?.file_url
                                "
                                class="mt-4 rounded-lg border border-gray-200 bg-gray-50/80 p-3 text-left text-sm"
                            >
                                <template
                                    v-if="detailCommande.ordonnance.verification"
                                >
                                    <OrdonnanceAnalysisProgressBar
                                        class="mb-3"
                                        :visible="
                                            detailCommande.ordonnance
                                                .verification.status ===
                                                'pending' ||
                                            detailCommande.ordonnance
                                                .verification.status ===
                                                'processing'
                                        "
                                        label="Analyse OCR et règles en cours…"
                                    />
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span
                                            class="font-medium text-gray-700"
                                            >Vérification (OCR + règles)</span
                                        >
                                        <span
                                            v-if="
                                                detailCommande.ordonnance
                                                    .verification.score !==
                                                null
                                            "
                                            class="rounded-full bg-white px-2 py-0.5 text-xs font-bold tabular-nums"
                                        >
                                            {{
                                                detailCommande.ordonnance
                                                    .verification.score
                                            }}
                                            %
                                        </span>
                                        <span
                                            class="cursor-help rounded-full px-2 py-0.5 text-xs font-semibold"
                                            :title="
                                                descriptionDecisionVerification(
                                                    detailCommande.ordonnance
                                                        .verification.decision,
                                                )
                                            "
                                            :class="{
                                                'bg-emerald-100 text-emerald-800':
                                                    detailCommande.ordonnance
                                                        .verification
                                                        .decision === 'pass',
                                                'bg-amber-100 text-amber-900':
                                                    detailCommande.ordonnance
                                                        .verification
                                                        .decision ===
                                                        'review' ||
                                                    detailCommande.ordonnance
                                                        .verification
                                                        .decision ===
                                                        'skipped',
                                                'bg-red-100 text-red-800':
                                                    detailCommande.ordonnance
                                                        .verification
                                                        .decision === 'fail',
                                                'bg-gray-200 text-gray-700':
                                                    detailCommande.ordonnance
                                                        .verification
                                                        .decision ===
                                                        'pending',
                                            }"
                                        >
                                            {{
                                                libelleDecisionVerification(
                                                    detailCommande.ordonnance
                                                        .verification.decision,
                                                )
                                            }}
                                        </span>
                                    </div>
                                    <p
                                        v-if="
                                            detailCommande.ordonnance
                                                .verification.status ===
                                            'pending'
                                        "
                                        class="mt-2 text-xs text-amber-800"
                                    >
                                        File d’analyse : mise à jour automatique
                                        de cette section.
                                    </p>
                                    <p
                                        v-else-if="
                                            detailCommande.ordonnance
                                                .verification.status ===
                                            'processing'
                                        "
                                        class="mt-2 text-xs text-amber-800"
                                    >
                                        Traitement en cours sur le serveur…
                                    </p>
                                    <p
                                        v-if="
                                            detailCommande.ordonnance
                                                .verification
                                                .parsed_prescription_date
                                        "
                                        class="mt-2 text-xs text-gray-600"
                                    >
                                        Date extraite :
                                        {{
                                            detailCommande.ordonnance
                                                .verification
                                                .parsed_prescription_date
                                        }}
                                    </p>
                                    <ul
                                        v-if="
                                            detailCommande.ordonnance
                                                .verification.rule_results
                                        "
                                        class="mt-2 space-y-1 text-xs text-gray-600"
                                    >
                                        <li
                                            v-for="(info, key) in detailCommande
                                                .ordonnance.verification
                                                .rule_results"
                                            :key="key"
                                            class="flex gap-2"
                                        >
                                            <template
                                                v-if="
                                                    info &&
                                                    typeof info === 'object' &&
                                                    'pass' in info
                                                "
                                            >
                                                <span
                                                    :class="
                                                        info.pass
                                                            ? 'text-emerald-600'
                                                            : 'text-gray-400 line-through'
                                                    "
                                                    >{{ info.label }}</span
                                                >
                                            </template>
                                            <template
                                                v-else-if="
                                                    typeof info === 'string'
                                                "
                                            >
                                                <span class="text-gray-600">{{
                                                    info
                                                }}</span>
                                            </template>
                                        </li>
                                    </ul>
                                    <p
                                        v-if="
                                            detailCommande.ordonnance
                                                .verification.error_message
                                        "
                                        class="mt-2 text-xs text-red-600"
                                    >
                                        {{
                                            detailCommande.ordonnance
                                                .verification.error_message
                                        }}
                                    </p>
                                </template>
                                <div v-else>
                                    <span class="font-medium text-gray-700"
                                        >Vérification (OCR + règles)</span
                                    >
                                    <p class="mt-2 text-xs text-gray-600">
                                        Aucune analyse enregistrée pour ce
                                        fichier (données antérieures ou envoi
                                        hors compte agent / admin).
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Commentaires commande / pharmacien -->
                        <div
                            class="space-y-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"
                        >
                            <div>
                                <h3
                                    class="mb-3 text-[14px] font-bold text-[#b4b4b4]"
                                >
                                    Commentaire (back-office)
                                </h3>
                                <p
                                    class="text-[14px] text-gray-700 whitespace-pre-wrap leading-relaxed"
                                >
                                    {{
                                        detailCommande.commentaire ||
                                        'Aucun commentaire.'
                                    }}
                                </p>
                            </div>
                            <div
                                v-if="
                                    (
                                        detailCommande.commentaire_pharmacie ??
                                        ''
                                    ).trim() !== ''
                                "
                            >
                                <h3
                                    class="mb-3 text-[14px] font-bold text-[#b4b4b4]"
                                >
                                    Commentaires du pharmacien
                                </h3>
                                <p
                                    class="text-[14px] text-gray-700 whitespace-pre-wrap leading-relaxed"
                                >
                                    {{ detailCommande.commentaire_pharmacie }}
                                </p>
                            </div>
                        </div>

                        <!-- Bloc annulation (commande annulée) -->
                        <div
                            v-if="detailCommande.status === 'annulee'"
                            class="rounded-2xl border-2 border-red-200 bg-red-50 p-5 shadow-sm"
                        >
                            <div class="mb-4 flex items-start gap-3">
                                <AlertTriangle
                                    class="mt-0.5 size-6 shrink-0 text-red-600"
                                />
                                <div>
                                    <h3
                                        class="text-[16px] font-bold text-red-700"
                                    >
                                        Commande Annulée
                                    </h3>
                                    <p class="mt-1 text-[13px] text-red-600">
                                        Cette commande a été annulée. Consultez
                                        les détails ci-dessous.
                                    </p>
                                </div>
                            </div>
                            <div
                                class="mb-4 flex items-start gap-3 rounded-lg border border-red-200 bg-white/60 p-3"
                            >
                                <XCircle
                                    class="mt-0.5 size-5 shrink-0 text-red-600"
                                />
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="text-[11px] font-bold uppercase tracking-wide text-red-700"
                                    >
                                        Motif d'annulation
                                    </p>
                                    <p
                                        class="mt-1 text-[14px] font-medium text-gray-900"
                                    >
                                        {{
                                            getMotifAnnulationLabel(
                                                detailCommande.motif_annulation,
                                            )
                                        }}
                                    </p>
                                    <p
                                        v-if="detailCommande.note_annulation"
                                        class="mt-2 text-[13px] text-gray-600 whitespace-pre-wrap"
                                    >
                                        {{ detailCommande.note_annulation }}
                                    </p>
                                </div>
                            </div>
                            <button
                                v-if="
                                    motifAutoriseRelance(
                                        detailCommande.motif_annulation,
                                    ) &&
                                    canCreateCommande &&
                                    !detailCommande.deja_relancee
                                "
                                type="button"
                                class="flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-[#3B82F6] text-[15px] font-bold text-white transition-colors hover:bg-blue-600"
                                @click="openRelancerModal"
                            >
                                <RefreshCw class="size-5" />
                                Relancer la commande
                            </button>
                        </div>

                        <!-- Informations paiement -->
                        <div
                            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"
                        >
                            <h3
                                class="mb-4 text-[14px] font-bold text-[#b4b4b4]"
                            >
                                Informations paiement
                            </h3>

                            <div
                                class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <span class="text-[13px] text-gray-500"
                                    >Mode de paiement</span
                                >
                                <template
                                    v-if="
                                        detailCommande.status ===
                                            'en_attente' &&
                                        canCreateCommande &&
                                        modesPaiement.length &&
                                        !enAttentePharmacieToutIndisponible
                                    "
                                >
                                    <select
                                        class="h-10 min-w-[12rem] max-w-full rounded-xl border border-gray-200 bg-white px-3 text-[13px] font-semibold text-gray-900 focus:border-[#0d6efd] focus:outline-none focus:ring-1 focus:ring-[#0d6efd]"
                                        :value="
                                            detailCommande.mode_paiement?.id ??
                                            ''
                                        "
                                        @change="
                                            ($event) => {
                                                const v = (
                                                    $event.target as HTMLSelectElement
                                                ).value;
                                                if (v)
                                                    setModePaiementCommande(
                                                        Number(v),
                                                    );
                                            }
                                        "
                                    >
                                        <option value="" disabled>
                                            Choisir un mode
                                        </option>
                                        <option
                                            v-for="m in modesPaiement"
                                            :key="m.id"
                                            :value="m.id"
                                        >
                                            {{ m.designation }}
                                        </option>
                                    </select>
                                </template>
                                <template v-else>
                                    <span
                                        v-if="detailCommande.mode_paiement"
                                        class="rounded-full border border-[#016630] bg-[#e1f3e7] px-3 py-1 text-[12px] font-bold text-[#016630]"
                                    >
                                        {{
                                            detailCommande.mode_paiement
                                                .designation
                                        }}
                                    </span>
                                    <span
                                        v-else
                                        class="text-[13px] font-medium text-gray-400"
                                        >Non défini</span
                                    >
                                </template>
                            </div>

                            <div class="space-y-2 text-[14px]">
                                <div
                                    v-if="detailSplit.sousTotalMedicaments > 0"
                                    class="flex items-center justify-between"
                                >
                                    <span class="text-gray-500"
                                        >Sous-total médicaments</span
                                    >
                                    <span class="font-bold text-gray-900"
                                        >{{
                                            detailSplit.sousTotalMedicaments.toLocaleString(
                                                'fr-FR',
                                            )
                                        }}
                                        FCFA</span
                                    >
                                </div>
                                <div
                                    v-if="detailSplit.sousTotalParapharma > 0"
                                    class="flex items-center justify-between"
                                >
                                    <span class="text-gray-500"
                                        >Sous-total parapharmacie</span
                                    >
                                    <span class="font-bold text-gray-900"
                                        >{{
                                            detailSplit.sousTotalParapharma.toLocaleString(
                                                'fr-FR',
                                            )
                                        }}
                                        FCFA</span
                                    >
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-500"
                                        >Sous-total</span
                                    >
                                    <span class="font-extrabold text-gray-900"
                                        >{{
                                            sousTotal().toLocaleString('fr-FR')
                                        }}
                                        FCFA</span
                                    >
                                </div>

                                <div
                                    v-if="
                                        detailCommande.status ===
                                            'en_attente' &&
                                        !detailCommande.montant_livraison &&
                                        !enAttentePharmacieToutIndisponible
                                    "
                                    class="flex flex-col gap-2 pt-1 border-t border-gray-100"
                                >
                                    <span class="text-gray-500"
                                        >Définir Livraison :</span
                                    >
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            v-for="m in montantsLivraison"
                                            :key="m.id"
                                            type="button"
                                            class="rounded-full border border-gray-200 bg-gray-50 px-3 py-1.5 text-[12px] font-bold text-gray-700 transition-colors hover:border-[#0d6efd] hover:bg-blue-50 hover:text-[#0d6efd]"
                                            @click.stop="
                                                setMontantLivraison(m.id)
                                            "
                                        >
                                            {{
                                                Number(
                                                    m.designation,
                                                ).toLocaleString('fr-FR')
                                            }}
                                            FCFA
                                        </button>
                                    </div>
                                </div>
                                <div
                                    v-else-if="detailCommande.montant_livraison"
                                    class="flex items-center justify-between"
                                >
                                    <span class="text-gray-500">Livraison</span>
                                    <span class="font-extrabold text-gray-900"
                                        >{{
                                            Number(
                                                detailCommande.montant_livraison
                                                    .designation,
                                            ).toLocaleString('fr-FR')
                                        }}
                                        FCFA</span
                                    >
                                </div>

                                <div
                                    class="flex items-center justify-between pt-2 border-t border-gray-100 mt-2"
                                >
                                    <span class="font-bold text-gray-900"
                                        >Total</span
                                    >
                                    <span
                                        class="text-[16px] font-extrabold text-gray-900"
                                        >{{
                                            totalDetail().toLocaleString(
                                                'fr-FR',
                                            )
                                        }}
                                        FCFA</span
                                    >
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="
                                detailCommande.livreur ||
                                (canCreateCommande &&
                                    peutAssignerLivreurDetail() &&
                                    !enAttentePharmacieToutIndisponible)
                            "
                            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"
                        >
                            <h3
                                class="mb-3 text-[14px] font-bold text-[#b4b4b4]"
                            >
                                Livreur
                            </h3>
                            <div
                                v-if="
                                    canCreateCommande &&
                                    peutAssignerLivreurDetail() &&
                                    !enAttentePharmacieToutIndisponible
                                "
                                class="flex flex-col gap-2"
                            >
                                <label
                                    class="text-[13px] font-medium text-gray-600"
                                    for="detail-livreur-select"
                                    >Attribuer un livreur</label
                                >
                                <select
                                    id="detail-livreur-select"
                                    class="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-[14px] text-gray-900 focus:border-[#0d6efd] focus:outline-none focus:ring-1 focus:ring-[#0d6efd]"
                                    :value="detailCommande.livreur?.id ?? ''"
                                    @change="
                                        setLivreurCommande(
                                            ($event.target as HTMLSelectElement)
                                                .value
                                                ? Number(
                                                      (
                                                          $event.target as HTMLSelectElement
                                                      ).value,
                                                  )
                                                : null,
                                        )
                                    "
                                >
                                    <option value="">Aucun livreur</option>
                                    <option
                                        v-for="l in livreurs"
                                        :key="l.id"
                                        :value="l.id"
                                    >
                                        {{ l.prenom }} {{ l.nom }} — {{ l.tel }}
                                    </option>
                                </select>
                            </div>
                            <p
                                v-else-if="detailCommande.livreur"
                                class="text-[14px] font-medium text-gray-900"
                            >
                                {{ detailCommande.livreur.prenom }}
                                {{ detailCommande.livreur.nom }}
                                <span
                                    class="block text-[13px] font-normal text-gray-500"
                                    >{{ detailCommande.livreur.tel }}</span
                                >
                            </p>
                            <p v-else class="text-[13px] text-gray-500">
                                Aucun livreur assigné.
                            </p>
                        </div>

                        <!-- Actions (suite du scroll, pas de barre fixe) -->
                        <div class="border-t border-gray-200 bg-white pt-5">
                            <div class="flex flex-col gap-3">
                                <template
                                    v-if="
                                        detailCommande.status === 'en_attente'
                                    "
                                >
                                    <!-- Explications toujours au-dessus des boutons d'action -->
                                    <div
                                        v-if="
                                            enAttentePharmacieToutIndisponible ||
                                            !detailCommande.montant_livraison ||
                                            (!!detailCommande.montant_livraison &&
                                                !detailCommande.mode_paiement) ||
                                            enfantEnAttenteSansPaiementComplet
                                        "
                                        class="flex flex-col gap-2"
                                    >
                                        <p
                                            v-if="
                                                enAttentePharmacieToutIndisponible
                                            "
                                            class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-[13px] font-medium text-amber-900"
                                        >
                                            Aucun médicament disponible :
                                            annulez la commande ou faites-la
                                            renvoyer vers une autre pharmacie
                                            (agent).
                                        </p>
                                        <p
                                            v-else-if="
                                                !detailCommande.montant_livraison
                                            "
                                            class="text-center text-[12px] font-medium text-amber-800"
                                        >
                                            Définissez le montant de la
                                            livraison (section paiement
                                            ci-dessus) avant de valider.
                                        </p>
                                        <p
                                            v-else-if="
                                                !detailCommande.mode_paiement
                                            "
                                            class="text-center text-[12px] font-medium text-amber-800"
                                        >
                                            Choisissez un mode de paiement
                                            (section paiement ci-dessus) avant
                                            de valider.
                                        </p>
                                        <p
                                            v-else-if="
                                                enfantEnAttenteSansPaiementComplet
                                            "
                                            class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-left text-[12px] font-medium text-amber-900"
                                        >
                                            Une ou plusieurs commandes associées
                                            (autre pharmacie) sont encore en
                                            attente sans frais de livraison ou
                                            sans mode de paiement. Ouvrez chaque
                                            fiche associée (recherche par N°) et
                                            complétez ces champs avant de
                                            valider l’ensemble.
                                        </p>
                                    </div>
                                    <div class="flex flex-col gap-3">
                                        <button
                                            v-if="
                                                !enAttentePharmacieToutIndisponible
                                            "
                                            type="button"
                                            :disabled="
                                                !peutValiderCommandeEnAttente
                                            "
                                            class="flex h-12 w-full items-center justify-center rounded-full text-[15px] font-bold text-white transition-colors"
                                            :class="
                                                peutValiderCommandeEnAttente
                                                    ? 'bg-[#0d6efd] hover:bg-blue-700'
                                                    : 'cursor-not-allowed bg-gray-400'
                                            "
                                            @click="openValiderModal"
                                        >
                                            Valider
                                        </button>
                                        <button
                                            type="button"
                                            class="flex h-12 w-full flex-row items-center justify-center rounded-full bg-[#e7000b] text-[15px] font-bold text-white transition-colors hover:bg-red-700"
                                            @click="openAnnulerModal"
                                        >
                                            Annuler
                                        </button>
                                    </div>
                                </template>

                                <template
                                    v-else-if="
                                        detailCommande.status === 'validee' ||
                                        detailCommande.status === 'a_preparer'
                                    "
                                >
                                    <button
                                        type="button"
                                        class="flex h-12 w-full items-center justify-center rounded-full bg-[#016630] text-[15px] font-bold text-white transition-colors hover:bg-green-800 focus:outline-none"
                                        @click="updateStatus('retiree')"
                                    >
                                        <Truck class="mr-2 size-5" />
                                        Livrée
                                    </button>
                                    <button
                                        type="button"
                                        class="flex h-12 w-full flex-row items-center justify-center rounded-full bg-[#e7000b] text-[15px] font-bold text-white transition-colors hover:bg-red-700"
                                        @click="openAnnulerModal"
                                    >
                                        Annuler
                                    </button>
                                </template>

                                <template
                                    v-else-if="
                                        detailCommande.status === 'retiree'
                                    "
                                >
                                    <button
                                        type="button"
                                        class="flex h-12 w-full items-center justify-center rounded-full bg-[#0d6efd] text-[15px] font-bold text-white transition-colors hover:bg-blue-700"
                                        @click="showRecuModal = true"
                                    >
                                        <FileText class="mr-2 size-5" />
                                        Générer le reçu
                                    </button>
                                    <button
                                        type="button"
                                        class="flex h-12 w-full items-center justify-center rounded-full bg-gray-400 text-[15px] font-bold text-white cursor-not-allowed"
                                    >
                                        <Truck class="mr-2 size-5" />
                                        Livrée
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </SheetContent>
        </Sheet>

        <!-- Modal confirmation validation commande -->
        <Dialog
            :open="showValiderModal"
            @update:open="showValiderModal = $event"
        >
            <DialogContent class="max-w-[440px]">
                <DialogHeader>
                    <DialogTitle
                        class="flex items-center gap-2 text-lg font-bold text-gray-900"
                    >
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-full bg-[#0d6efd]/15"
                        >
                            <CheckCircle2 class="size-6 text-[#0d6efd]" />
                        </span>
                        Valider la commande
                    </DialogTitle>
                </DialogHeader>
                <p class="text-[14px] leading-relaxed text-gray-600">
                    Confirmez-vous la validation de la commande
                    <span class="font-semibold text-gray-900">{{
                        detailCommande?.numero
                    }}</span>
                    ? Le statut passera à « Validée » et la pharmacie pourra
                    préparer la commande. Les frais de livraison et le mode de
                    paiement doivent être renseignés sur cette commande et sur
                    toute commande liée encore en attente.
                </p>
                <DialogFooter
                    class="mt-2 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"
                >
                    <Button
                        type="button"
                        variant="outline"
                        class="rounded-[10px] border-gray-300"
                        @click="showValiderModal = false"
                    >
                        Retour
                    </Button>
                    <Button
                        type="button"
                        class="rounded-[10px] bg-[#0d6efd] font-bold text-white hover:bg-blue-700"
                        :disabled="!peutValiderCommandeEnAttente"
                        @click="confirmValiderCommande"
                    >
                        Confirmer la validation
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Modal Annuler -->
        <Dialog
            :open="showAnnulerModal"
            @update:open="showAnnulerModal = $event"
        >
            <DialogContent
                class="max-h-[70vh] max-w-[500px] flex flex-col overflow-hidden"
            >
                <DialogHeader class="shrink-0">
                    <DialogTitle
                        class="flex items-center gap-2 text-[#666] text-lg font-black"
                    >
                        <div
                            class="flex size-8 shrink-0 items-center justify-center rounded-full bg-[#e7000b]"
                        >
                            <AlertTriangle class="size-4 text-white" />
                        </div>
                        <span class="text-[#e7000b]"
                            >Annuler la commande
                            {{ detailCommande?.numero }}</span
                        >
                    </DialogTitle>
                </DialogHeader>
                <p class="shrink-0 text-[13px] text-black leading-snug">
                    Sélectionner le motif d’annulation. Si les médicaments sont
                    indisponibles ou selon la configuration du motif, vous
                    pouvez relancer la commande avec une autre pharmacie.
                </p>
                <div class="min-h-0 flex-1 space-y-1.5 overflow-y-auto">
                    <p class="text-sm font-black text-black">
                        Motif d'annulation <span class="text-[#e7000b]">*</span>
                    </p>
                    <div
                        v-for="opt in motifOptions"
                        :key="opt.key"
                        class="relative flex min-h-[52px] cursor-pointer flex-col justify-center rounded-[8px] border border-[rgba(92,89,89,0.25)] px-3 py-1.5 pr-9 transition-colors"
                        :class="
                            motifAnnulation === opt.key
                                ? 'border-[#e7000b] bg-[rgba(231,0,11,0.2)]'
                                : 'bg-[rgba(231,0,11,0.13)] hover:bg-[rgba(231,0,11,0.18)]'
                        "
                        @click="motifAnnulation = opt.key"
                    >
                        <div
                            v-if="motifAnnulation === opt.key"
                            class="absolute right-2 top-2 flex size-5 items-center justify-center rounded-full bg-[#e7000b]"
                        >
                            <Check class="size-3 text-white" stroke-width="3" />
                        </div>
                        <p class="text-[13px] font-bold text-black">
                            {{ opt.label }}
                        </p>
                        <p class="mt-0.5 text-[12px] font-light text-black">
                            {{ opt.desc }}
                        </p>
                    </div>
                </div>
                <div class="shrink-0 space-y-1">
                    <p class="text-sm font-black text-black">
                        Note complémentaire
                        <span class="font-normal">(optionnel)</span>
                    </p>
                    <textarea
                        v-model="noteAnnulation"
                        rows="2"
                        placeholder="Ajouter des détails supplémentaires sur l'annulation..."
                        class="w-full rounded-[10px] border border-[rgba(92,89,89,0.25)] bg-[rgba(0,0,0,0.11)] px-3 py-1.5 text-[13px] placeholder:text-black/60 focus:outline-none focus:ring-2 focus:ring-[#e7000b]/50"
                    />
                </div>
                <DialogFooter class="shrink-0 block space-y-0 p-0 sm:p-0">
                    <div
                        class="flex w-full flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <Button
                            v-if="
                                motifAnnulation &&
                                motifAutoriseRelance(motifAnnulation) &&
                                canCreateCommande
                            "
                            class="h-9 min-w-[180px] rounded-[10px] bg-[#0d6efd] text-sm font-black text-white hover:bg-blue-700"
                            :disabled="!motifAnnulation"
                            @click="confirmAnnulerEtRelancer"
                        >
                            Relancer la commande
                        </Button>
                        <div
                            class="flex flex-wrap justify-end gap-3 sm:ml-auto"
                        >
                            <Button
                                variant="outline"
                                class="h-9 rounded-[10px] bg-[rgba(102,102,102,0.13)] px-4 text-sm font-black text-[rgba(0,0,0,0.82)] hover:bg-[rgba(102,102,102,0.2)]"
                                @click="showAnnulerModal = false"
                            >
                                Retour
                            </Button>
                            <Button
                                class="h-9 min-w-[160px] rounded-[10px] bg-[#e7000b] text-sm font-black text-white hover:bg-red-700 disabled:opacity-50"
                                :disabled="!motifAnnulation"
                                @click="confirmAnnuler"
                            >
                                Annuler la commande
                            </Button>
                        </div>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Modal Annulation groupée -->
        <Dialog
            :open="showBulkAnnulerModal"
            @update:open="showBulkAnnulerModal = $event"
        >
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-red-600">
                        <AlertTriangle class="size-5" />
                        Annuler {{ selectedIds.size }} commande(s)
                    </DialogTitle>
                </DialogHeader>
                <p class="text-sm text-muted-foreground mb-4">
                    Choisissez le motif d'annulation pour les commandes
                    sélectionnées.
                </p>
                <div class="space-y-2">
                    <Label>Motif d'annulation *</Label>
                    <select
                        v-model="motifBulkAnnulation"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                    >
                        <option value="">Sélectionner un motif</option>
                        <option
                            v-for="opt in motifOptions"
                            :key="opt.key"
                            :value="opt.key"
                        >
                            {{ opt.label }}
                        </option>
                    </select>
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="showBulkAnnulerModal = false"
                        >Retour</Button
                    >
                    <Button
                        variant="destructive"
                        :disabled="!motifBulkAnnulation"
                        @click="confirmBulkAnnuler"
                    >
                        Annuler les commandes
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Modal Enregistrement -->
        <CommandeEnregistrementModal
            v-model:open="showEnregistrementModal"
            :zones="zones ?? []"
            :pharmacies="pharmacies ?? []"
            :arrondissements="arrondissements ?? []"
            :parapharma-produit-types="parapharma_produit_types ?? []"
            :api-errors="apiErrorsEnreg"
            @submit="submitEnregistrementFromModal"
        />

        <!-- Modal Relancer (même design que Nouvelle commande) -->
        <CommandeEnregistrementModal
            v-model:open="showRelancerModal"
            mode="relance"
            :commande="detailCommande ?? undefined"
            :zones="zones ?? []"
            :pharmacies="pharmacies ?? []"
            :arrondissements="arrondissements ?? []"
            :parapharma-produit-types="parapharma_produit_types ?? []"
            :api-errors="errorsRelancer"
            @submit="submitRelancerFromModal"
        />

        <!-- Modal Reçu commande (design Figma) -->
        <RecuCommandeModal
            v-model:open="showRecuModal"
            :commande="detailCommande"
        />
    </AppLayout>
</template>
