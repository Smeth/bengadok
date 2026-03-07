<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { usePolling } from '@/composables/usePolling';
import {
    Plus,
    Search,
    Eye,
    MoreHorizontal,
    ClipboardList,
    X,
    Link2,
    AlertTriangle,
    Truck,
    FileText,
    Download,
} from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import OrdonnanceViewer from '@/components/OrdonnanceViewer.vue';
import type { BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes';

usePolling();

type CommandeDetail = {
    id: number;
    numero: string;
    date: string;
    heurs?: string;
    status: string;
    prix_total: number;
    commentaire?: string;
    motif_annulation?: string;
    client: { nom: string; prenom: string; tel: string; adresse?: string; sexe?: string };
    pharmacie: { designation: string; telephone: string; adresse: string };
    produits: Array<{
        id: number;
        designation: string;
        dosage?: string;
        pivot: { quantite: number; prix_unitaire: number; status: string };
    }>;
    mode_paiement?: { designation: string };
    montant_livraison?: { designation: number };
    ordonnance?: { urlfile?: string } | null;
};

const props = withDefaults(
    defineProps<{
        commandes?: {
            data: Array<{
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
            }>;
            links: Array<{ url: string | null; label: string; active: boolean }>;
        };
        stats?: Record<string, number>;
        filters?: { search?: string; status?: string; periode?: string; date?: string };
        pharmacies?: Array<{ id: number; designation: string; adresse: string; telephone: string; zone_id?: number; zone?: { id: number; designation: string } }>;
        zones?: Array<{ id: number; designation: string; pharmacies_count: number }>;
        produits?: Array<{ id: number; designation: string; dosage?: string; pu: number }>;
        modesPaiement?: Array<{ id: number; designation: string }>;
        montantsLivraison?: Array<{ id: number; designation: number }>;
    }>(),
    {
        commandes: () => ({ data: [], links: [] }),
        stats: () => ({}),
        filters: () => ({}),
        pharmacies: () => [],
        zones: () => [],
        produits: () => [],
        modesPaiement: () => [],
        montantsLivraison: () => [],
    }
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: dashboard() },
    { title: 'Commandes', href: '/commandes' },
];

const page = usePage();
const canCreateCommande = computed(() => {
    const roles = (page.props.auth as { user?: { roles?: string[] } })?.user?.roles ?? [];
    return roles.some((r) => ['admin', 'super_admin', 'agent_call_center'].includes(r));
});

const searchQuery = ref(props.filters.search ?? '');
const activeTab = ref<'gestion' | 'statistiques'>('gestion');
const detailCommande = ref<CommandeDetail | null>(null);
const showDetailModal = ref(false);
const showEnregistrementModal = ref(false);
const showAnnulerModal = ref(false);
const showRelancerModal = ref(false);
const loadingDetail = ref(false);
const motifAnnulation = ref('');

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
    const headers = ['N°', 'Client', 'Tél', 'Date', 'Adresse', 'Médicaments', 'Montant', 'Statut'];
    const rows = selected.map((c) => [
        c.numero,
        [c.client?.prenom, c.client?.nom].filter(Boolean).join(' ') || '-',
        c.client?.tel ?? '',
        c.date ?? '',
        c.client?.adresse ?? '-',
        getMedicamentsText(c.produits),
        Number(c.prix_total).toLocaleString('fr-FR'),
        getStatusLabel(c.status),
    ]);
    const csv = [headers.join(';'), ...rows.map((r) => r.map((v) => `"${String(v).replace(/"/g, '""')}"`).join(';'))].join('\n');
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
    router.post('/commandes/bulk-annuler', { ids, motif_annulation: motifBulkAnnulation.value }, {
        preserveScroll: true,
        onSuccess: () => {
            showBulkAnnulerModal.value = false;
            clearSelection();
            router.reload();
        },
    });
}

const formEnreg = ref({
    client_nom: '',
    client_prenom: '',
    client_tel: '',
    client_adresse: '',
    pharmacie_id: '',
    beneficiaire: '',
    produits: [] as Array<{ designation: string; dosage: string; quantite: number; prix_unitaire: number }>,
    ordonnance: null as File | null,
    mode_paiement_id: '',
    montant_livraison_id: '',
    commentaire: '',
});

const errorsEnreg = ref<Record<string, string>>({});
const errorsRelancer = ref<Record<string, string>>({});

function getProduitError(errors: Record<string, string>, index: number, field: string): string {
    return errors[`produits.${index}.${field}`] ?? '';
}

const pharmaciesProches = ref<typeof props.pharmacies>([]);

const formRelancer = ref({
    client_nom: '',
    client_prenom: '',
    client_adresse: '',
    client_tel: '',
    beneficiaire: '',
    zone_id: '',
    pharmacie_id: '',
    produits: [] as Array<{ designation: string; dosage: string; quantite: number; prix_unitaire: number }>,
    ordonnance: null as File | null,
});
const pharmaciesProchesRelancer = ref<typeof props.pharmacies>([]);

watch(() => props.filters.search, (v) => { searchQuery.value = v ?? ''; });

watch(
    () => formEnreg.value.client_adresse,
    async (adresse) => {
        if (!adresse || adresse.length < 2) {
            pharmaciesProches.value = [];
            return;
        }
        try {
            const r = await fetch(`/commandes/recherche-pharmacie-proche?adresse=${encodeURIComponent(adresse)}`);
            const json = await r.json();
            pharmaciesProches.value = json.pharmacies || [];
            if (pharmaciesProches.value.length && !formEnreg.value.pharmacie_id) {
                formEnreg.value.pharmacie_id = String(pharmaciesProches.value[0].id);
            }
        } catch {
            pharmaciesProches.value = [];
        }
    }
);

watch(
    () => formRelancer.value.client_adresse,
    async (adresse) => {
        if (!adresse || adresse.length < 2) {
            pharmaciesProchesRelancer.value = [];
            return;
        }
        try {
            const r = await fetch(`/commandes/recherche-pharmacie-proche?adresse=${encodeURIComponent(adresse)}`);
            const json = await r.json();
            pharmaciesProchesRelancer.value = json.pharmacies || [];
            if (pharmaciesProchesRelancer.value.length && !formRelancer.value.pharmacie_id) {
                formRelancer.value.pharmacie_id = String(pharmaciesProchesRelancer.value[0].id);
            }
        } catch {
            pharmaciesProchesRelancer.value = [];
        }
    }
);

const statuts = [
    { key: 'nouvelle', label: 'Nouvelles Commandes', statsKey: 'nouvelles' },
    { key: 'en_attente', label: 'En Attente', statsKey: 'en_attente' },
    { key: 'validee', label: 'Validées / À préparer', statsKey: 'validees' },
    { key: 'retiree', label: 'Retirées', statsKey: 'retirees' },
    { key: 'livree', label: 'Livrées', statsKey: 'livrees' },
    { key: 'annulee', label: 'Annulées', statsKey: 'annulees' },
];

const motifOptions = [
    { key: 'medicaments_indisponibles', label: 'Médicaments indisponibles', desc: 'Un ou plusieurs médicaments ne sont pas disponibles dans cette pharmacie' },
    { key: 'demande_patient', label: 'Demande du patient', desc: 'Le patient a demandé l\'annulation de la commande' },
    { key: 'erreur_commande', label: 'Erreur de commandes', desc: 'Une erreur a été détecté dans les informations de la commande' },
    { key: 'probleme_paiement', label: 'Problème de paiement', desc: 'Le patient ne peut pas effectuer le paiement' },
];

const beneficiaires = ['Soi-même', 'Sa mère', 'Son père', 'Son enfant', 'Autre'];

function filtrer(key: string, value: string) {
    router.get('/commandes', { ...props.filters, [key]: value || undefined }, { preserveState: true });
}

function getStatusLabel(s: string) {
    return statuts.find((st) => st.key === s)?.label ?? s;
}

function getMedicamentsText(produits: Array<{ designation: string; dosage?: string }> | undefined): string {
    return produits?.map((p) => p.designation + (p.dosage ? ' ' + p.dosage : '')).join(', ') || '-';
}

function formatDate(d: string) {
    if (!d) return '-';
    const dt = new Date(d);
    return dt.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

async function openDetail(id: number) {
    loadingDetail.value = true;
    showDetailModal.value = true;
    try {
        const r = await fetch(`/commandes/${id}`, { headers: { Accept: 'application/json' } });
        const json = await r.json();
        detailCommande.value = json.commande;
    } catch {
        detailCommande.value = null;
    } finally {
        loadingDetail.value = false;
    }
}

function closeDetail() {
    showDetailModal.value = false;
    detailCommande.value = null;
}

function updateStatus(status: string) {
    if (!detailCommande.value) return;
    router.patch(`/commandes/${detailCommande.value.id}/status`, { status }, {
        preserveScroll: true,
        onSuccess: () => { detailCommande.value!.status = status; },
    });
}

function setMontantLivraison(montantId: number) {
    if (!detailCommande.value) return;
    router.patch(`/commandes/${detailCommande.value.id}/montant-livraison`, { montant_livraison_id: montantId }, {
        preserveScroll: true,
        onSuccess: () => { closeDetail(); router.reload(); },
    });
}

function openAnnulerModal() {
    motifAnnulation.value = '';
    showAnnulerModal.value = true;
}

function confirmAnnuler() {
    if (!detailCommande.value || !motifAnnulation.value) return;
    router.patch(`/commandes/${detailCommande.value.id}/status`, {
        status: 'annulee',
        motif_annulation: motifAnnulation.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            showAnnulerModal.value = false;
            closeDetail();
            router.reload();
        },
    });
}

function openRelancerModal() {
    if (!detailCommande.value) return;
    formRelancer.value = {
        client_nom: detailCommande.value.client?.nom ?? '',
        client_prenom: detailCommande.value.client?.prenom ?? '',
        client_adresse: detailCommande.value.client?.adresse ?? '',
        client_tel: detailCommande.value.client?.tel ?? '',
        beneficiaire: 'Soi-même',
        zone_id: '',
        pharmacie_id: '',
        produits: detailCommande.value.produits?.map((p) => ({
            designation: p.designation ?? '',
            dosage: p.dosage ?? '',
            quantite: p.pivot.quantite ?? 1,
            prix_unitaire: Number(p.pivot.prix_unitaire) ?? 0,
        })) ?? [{ designation: '', dosage: '', quantite: 1, prix_unitaire: 0 }],
        ordonnance: null,
    };
    pharmaciesProchesRelancer.value = [];
    errorsRelancer.value = {};
    showRelancerModal.value = true;
}

function openEnregistrementModal() {
    errorsEnreg.value = {};
    formEnreg.value = {
        client_nom: '',
        client_prenom: '',
        client_tel: '',
        client_adresse: '',
        pharmacie_id: '',
        beneficiaire: '',
        produits: [{ designation: '', dosage: '', quantite: 1, prix_unitaire: 0 }],
        ordonnance: null,
        mode_paiement_id: '',
        montant_livraison_id: '',
        commentaire: '',
    };
    pharmaciesProches.value = [];
    showEnregistrementModal.value = true;
}

function addProduitEnreg() {
    formEnreg.value.produits.push({ designation: '', dosage: '', quantite: 1, prix_unitaire: 0 });
}

function addProduitRelancer() {
    formRelancer.value.produits.push({ designation: '', dosage: '', quantite: 1, prix_unitaire: 0 });
}

function removeProduitEnreg(i: number) {
    formEnreg.value.produits.splice(i, 1);
}

function removeProduitRelancer(i: number) {
    formRelancer.value.produits.splice(i, 1);
}

const sousTotal = () => {
    const cmd = detailCommande.value;
    if (!cmd?.produits) return 0;
    return cmd.produits.reduce((s, p) => s + p.pivot.quantite * Number(p.pivot.prix_unitaire), 0);
};

const livraison = () => Number(detailCommande.value?.montant_livraison?.designation ?? 0);
const totalDetail = () => sousTotal() + livraison();

const totalEnreg = () => formEnreg.value.produits.reduce(
    (s, p) => s + (Number(p.prix_unitaire) || 0) * (p.quantite || 0),
    0
);

const totalRelancer = () => formRelancer.value.produits.reduce(
    (s, p) => s + (Number(p.prix_unitaire) || 0) * (p.quantite || 0),
    0
);

function onOrdonnanceChange(e: Event) {
    const target = e.target as HTMLInputElement;
    formEnreg.value.ordonnance = target.files?.[0] ?? null;
}

function parseValidationErrors(e: unknown): Record<string, string> {
    const data = (e as { response?: { data?: { errors?: Record<string, string[]> } } })?.response?.data;
    const err = data?.errors ?? {};
    return Object.fromEntries(
        Object.entries(err).map(([k, v]) => [k, Array.isArray(v) ? v[0] : String(v)])
    );
}

function submitEnregistrement() {
    const err: Record<string, string> = {};
    if (!formEnreg.value.client_nom?.trim()) err.client_nom = 'Le nom du client est obligatoire.';
    if (!formEnreg.value.client_tel?.trim()) err.client_tel = 'Le téléphone est obligatoire.';
    if (!formEnreg.value.client_adresse?.trim()) err.client_adresse = "L'adresse est obligatoire.";
    if (!formEnreg.value.pharmacie_id) err.pharmacie_id = 'Veuillez sélectionner une pharmacie.';
    const produitsValides = formEnreg.value.produits
        .filter((p) => p.designation.trim() && p.quantite > 0 && Number(p.prix_unitaire) >= 0)
        .map((p) => ({
            designation: p.designation.trim(),
            dosage: (p.dosage ?? '').trim() || null,
            quantite: p.quantite,
            prix_unitaire: Number(p.prix_unitaire),
        }));
    if (!produitsValides.length) {
        err.produits = 'Ajoutez au moins un médicament avec désignation, quantité et prix unitaire.';
    }
    formEnreg.value.produits.forEach((p, i) => {
        if (!p.designation?.trim()) err[`produits.${i}.designation`] = 'La désignation est obligatoire.';
        if (!p.quantite || p.quantite < 1) err[`produits.${i}.quantite`] = 'La quantité doit être au moins 1.';
        if (Number(p.prix_unitaire) < 0) err[`produits.${i}.prix_unitaire`] = 'Le prix unitaire doit être ≥ 0.';
    });
    if (Object.keys(err).length) {
        errorsEnreg.value = err;
        return;
    }

    const payload: Record<string, unknown> = {
        client_nom: formEnreg.value.client_nom,
        client_prenom: formEnreg.value.client_prenom,
        client_tel: formEnreg.value.client_tel,
        client_adresse: formEnreg.value.client_adresse,
        pharmacie_id: formEnreg.value.pharmacie_id,
        beneficiaire: formEnreg.value.beneficiaire || undefined,
        produits: produitsValides,
        mode_paiement_id: formEnreg.value.mode_paiement_id || undefined,
        montant_livraison_id: formEnreg.value.montant_livraison_id || undefined,
        commentaire: formEnreg.value.commentaire || undefined,
    };

    if (formEnreg.value.ordonnance) {
        const formData = new FormData();
        formData.append('client_nom', formEnreg.value.client_nom);
        formData.append('client_prenom', formEnreg.value.client_prenom);
        formData.append('client_tel', formEnreg.value.client_tel);
        formData.append('client_adresse', formEnreg.value.client_adresse);
        formData.append('pharmacie_id', formEnreg.value.pharmacie_id);
        if (formEnreg.value.beneficiaire) formData.append('beneficiaire', formEnreg.value.beneficiaire);
        formData.append('produits', JSON.stringify(produitsValides));
        if (formEnreg.value.mode_paiement_id) formData.append('mode_paiement_id', formEnreg.value.mode_paiement_id);
        if (formEnreg.value.montant_livraison_id) formData.append('montant_livraison_id', formEnreg.value.montant_livraison_id);
        if (formEnreg.value.commentaire) formData.append('commentaire', formEnreg.value.commentaire);
        formData.append('ordonnance', formEnreg.value.ordonnance);

        router.post('/commandes', formData, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => { errorsEnreg.value = {}; showEnregistrementModal.value = false; },
            onError: (e) => { errorsEnreg.value = parseValidationErrors(e); },
        });
    } else {
        router.post('/commandes', payload, {
            preserveScroll: true,
            onSuccess: () => { errorsEnreg.value = {}; showEnregistrementModal.value = false; },
            onError: (e) => { errorsEnreg.value = parseValidationErrors(e); },
        });
    }
}

function onOrdonnanceRelancerChange(e: Event) {
    const target = e.target as HTMLInputElement;
    formRelancer.value.ordonnance = target.files?.[0] ?? null;
}

function submitRelancer() {
    const c = detailCommande.value?.client;
    const err: Record<string, string> = {};
    if (!c?.id) {
        if (!formRelancer.value.client_nom?.trim()) err.client_nom = 'Le nom du client est obligatoire.';
        if (!formRelancer.value.client_tel?.trim()) err.client_tel = 'Le téléphone est obligatoire.';
        if (!formRelancer.value.client_adresse?.trim()) err.client_adresse = "L'adresse est obligatoire.";
    }
    if (!formRelancer.value.pharmacie_id) err.pharmacie_id = 'Veuillez sélectionner une pharmacie.';
    const produitsValides = formRelancer.value.produits
        .filter((p) => p.designation.trim() && p.quantite > 0 && Number(p.prix_unitaire) >= 0)
        .map((p) => ({
            designation: p.designation.trim(),
            dosage: (p.dosage ?? '').trim() || null,
            quantite: p.quantite,
            prix_unitaire: Number(p.prix_unitaire),
        }));
    if (!produitsValides.length) {
        err.produits = 'Ajoutez au moins un médicament avec désignation, quantité et prix unitaire.';
    }
    formRelancer.value.produits.forEach((p, i) => {
        if (!p.designation?.trim()) err[`produits.${i}.designation`] = 'La désignation est obligatoire.';
        if (!p.quantite || p.quantite < 1) err[`produits.${i}.quantite`] = 'La quantité doit être au moins 1.';
        if (Number(p.prix_unitaire) < 0) err[`produits.${i}.prix_unitaire`] = 'Le prix unitaire doit être ≥ 0.';
    });
    if (Object.keys(err).length) {
        errorsRelancer.value = err;
        return;
    }

    const payload: Record<string, unknown> = {
        pharmacie_id: formRelancer.value.pharmacie_id,
        beneficiaire: formRelancer.value.beneficiaire || undefined,
        produits: produitsValides,
    };
    if (c?.id) {
        payload.client_id = c.id;
    } else {
        payload.client_nom = formRelancer.value.client_nom;
        payload.client_prenom = formRelancer.value.client_prenom;
        payload.client_tel = formRelancer.value.client_tel;
        payload.client_adresse = formRelancer.value.client_adresse;
    }
    if (formRelancer.value.ordonnance) {
        const formData = new FormData();
        if (payload.client_id) formData.append('client_id', String(payload.client_id));
        else {
            formData.append('client_nom', formRelancer.value.client_nom);
            formData.append('client_prenom', formRelancer.value.client_prenom);
            formData.append('client_tel', formRelancer.value.client_tel);
            formData.append('client_adresse', formRelancer.value.client_adresse);
        }
        formData.append('pharmacie_id', formRelancer.value.pharmacie_id);
        if (formRelancer.value.beneficiaire) formData.append('beneficiaire', formRelancer.value.beneficiaire);
        formData.append('produits', JSON.stringify(produitsValides));
        formData.append('ordonnance', formRelancer.value.ordonnance);
        router.post('/commandes', formData, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                errorsRelancer.value = {};
                showRelancerModal.value = false;
                showAnnulerModal.value = false;
                closeDetail();
            },
            onError: (e) => { errorsRelancer.value = parseValidationErrors(e); },
        });
    } else {
        router.post('/commandes', payload, {
            preserveScroll: true,
            onSuccess: () => {
                errorsRelancer.value = {};
                showRelancerModal.value = false;
                showAnnulerModal.value = false;
                closeDetail();
            },
            onError: (e) => { errorsRelancer.value = parseValidationErrors(e); },
        });
    }
}

const zonePharmacies = () => props.zones ?? [];

const pharmaciesInZone = () => {
    const list = props.pharmacies ?? [];
    if (!formRelancer.value.zone_id) return list;
    return list.filter((p) => String((p as { zone_id?: number }).zone_id ?? (p.zone as { id?: number })?.id) === formRelancer.value.zone_id);
};
</script>

<template>
    <Head title="Gestion des commandes - BengaDok" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6"
            style="background: linear-gradient(to right, rgba(91, 176, 52, 0.14) 0%, rgba(52, 176, 199, 0.14) 100%);"
        >
            <!-- Tabs -->
            <div class="flex gap-2">
                <button
                    class="rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                    :class="activeTab === 'gestion' ? 'bg-[#459cd1] text-white' : 'bg-white/80 text-muted-foreground hover:bg-white'"
                    @click="activeTab = 'gestion'"
                >
                    Gestion commandes
                </button>
                <button
                    class="rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                    :class="activeTab === 'statistiques' ? 'bg-[#459cd1] text-white' : 'bg-white/80 text-muted-foreground hover:bg-white'"
                    @click="activeTab = 'statistiques'"
                >
                    Statistiques
                </button>
            </div>

            <div v-if="activeTab === 'gestion'" class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <form class="flex flex-wrap items-center gap-3" @submit.prevent="filtrer('search', searchQuery)">
                        <div class="relative min-w-[220px] flex-1">
                            <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                v-model="searchQuery"
                                placeholder="Recherche commandes (Médicaments, téléphone, Noms,...)"
                                class="h-9 pl-9"
                            />
                        </div>
                        <span class="text-sm text-muted-foreground">Trier par</span>
                        <select
                            :value="filters.periode"
                            class="h-9 min-w-[140px] rounded-md border border-input bg-background px-3 py-1 text-sm"
                            @change="(e: Event) => filtrer('periode', (e.target as HTMLSelectElement).value)"
                        >
                            <option value="">Période</option>
                            <option value="aujourdhui">Aujourd'hui</option>
                            <option value="semaine">Cette semaine</option>
                            <option value="mois">Ce mois</option>
                        </select>
                        <Input
                            :model-value="filters.date"
                            type="date"
                            class="h-9 w-auto min-w-[140px]"
                            @update:model-value="(v: string) => filtrer('date', v)"
                        />
                        <Button type="submit" size="sm">Rechercher</Button>
                    </form>
                    <Button
                        v-if="canCreateCommande"
                        class="shrink-0 bg-[#459cd1] text-white hover:bg-[#3a8ab8]"
                        @click="openEnregistrementModal"
                    >
                        <Plus class="mr-2 size-4" />
                        Nouvelles Commandes
                    </Button>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-sm font-medium text-muted-foreground">Statut :</span>
                    <Button
                        v-for="s in statuts"
                        :key="s.key"
                        :variant="filters.status === s.key ? 'default' : 'outline'"
                        size="sm"
                        :class="filters.status === s.key ? 'bg-primary text-primary-foreground' : ''"
                        @click="filtrer('status', filters.status === s.key ? '' : s.key)"
                    >
                        {{ s.label }} ({{ stats[s.statsKey] ?? 0 }})
                    </Button>
                </div>

                <!-- Barre actions groupées -->
                <div
                    v-if="someSelected"
                    class="flex flex-wrap items-center gap-3 rounded-lg border bg-primary/10 px-4 py-3"
                >
                    <span class="font-medium">{{ selectedIds.size }} commande(s) sélectionnée(s)</span>
                    <Button variant="outline" size="sm" @click="clearSelection">Tout désélectionner</Button>
                    <Button variant="outline" size="sm" @click="exportSelectedCSV">
                        <Download class="mr-2 size-4" />
                        Exporter CSV
                    </Button>
                    <Button variant="destructive" size="sm" @click="openBulkAnnulerModal">
                        <X class="mr-2 size-4" />
                        Annuler
                    </Button>
                </div>

                <div class="overflow-x-auto rounded-xl border bg-white shadow-sm">
                    <table class="w-full min-w-[900px] table-fixed text-sm">
                        <thead class="bg-muted/50">
                            <tr>
                                <th class="w-10 px-2 py-3">
                                    <Checkbox
                                        :checked="allSelected"
                                        :indeterminate="someSelected && !allSelected"
                                        @update:checked="toggleAll"
                                    />
                                </th>
                                <th class="w-[130px] px-3 py-3 text-left font-medium">ID Cmd</th>
                                <th class="w-[110px] px-3 py-3 text-left font-medium">Client</th>
                                <th class="w-[48px] px-2 py-3 text-left font-medium">Sexe</th>
                                <th class="w-[105px] px-3 py-3 text-left font-medium">Tel</th>
                                <th class="w-[88px] px-3 py-3 text-left font-medium">Date</th>
                                <th class="w-[130px] px-3 py-3 text-left font-medium">Adresse</th>
                                <th class="min-w-0 px-3 py-3 text-left font-medium">Médicament</th>
                                <th class="w-[95px] px-3 py-3 text-left font-medium">Montant</th>
                                <th class="w-[140px] px-3 py-3 text-left font-medium">Statut</th>
                                <th class="w-[72px] px-2 py-3 text-center font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="cmd in commandes.data"
                                :key="cmd.id"
                                class="border-t hover:bg-muted/30"
                            >
                                <td class="px-2 py-2.5">
                                    <Checkbox
                                        :checked="selectedIds.has(cmd.id)"
                                        @update:checked="() => toggleOne(cmd.id)"
                                    />
                                </td>
                                <td class="px-3 py-2.5">
                                    <span
                                        class="block truncate font-mono text-xs font-medium"
                                        :title="cmd.numero"
                                    >
                                        {{ cmd.numero }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5">
                                    <span
                                        class="block truncate"
                                        :title="[cmd.client?.prenom, cmd.client?.nom].filter(Boolean).join(' ') || '-'"
                                    >
                                        {{ [cmd.client?.prenom, cmd.client?.nom].filter(Boolean).join(' ') || '-' }}
                                    </span>
                                </td>
                                <td class="px-2 py-2.5 text-center">{{ cmd.client?.sexe || '-' }}</td>
                                <td class="px-3 py-2.5">
                                    <span
                                        class="block truncate font-mono text-xs"
                                        :title="cmd.client?.tel"
                                    >
                                        {{ cmd.client?.tel }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-muted-foreground">
                                    {{ formatDate(cmd.date) }}
                                </td>
                                <td class="px-3 py-2.5">
                                    <span
                                        class="block truncate text-muted-foreground"
                                        :title="cmd.client?.adresse || '-'"
                                    >
                                        {{ cmd.client?.adresse || '-' }}
                                    </span>
                                </td>
                                <td class="min-w-0 px-3 py-2.5">
                                    <span
                                        class="block truncate"
                                        :title="getMedicamentsText(cmd.produits)"
                                    >
                                        {{ getMedicamentsText(cmd.produits) }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-2.5 font-medium tabular-nums">
                                    {{ Number(cmd.prix_total).toLocaleString('fr-FR') }} FCFA
                                </td>
                                <td class="px-3 py-2.5">
                                    <span
                                        class="inline-block max-w-full truncate rounded-full px-2 py-0.5 text-xs font-medium"
                                        :title="getStatusLabel(cmd.status)"
                                        :class="{
                                            'bg-blue-100 text-blue-800 dark:bg-blue-900/30': cmd.status === 'nouvelle',
                                            'bg-amber-100 text-amber-800 dark:bg-amber-900/30': cmd.status === 'en_attente',
                                            'bg-red-100 text-red-800 dark:bg-red-900/30': cmd.status === 'annulee',
                                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30': ['validee', 'livree'].includes(cmd.status),
                                        }"
                                    >
                                        {{ getStatusLabel(cmd.status) }}
                                    </span>
                                </td>
                                <td class="px-2 py-2.5">
                                    <div class="flex items-center justify-center gap-0.5">
                                        <Button variant="ghost" size="icon" class="size-8" @click="openDetail(cmd.id)">
                                            <Eye class="size-4" />
                                        </Button>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger as-child>
                                                <Button variant="ghost" size="icon" class="size-8">
                                                    <MoreHorizontal class="size-4" />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem @click="openDetail(cmd.id)">
                                                    Voir détails
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="commandes.links?.length" class="flex flex-wrap justify-center gap-2">
                    <template v-for="(link, i) in commandes.links" :key="i">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="rounded px-3 py-1 text-sm transition-colors"
                            :class="link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'"
                        >
                            {{ link.label }}
                        </Link>
                        <span v-else class="px-3 py-1 text-muted-foreground">{{ link.label }}</span>
                    </template>
                </div>
            </div>

            <div v-else class="rounded-xl border bg-white p-8 text-center">
                <p class="text-muted-foreground">Statistiques – à venir</p>
            </div>
        </div>

        <!-- Modal Détails -->
        <Dialog :open="showDetailModal" @update:open="showDetailModal = $event">
            <DialogContent class="max-h-[90vh] max-w-2xl overflow-y-auto" @pointer-down-outside="closeDetail">
                <DialogHeader class="sr-only" />
                <div v-if="loadingDetail" class="py-12 text-center text-muted-foreground">Chargement...</div>
                <template v-else-if="detailCommande">
                    <div class="space-y-4">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="font-semibold text-lg">{{ detailCommande.numero }}</p>
                                <p class="text-sm text-muted-foreground">Date : {{ formatDate(detailCommande.date) }}</p>
                            </div>
                            <span
                                class="rounded-full px-3 py-1 text-sm font-medium text-white"
                                :class="{
                                    'bg-red-500': detailCommande.status === 'annulee',
                                    'bg-amber-500': detailCommande.status === 'en_attente',
                                    'bg-emerald-500': ['validee', 'livree'].includes(detailCommande.status),
                                }"
                            >
                                {{ getStatusLabel(detailCommande.status) }}
                            </span>
                        </div>

                        <div class="rounded-lg border bg-slate-50 p-4 dark:bg-slate-800/50">
                            <p class="mb-2 text-sm font-medium text-muted-foreground">Informations du client</p>
                            <p><span class="text-muted-foreground">Client :</span> {{ [detailCommande.client?.prenom, detailCommande.client?.nom].filter(Boolean).join(' ') || '-' }}</p>
                            <p><span class="text-muted-foreground">Tel :</span> {{ detailCommande.client?.tel }}</p>
                            <p><span class="text-muted-foreground">Adresse :</span> {{ detailCommande.client?.adresse || '-' }}</p>
                        </div>

                        <div class="rounded-lg border bg-slate-50 p-4 dark:bg-slate-800/50">
                            <p class="mb-2 text-sm font-medium text-muted-foreground">Pharmacie</p>
                            <p>{{ detailCommande.pharmacie?.designation }}</p>
                            <p class="text-muted-foreground">Adresse : {{ detailCommande.pharmacie?.adresse }}</p>
                        </div>

                        <div class="rounded-lg border bg-slate-50 p-4 dark:bg-slate-800/50">
                            <p class="mb-2 text-sm font-medium text-muted-foreground">Médicaments</p>
                            <div
                                v-for="p in detailCommande.produits"
                                :key="p.id"
                                class="flex items-center justify-between border-b py-2 last:border-0"
                            >
                                <div>
                                    <p class="font-medium">{{ p.designation }} {{ p.dosage ?? '' }}</p>
                                    <p class="text-sm text-muted-foreground">Quantité : {{ p.pivot.quantite }} | Prix unitaire : {{ Number(p.pivot.prix_unitaire).toLocaleString('fr-FR') }} FCFA</p>
                                </div>
                                <span class="rounded-full bg-emerald-500 px-2 py-0.5 text-xs text-white">{{ p.pivot.status || 'Disponible' }}</span>
                            </div>
                        </div>

                        <div class="rounded-lg border border-dashed bg-slate-50 p-4 dark:bg-slate-800/50">
                            <p class="mb-2 text-sm font-medium text-muted-foreground">Ordonnance</p>
                            <OrdonnanceViewer
                                v-if="detailCommande.ordonnance?.urlfile"
                                :urlfile="detailCommande.ordonnance.urlfile"
                                max-height="12rem"
                            />
                            <div v-else class="flex h-24 items-center justify-center text-muted-foreground">
                                Aucune ordonnance
                            </div>
                        </div>

                        <div class="rounded-lg border bg-slate-50 p-4 dark:bg-slate-800/50">
                            <p class="mb-2 text-sm font-medium text-muted-foreground">Commentaires</p>
                            <p class="text-sm">{{ detailCommande.commentaire || '-' }}</p>
                        </div>

                        <div
                            v-if="!['annulee'].includes(detailCommande.status)"
                            class="rounded-lg border bg-slate-50 p-4 dark:bg-slate-800/50"
                        >
                            <p class="mb-2 text-sm font-medium text-muted-foreground">Informations paiement</p>
                            <p v-if="detailCommande.mode_paiement">
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-emerald-800 dark:bg-emerald-900/30">
                                    {{ detailCommande.mode_paiement.designation }}
                                </span>
                            </p>
                            <p class="mt-2 text-right">
                                <span class="text-muted-foreground">Sous-Total :</span>
                                <strong>{{ sousTotal().toLocaleString('fr-FR') }} FCFA</strong>
                            </p>
                            <p v-if="detailCommande.status === 'en_attente' && !detailCommande.montant_livraison" class="mt-1 flex flex-wrap justify-end gap-2">
                                <span class="text-muted-foreground">Livraison :</span>
                                <Button
                                    v-for="m in montantsLivraison"
                                    :key="m.id"
                                    size="sm"
                                    variant="outline"
                                    class="rounded-full"
                                    @click="setMontantLivraison(m.id)"
                                >
                                    {{ Number(m.designation).toLocaleString('fr-FR') }} xaf
                                </Button>
                            </p>
                            <p v-else-if="detailCommande.montant_livraison" class="mt-1 text-right">
                                <span class="text-muted-foreground">Livraison :</span>
                                <strong>{{ Number(detailCommande.montant_livraison.designation).toLocaleString('fr-FR') }} FCFA</strong>
                            </p>
                            <p class="mt-1 text-right">
                                <span class="text-muted-foreground">Total :</span>
                                <strong class="text-lg">{{ totalDetail().toLocaleString('fr-FR') }} FCFA</strong>
                            </p>
                        </div>

                        <!-- Actions selon statut -->
                        <div class="flex flex-wrap gap-2 pt-4">
                            <template v-if="detailCommande.status === 'en_attente'">
                                <Button class="bg-emerald-500 text-white" @click="updateStatus('validee')">
                                    Valider
                                </Button>
                                <Button variant="destructive" @click="openAnnulerModal">
                                    Annuler
                                </Button>
                            </template>
                            <template v-else-if="detailCommande.status === 'validee'">
                                <Button class="bg-emerald-500 text-white" @click="updateStatus('livree')">
                                    <Truck class="mr-1 size-4" />
                                    Livrée
                                </Button>
                                <Button variant="destructive" @click="openAnnulerModal">
                                    Annuler
                                </Button>
                            </template>
                            <template v-else-if="detailCommande.status === 'livree'">
                                <Button class="bg-[#459cd1] text-white">
                                    <FileText class="mr-1 size-4" />
                                    Voir/Générer le reçu
                                </Button>
                                <Button class="bg-emerald-500 text-white" disabled>
                                    <Truck class="mr-1 size-4" />
                                    Livrée
                                </Button>
                            </template>
                            <template v-else-if="detailCommande.status === 'annulee'">
                                <Button
                                    v-if="detailCommande.motif_annulation === 'medicaments_indisponibles' && canCreateCommande"
                                    class="bg-[#459cd1] text-white"
                                    @click="openRelancerModal"
                                >
                                    Relancer la commande
                                </Button>
                            </template>
                        </div>
                    </div>
                </template>
            </DialogContent>
        </Dialog>

        <!-- Modal Annuler -->
        <Dialog :open="showAnnulerModal" @update:open="showAnnulerModal = $event">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2 text-red-600">
                        <AlertTriangle class="size-5" />
                        Annuler la commande {{ detailCommande?.numero }}
                    </DialogTitle>
                </DialogHeader>
                <p class="text-sm text-muted-foreground">
                    Sélectionner le motif d'annulation. Si les médicaments sont indisponibles, vous pouvez relancer la commande avec une autre pharmacie.
                </p>
                <div class="space-y-2">
                    <Label>Motif d'annulation *</Label>
                    <div
                        v-for="opt in motifOptions"
                        :key="opt.key"
                        class="cursor-pointer rounded-lg border p-3 transition-colors"
                        :class="motifAnnulation === opt.key ? 'border-red-400 bg-red-50 dark:bg-red-900/10' : 'border-input hover:bg-muted/50'"
                        @click="motifAnnulation = opt.key"
                    >
                        <p class="font-medium">{{ opt.label }}</p>
                        <p class="text-sm text-muted-foreground">{{ opt.desc }}</p>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showAnnulerModal = false">Retour</Button>
                    <Button variant="destructive" :disabled="!motifAnnulation" @click="confirmAnnuler">
                        Annuler la commande
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Modal Enregistrement -->
        <Dialog :open="showEnregistrementModal" @update:open="showEnregistrementModal = $event">
            <DialogContent class="max-h-[90vh] max-w-2xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <ClipboardList class="size-5 text-[#459cd1]" />
                        Enregistrement Commande
                    </DialogTitle>
                </DialogHeader>
                <form class="space-y-6" @submit.prevent="submitEnregistrement">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label>Nom du Client *</Label>
                            <Input v-model="formEnreg.client_nom" placeholder="Ex : Fofana Didier" :class="{ 'border-red-500': errorsEnreg.client_nom }" />
                            <p v-if="errorsEnreg.client_nom" class="text-sm text-red-600">{{ errorsEnreg.client_nom }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label>Prénom du Client (facultatif)</Label>
                            <Input v-model="formEnreg.client_prenom" placeholder="Ex : Didier" />
                        </div>
                        <div class="space-y-2 sm:col-span-2">
                            <Label>Téléphone *</Label>
                            <Input v-model="formEnreg.client_tel" placeholder="+242 06 888 8888" :class="{ 'border-red-500': errorsEnreg.client_tel }" />
                            <p v-if="errorsEnreg.client_tel" class="text-sm text-red-600">{{ errorsEnreg.client_tel }}</p>
                        </div>
                        <div class="space-y-2 sm:col-span-2">
                            <Label>Adresse (remplir pour afficher la pharmacie la plus proche) *</Label>
                            <Input v-model="formEnreg.client_adresse" placeholder="Ex : 20 rue Loby Moungali" :class="{ 'border-red-500': errorsEnreg.client_adresse }" />
                            <p v-if="errorsEnreg.client_adresse" class="text-sm text-red-600">{{ errorsEnreg.client_adresse }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label>Pharmacie *</Label>
                            <select
                                v-model="formEnreg.pharmacie_id"
                                class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1"
                                :class="{ 'border-red-500': errorsEnreg.pharmacie_id }"
                            >
                                <option value="">choisir la pharmacie</option>
                                <option
                                    v-for="(p, idx) in (pharmaciesProches.length ? pharmaciesProches : pharmacies)"
                                    :key="p.id"
                                    :value="p.id"
                                >
                                    {{ pharmaciesProches.length ? (idx === 0 ? '1ère proche - ' : `${idx + 1}ème proche - `) : '' }}{{ p.designation }}{{ p.zone ? ` (${p.zone.designation})` : '' }} - {{ p.adresse }}
                                </option>
                            </select>
                            <p v-if="errorsEnreg.pharmacie_id" class="text-sm text-red-600">{{ errorsEnreg.pharmacie_id }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label>Bénéficiaire</Label>
                            <select
                                v-model="formEnreg.beneficiaire"
                                class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1"
                            >
                                <option value="">choisir un bénéficiaire</option>
                                <option v-for="b in beneficiaires" :key="b" :value="b">{{ b }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <Label>Médicaments (créés pendant la commande) *</Label>
                            <Button type="button" size="sm" class="bg-[#459cd1] text-white" @click="addProduitEnreg">
                                <Link2 class="mr-1 size-4" />
                                Ajouter un médicament
                            </Button>
                        </div>
                        <p v-if="errorsEnreg.produits" class="text-sm text-red-600">{{ errorsEnreg.produits }}</p>
                        <div
                            v-for="(p, i) in formEnreg.produits"
                            :key="i"
                            class="flex flex-wrap items-end gap-2"
                        >
                            <div class="min-w-[140px] flex-1">
                                <Label class="text-xs">Désignation *</Label>
                                <Input
                                    v-model="p.designation"
                                    placeholder="Ex : Doliprane"
                                    class="text-sm"
                                    :class="{ 'border-red-500': getProduitError(errorsEnreg, i, 'designation') }"
                                />
                                <p v-if="getProduitError(errorsEnreg, i, 'designation')" class="text-xs text-red-600">{{ getProduitError(errorsEnreg, i, 'designation') }}</p>
                            </div>
                            <div class="w-24">
                                <Label class="text-xs">Dosage</Label>
                                <Input v-model="p.dosage" placeholder="500mg" class="text-sm" />
                            </div>
                            <div class="w-20">
                                <Label class="text-xs">Qté *</Label>
                                <Input
                                    v-model.number="p.quantite"
                                    type="number"
                                    min="1"
                                    :class="{ 'border-red-500': getProduitError(errorsEnreg, i, 'quantite') }"
                                />
                                <p v-if="getProduitError(errorsEnreg, i, 'quantite')" class="text-xs text-red-600">{{ getProduitError(errorsEnreg, i, 'quantite') }}</p>
                            </div>
                            <div class="w-24">
                                <Label class="text-xs">Prix unit. *</Label>
                                <Input
                                    v-model.number="p.prix_unitaire"
                                    type="number"
                                    min="0"
                                    step="1"
                                    class="text-sm"
                                    :class="{ 'border-red-500': getProduitError(errorsEnreg, i, 'prix_unitaire') }"
                                />
                                <p v-if="getProduitError(errorsEnreg, i, 'prix_unitaire')" class="text-xs text-red-600">{{ getProduitError(errorsEnreg, i, 'prix_unitaire') }}</p>
                            </div>
                            <div class="w-28">
                                <Label class="text-xs">Total</Label>
                                <Input
                                    :model-value="((p.prix_unitaire || 0) * (p.quantite || 0)).toLocaleString('fr-FR') + ' xaf'"
                                    readonly
                                    class="bg-muted text-sm"
                                />
                            </div>
                            <Button type="button" variant="ghost" size="icon" @click="removeProduitEnreg(i)">
                                <X class="size-4" />
                            </Button>
                        </div>
                        <p class="font-semibold">Total montant commande : {{ totalEnreg().toLocaleString('fr-FR') }} xaf</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Ordonnance (image ou PDF)</Label>
                        <Input
                            type="file"
                            accept=".jpg,.jpeg,.png,.gif,.webp,.pdf"
                            class="cursor-pointer"
                            @change="onOrdonnanceChange"
                        />
                        <p class="text-xs text-muted-foreground">JPG, PNG, GIF, WebP ou PDF. Max 10 Mo.</p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label>Mode de paiement</Label>
                            <select
                                v-model="formEnreg.mode_paiement_id"
                                class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1"
                            >
                                <option value="">choisir le mode de paiement</option>
                                <option v-for="m in modesPaiement" :key="m.id" :value="m.id">{{ m.designation }}</option>
                            </select>
                        </div>
                        <div class="space-y-2">
                            <Label>Montant livraison</Label>
                            <select
                                v-model="formEnreg.montant_livraison_id"
                                class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1"
                            >
                                <option value="">—</option>
                                <option v-for="m in montantsLivraison" :key="m.id" :value="m.id">
                                    {{ Number(m.designation).toLocaleString('fr-FR') }} xaf
                                </option>
                            </select>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="destructive" @click="showEnregistrementModal = false">Annuler</Button>
                        <Button type="submit" class="bg-[#459cd1] text-white">Envoyer</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Modal Relancer -->
        <Dialog :open="showRelancerModal" @update:open="showRelancerModal = $event">
            <DialogContent class="max-h-[90vh] max-w-2xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <ClipboardList class="size-5 text-[#459cd1]" />
                        Relancer la commande avec la nouvelle pharmacie
                    </DialogTitle>
                </DialogHeader>
                <form class="space-y-6" @submit.prevent="submitRelancer">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label>Nom du Client *</Label>
                            <Input v-model="formRelancer.client_nom" placeholder="Ex : Fofana Didier" :class="{ 'border-red-500': errorsRelancer.client_nom }" />
                            <p v-if="errorsRelancer.client_nom" class="text-sm text-red-600">{{ errorsRelancer.client_nom }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label>Prénom du Client (facultatif)</Label>
                            <Input v-model="formRelancer.client_prenom" placeholder="Ex : Amélia" />
                        </div>
                        <div class="space-y-2 sm:col-span-2">
                            <Label>Adresse *</Label>
                            <Input v-model="formRelancer.client_adresse" placeholder="Ex : 16 rue Djouari moukounziguaka" :class="{ 'border-red-500': errorsRelancer.client_adresse }" />
                            <p v-if="errorsRelancer.client_adresse" class="text-sm text-red-600">{{ errorsRelancer.client_adresse }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label>Bénéficiaire</Label>
                            <select
                                v-model="formRelancer.beneficiaire"
                                class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1"
                            >
                                <option v-for="b in beneficiaires" :key="b" :value="b">{{ b }}</option>
                            </select>
                        </div>
                        <div class="space-y-2">
                            <Label>Téléphone *</Label>
                            <Input v-model="formRelancer.client_tel" placeholder="+242 06 536 90 86" :class="{ 'border-red-500': errorsRelancer.client_tel }" />
                            <p v-if="errorsRelancer.client_tel" class="text-sm text-red-600">{{ errorsRelancer.client_tel }}</p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label>Pharmacie Partenaire *</Label>
                        <p class="text-sm text-muted-foreground">Remplir l'adresse client ci-dessus pour voir la pharmacie la plus proche, ou sélectionner une zone</p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="z in zonePharmacies()"
                                :key="z.id"
                                type="button"
                                class="flex items-center gap-2 rounded-lg border p-3 transition-colors"
                                :class="formRelancer.zone_id === String(z.id) ? 'border-[#459cd1] bg-sky-50' : 'border-input hover:bg-muted/50'"
                                @click="formRelancer.zone_id = String(z.id); formRelancer.pharmacie_id = ''"
                            >
                                <span>{{ z.designation }}</span>
                                <span class="text-xs text-muted-foreground">{{ z.pharmacies_count ?? 0 }} Pharmacies</span>
                            </button>
                        </div>
                        <select
                            v-model="formRelancer.pharmacie_id"
                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1"
                            :class="{ 'border-red-500': errorsRelancer.pharmacie_id }"
                        >
                            <option value="">Sélectionner une pharmacie</option>
                            <option
                                v-for="(p, idx) in (pharmaciesProchesRelancer.length ? pharmaciesProchesRelancer : pharmaciesInZone())"
                                :key="p.id"
                                :value="p.id"
                            >
                                {{ pharmaciesProchesRelancer.length ? (idx === 0 ? '1ère proche - ' : `${idx + 1}ème proche - `) : '' }}{{ p.designation }}{{ p.zone ? ` (${p.zone.designation})` : '' }} - {{ p.adresse }}
                            </option>
                        </select>
                        <p v-if="errorsRelancer.pharmacie_id" class="text-sm text-red-600">{{ errorsRelancer.pharmacie_id }}</p>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <Label>Médicaments (créés pendant la commande) *</Label>
                            <Button type="button" size="sm" class="bg-[#459cd1] text-white" @click="addProduitRelancer">
                                <Link2 class="mr-1 size-4" />
                                Ajouter un médicament
                            </Button>
                        </div>
                        <p v-if="errorsRelancer.produits" class="text-sm text-red-600">{{ errorsRelancer.produits }}</p>
                        <div
                            v-for="(p, i) in formRelancer.produits"
                            :key="i"
                            class="flex flex-wrap items-end gap-2"
                        >
                            <div class="min-w-[120px] flex-1">
                                <Label class="text-xs">Désignation *</Label>
                                <Input
                                    v-model="p.designation"
                                    placeholder="Ex : Doliprane"
                                    class="text-sm"
                                    :class="{ 'border-red-500': getProduitError(errorsRelancer, i, 'designation') }"
                                />
                                <p v-if="getProduitError(errorsRelancer, i, 'designation')" class="text-xs text-red-600">{{ getProduitError(errorsRelancer, i, 'designation') }}</p>
                            </div>
                            <div class="w-20">
                                <Label class="text-xs">Dosage</Label>
                                <Input v-model="p.dosage" placeholder="500mg" class="text-sm" />
                            </div>
                            <div class="w-16">
                                <Label class="text-xs">Qté *</Label>
                                <Input
                                    v-model.number="p.quantite"
                                    type="number"
                                    min="1"
                                    :class="{ 'border-red-500': getProduitError(errorsRelancer, i, 'quantite') }"
                                />
                                <p v-if="getProduitError(errorsRelancer, i, 'quantite')" class="text-xs text-red-600">{{ getProduitError(errorsRelancer, i, 'quantite') }}</p>
                            </div>
                            <div class="w-24">
                                <Label class="text-xs">Prix unit. *</Label>
                                <Input v-model.number="p.prix_unitaire" type="number" min="0" class="text-sm" />
                            </div>
                            <Button type="button" variant="ghost" size="icon" @click="removeProduitRelancer(i)">
                                <X class="size-4" />
                            </Button>
                        </div>
                        <p class="font-semibold">Total montant commande : {{ totalRelancer().toLocaleString('fr-FR') }} xaf</p>
                    </div>

                    <div class="space-y-2">
                        <Label>Ordonnance (image ou PDF)</Label>
                        <Input
                            type="file"
                            accept=".jpg,.jpeg,.png,.gif,.webp,.pdf"
                            class="cursor-pointer"
                            @change="onOrdonnanceRelancerChange"
                        />
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="destructive" @click="showRelancerModal = false">Annuler</Button>
                        <Button type="submit" class="bg-[#459cd1] text-white">Envoyer</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
