<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import {
    Building2,
    CheckCircle2,
    ChevronLeft,
    ClipboardList,
    Clock,
    FileEdit,
    Pill,
    Plus,
    Search,
    X,
} from 'lucide-vue-next';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import OrdonnanceFilePreview from '@/components/OrdonnanceFilePreview.vue';
import OrdonnanceViewer from '@/components/OrdonnanceViewer.vue';

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
    pharmacie_id: string;
    beneficiaire: string;
    produits: Array<{
        designation: string;
        dosage: string | null;
        quantite: number;
        prix_unitaire: number;
    }>;
    ordonnance: File | null;
    mode_paiement_id: string;
    commentaire: string;
    client_id?: number;
};

export type CommandeRelance = {
    client?: { id?: number; nom?: string; prenom?: string; tel?: string; adresse?: string };
    pharmacie?: { id?: number; zone_id?: number; zone?: { id: number } };
    produits?: Array<{ designation?: string; dosage?: string; pivot: { quantite: number; prix_unitaire: number } }>;
    mode_paiement?: { id: number };
    ordonnance?: { urlfile?: string } | null;
};

type Zone = { id: number; designation: string; pharmacies_count: number };
type Pharmacie = {
    id: number;
    designation: string;
    adresse: string;
    telephone: string;
    zone_id?: number;
    de_garde?: boolean;
    type_pharmacie?: { designation: string };
    heurs?: { ouverture: string; fermeture: string };
};
type ModePaiement = { id: number; designation: string };

const props = withDefaults(
    defineProps<{
        open: boolean;
        mode?: 'nouvelle' | 'relance';
        commande?: CommandeRelance;
        zones?: Zone[];
        pharmacies?: Pharmacie[];
        modesPaiement?: ModePaiement[];
        apiErrors?: Record<string, string>;
    }>(),
    { mode: 'nouvelle', zones: () => [], pharmacies: () => [], modesPaiement: () => [], apiErrors: () => ({}) }
);

const emit = defineEmits<{
    'update:open': [value: boolean];
    submit: [payload: FormEnregPayload];
}>();

const beneficiaires = ['Soi-même', 'Sa mère', 'Son père', 'Son enfant', 'Autre'];
const formesPharmaceutiques = [
    'Comprimé', 'Sirop', 'Gélule', 'Suppositoire', 'Injectable',
    'Crème', 'Pommade', 'Sachets', 'Gouttes', 'Spray',
];
const filtresType = [
    { key: 'tous' as const, label: 'Toutes' },
    { key: 'jour' as const, label: '☀ Jour' },
    { key: 'nuit' as const, label: '🌙 Nuit' },
    { key: 'garde' as const, label: '🛡 Garde' },
];

const form = ref({
    client_nom: '',
    client_prenom: '',
    client_tel: '',
    client_adresse: '',
    pharmacie_id: '',
    beneficiaire: '',
    produits: [{ designation: '', dosage: '', forme: '', quantite: 1, prix_unitaire: 0 }] as ProduitEnreg[],
    ordonnance: null as File | null,
    mode_paiement_id: '',
    commentaire: '',
});

const errors = ref<Record<string, string>>({});
/** Fichier déjà enregistré (relance) — affichage tant qu’aucun nouveau fichier n’est choisi */
const ordonnanceUrlExistante = ref<string | null>(null);
const zoneEnreg = ref<number | ''>('');
const filtreTypeEnreg = ref<'tous' | 'jour' | 'nuit' | 'garde'>('tous');
const searchPharmacieEnreg = ref('');

const pharmaciesZoneEnreg = computed(() => {
    if (!zoneEnreg.value) return [];
    const zoneId = Number(zoneEnreg.value);
    let list = (props.pharmacies ?? []).filter((p) => (p.zone_id ?? (p.zone as { id?: number })?.id) === zoneId);
    if (filtreTypeEnreg.value !== 'tous') {
        list = list.filter((p) => {
            const t = (p.type_pharmacie?.designation ?? '').toLowerCase();
            if (filtreTypeEnreg.value === 'garde') return p.de_garde || t.includes('garde');
            return t.includes(filtreTypeEnreg.value);
        });
    }
    if (searchPharmacieEnreg.value) {
        const q = searchPharmacieEnreg.value.toLowerCase();
        list = list.filter(
            (p) =>
                p.designation.toLowerCase().includes(q) ||
                (p.adresse ?? '').toLowerCase().includes(q)
        );
    }
    return list;
});

function isOuverte(heurs?: { ouverture: string; fermeture: string }): boolean | null {
    if (!heurs?.ouverture || !heurs?.fermeture) return null;
    const now = new Date();
    const [oh, om] = heurs.ouverture.split(':').map(Number);
    const [fh, fm] = heurs.fermeture.split(':').map(Number);
    const n = now.getHours() * 60 + now.getMinutes();
    return n >= oh * 60 + om && n <= fh * 60 + fm;
}

const totalEnreg = computed(() =>
    form.value.produits.reduce(
        (s, p) => s + (Number(p.prix_unitaire) || 0) * (p.quantite || 0),
        0
    )
);

function getProduitError(index: number, field: string): string {
    return errors.value[`produits.${index}.${field}`] ?? '';
}

function addProduit() {
    form.value.produits.push({ designation: '', dosage: '', forme: '', quantite: 1, prix_unitaire: 0 });
}

function removeProduit(i: number) {
    form.value.produits.splice(i, 1);
}

function fillFromCommande(cmd: NonNullable<typeof props.commande>) {
    form.value = {
        client_nom: cmd.client?.nom ?? '',
        client_prenom: cmd.client?.prenom ?? '',
        client_tel: cmd.client?.tel ?? '',
        client_adresse: cmd.client?.adresse ?? '',
        pharmacie_id: '',
        beneficiaire: 'Soi-même',
        produits: (cmd.produits?.length
            ? cmd.produits.map((p) => ({
                designation: p.designation ?? '',
                dosage: p.dosage ?? '',
                forme: '',
                quantite: p.pivot?.quantite ?? 1,
                prix_unitaire: Number(p.pivot?.prix_unitaire) ?? 0,
            }))
            : [{ designation: '', dosage: '', forme: '', quantite: 1, prix_unitaire: 0 }]) as ProduitEnreg[],
        ordonnance: null,
        mode_paiement_id: cmd.mode_paiement?.id ? String(cmd.mode_paiement.id) : '',
        commentaire: '',
    };
    ordonnanceUrlExistante.value = cmd.ordonnance?.urlfile?.trim() || null;
    const ph = cmd.pharmacie;
    if (ph?.id && props.pharmacies?.length) {
        let zoneId = ph.zone_id ?? ph.zone?.id;
        if (!zoneId) {
            const found = props.pharmacies.find((p) => p.id === ph.id);
            zoneId = found?.zone_id ?? (found?.zone as { id?: number })?.id;
        }
        if (zoneId) {
            zoneEnreg.value = zoneId;
            form.value.pharmacie_id = String(ph.id);
        }
    }
    filtreTypeEnreg.value = 'tous';
    searchPharmacieEnreg.value = '';
    errors.value = {};
}

function resetForm() {
    form.value = {
        client_nom: '',
        client_prenom: '',
        client_tel: '',
        client_adresse: '',
        pharmacie_id: '',
        beneficiaire: '',
        produits: [{ designation: '', dosage: '', forme: '', quantite: 1, prix_unitaire: 0 }],
        ordonnance: null,
        mode_paiement_id: '',
        commentaire: '',
    };
    zoneEnreg.value = '';
    filtreTypeEnreg.value = 'tous';
    searchPharmacieEnreg.value = '';
    errors.value = {};
    ordonnanceUrlExistante.value = null;
}

function close() {
    emit('update:open', false);
    resetForm();
}

function onSubmit() {
    const err: Record<string, string> = {};
    if (!form.value.client_nom?.trim()) err.client_nom = 'Le nom du client est obligatoire.';
    if (!form.value.client_tel?.trim()) err.client_tel = 'Le téléphone est obligatoire.';
    if (!form.value.client_adresse?.trim()) err.client_adresse = "L'adresse est obligatoire.";
    if (!form.value.pharmacie_id) err.pharmacie_id = 'Veuillez sélectionner une pharmacie.';
    const produitsValides = form.value.produits
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
    form.value.produits.forEach((p, i) => {
        if (!p.designation?.trim()) err[`produits.${i}.designation`] = 'La désignation est obligatoire.';
        if (!p.quantite || p.quantite < 1) err[`produits.${i}.quantite`] = 'La quantité doit être au moins 1.';
        if (Number(p.prix_unitaire) < 0) err[`produits.${i}.prix_unitaire`] = 'Le prix unitaire doit être ≥ 0.';
    });
    if (Object.keys(err).length) {
        errors.value = err;
        return;
    }

    const payload: FormEnregPayload = {
        client_nom: form.value.client_nom,
        client_prenom: form.value.client_prenom,
        client_tel: form.value.client_tel,
        client_adresse: form.value.client_adresse,
        pharmacie_id: form.value.pharmacie_id,
        beneficiaire: form.value.beneficiaire || '',
        produits: produitsValides,
        ordonnance: form.value.ordonnance,
        mode_paiement_id: form.value.mode_paiement_id || '',
        commentaire: form.value.commentaire || '',
    };
    if (props.mode === 'relance' && props.commande?.client?.id) {
        payload.client_id = props.commande.client.id;
    }
    emit('submit', payload);
}

function onOrdonnanceChange(e: Event) {
    const target = e.target as HTMLInputElement;
    form.value.ordonnance = target.files?.[0] ?? null;
    if (form.value.ordonnance) {
        ordonnanceUrlExistante.value = null;
    }
}

watch(() => props.open, (v) => {
    if (v) {
        if (props.mode === 'relance' && props.commande) {
            fillFromCommande(props.commande);
        } else {
            resetForm();
        }
    }
});

watch(() => props.apiErrors, (v) => {
    if (v && Object.keys(v).length) errors.value = { ...errors.value, ...v };
}, { deep: true });
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent
            class="!left-[50%] !top-[50%] !w-[950px] !max-w-[95vw] !-translate-x-1/2 !-translate-y-1/2 max-h-[90vh] overflow-y-auto rounded-[15px] border border-gray-200 bg-white p-6 shadow-[0px_3px_20px_0px_rgba(0,0,0,0.25)]"
            :show-close-button="false"
        >
            <DialogHeader class="mb-4 flex min-w-0 flex-row items-center justify-between gap-2 border-b border-gray-200 pb-4">
                <DialogTitle
                    class="flex min-w-0 shrink items-center gap-3 text-xl font-black tracking-[2.8px] text-[#3995d2]"
                >
                    <FileEdit class="size-6 shrink-0 text-[#3995d2]" />
                    {{ mode === 'relance' ? 'Relancer la commande' : 'Enregistrement Commande' }}
                </DialogTitle>
                <button
                    type="button"
                    class="flex size-8 items-center justify-center rounded p-1 text-[#dc3545] transition-opacity hover:opacity-80"
                    aria-label="Fermer"
                    @click="close"
                >
                    <X class="size-5" />
                </button>
            </DialogHeader>

            <form class="flex min-w-0 flex-col gap-5" @submit.prevent="onSubmit">
                <!-- Client : Nom, Prénom, Adresse -->
                <div class="grid min-w-0 grid-cols-1 gap-3 md:grid-cols-3">
                    <div class="flex flex-col gap-1">
                        <Label class="text-[20px] font-light text-black">Nom du Client</Label>
                        <Input
                            v-model="form.client_nom"
                            placeholder="Ex : Fofana Didier"
                            class="h-[42px] rounded-[10px] border border-[#ccc5c5] px-4 text-[16px] focus:border-blue-500"
                            :class="{ 'border-red-500': errors.client_nom }"
                        />
                        <p v-if="errors.client_nom" class="text-xs text-red-600">{{ errors.client_nom }}</p>
                    </div>
                    <div class="flex flex-col gap-1">
                        <Label class="text-[20px] font-light text-black">Prénom du Client</Label>
                        <Input
                            v-model="form.client_prenom"
                            placeholder="Ex : Amélia"
                            class="h-[42px] rounded-[10px] border border-[#ccc5c5] px-4 text-[16px] focus:border-blue-500"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <Label class="text-[20px] font-light text-black">Adresse</Label>
                        <Input
                            v-model="form.client_adresse"
                            placeholder="Ex : 20 rue Loby Moungali"
                            class="h-[42px] rounded-[10px] border border-[#ccc5c5] px-4 text-[16px] focus:border-blue-500"
                            :class="{ 'border-red-500': errors.client_adresse }"
                        />
                        <p v-if="errors.client_adresse" class="text-xs text-red-600">{{ errors.client_adresse }}</p>
                    </div>
                </div>

                <!-- Bénéficiaire, Téléphone -->
                <div class="grid min-w-0 grid-cols-1 gap-3 md:grid-cols-2">
                    <div class="flex flex-col gap-1">
                        <Label class="text-[20px] font-light text-black">Bénéficiaire</Label>
                        <select
                            v-model="form.beneficiaire"
                            class="h-[42px] w-full rounded-[10px] border border-[#ccc5c5] bg-white px-4 text-[16px] focus:border-blue-500 focus:outline-none"
                        >
                            <option value="">choisir un bénéficiaire</option>
                            <option v-for="b in beneficiaires" :key="b" :value="b">{{ b }}</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <Label class="text-[20px] font-light text-black">Téléphone</Label>
                        <div
                            class="flex h-[42px] overflow-hidden rounded-[10px] border border-[#ccc5c5] focus-within:border-blue-500 focus-within:ring-1 focus-within:ring-blue-500"
                            :class="{ 'border-red-500': errors.client_tel }"
                        >
                            <span class="flex items-center border-r border-gray-200 bg-gray-50 px-3 text-[13px] font-medium text-gray-500">+242</span>
                            <input
                                v-model="form.client_tel"
                                type="tel"
                                placeholder="06 898 8888"
                                class="min-w-0 flex-1 bg-transparent px-4 py-2 text-[13px] outline-none"
                            />
                        </div>
                        <p v-if="errors.client_tel" class="text-xs text-red-600">{{ errors.client_tel }}</p>
                    </div>
                </div>

                <!-- Pharmacie Partenaire -->
                <div class="min-w-0 rounded-[10px] border border-[#ccc5c5] bg-white p-4">
                    <p class="mb-3 text-[21px] font-black text-[rgba(92,89,89,0.4)]">Pharmacie Partenaire</p>

                    <div v-if="!zoneEnreg" class="flex flex-col gap-3">
                        <p class="text-[13px] text-gray-500">Sélectionner d'abord une zone de Brazzaville</p>
                        <div class="grid min-w-0 grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                            <button
                                v-for="zone in zones"
                                :key="zone.id"
                                type="button"
                                class="flex min-h-[100px] flex-col items-center justify-center gap-2 rounded-[10px] border border-[rgba(92,89,89,0.25)] bg-white p-4 text-center transition-all hover:border-[#0d6efd] hover:bg-blue-50/50"
                                @click="zoneEnreg = zone.id; filtreTypeEnreg = 'tous'; searchPharmacieEnreg = ''"
                            >
                                <Building2 class="size-8 text-gray-400" />
                                <span class="text-[15px] font-bold text-gray-800">{{ zone.designation }}</span>
                                <span class="text-[12px] text-gray-500">{{ zone.pharmacies_count }} Pharmacies</span>
                            </button>
                        </div>
                        <p v-if="errors.pharmacie_id" class="text-xs text-red-600">{{ errors.pharmacie_id }}</p>
                    </div>

                    <div v-else class="flex flex-col gap-3">
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="flex shrink-0 items-center gap-1.5 rounded-[8px] border border-black/80 px-2 py-1.5 text-[12px] font-medium text-black transition-colors hover:bg-gray-100"
                                @click="form.pharmacie_id = ''; zoneEnreg = ''"
                            >
                                <ChevronLeft class="size-4" />
                                Retour
                            </button>
                            <span class="text-[20px] font-light text-black">Sélectionner une pharmacie</span>
                        </div>
                        <div class="flex min-w-0 flex-col gap-3 sm:flex-row">
                            <div class="flex min-w-0 flex-1 items-center overflow-hidden rounded-[10px] border border-[#ccc5c5] bg-white pl-3 pr-3 focus-within:border-blue-500 focus-within:ring-1 focus-within:ring-blue-500">
                                <Search class="mr-2 size-4 shrink-0 text-gray-400" />
                                <input
                                    v-model="searchPharmacieEnreg"
                                    placeholder="Recherche une pharmacie"
                                    class="min-w-0 flex-1 bg-transparent py-2.5 text-[13px] outline-none"
                                />
                            </div>
                            <div class="flex min-w-0 shrink-0 flex-wrap gap-2">
                                <button
                                    v-for="f in filtresType"
                                    :key="f.key"
                                    type="button"
                                    class="rounded-[8px] border px-3 py-1.5 text-[11px] font-medium transition-all"
                                    :class="filtreTypeEnreg === f.key ? 'border-[#0d6efd] bg-[#0d6efd] text-white' : 'border-black text-black hover:bg-gray-100'"
                                    @click="filtreTypeEnreg = f.key"
                                >
                                    {{ f.label }}
                                </button>
                            </div>
                        </div>
                        <div class="grid max-h-56 grid-cols-1 gap-3 overflow-y-auto sm:grid-cols-2"
                        >
                            <p
                                v-if="!pharmaciesZoneEnreg.length"
                                class="py-8 text-center text-[14px] font-medium text-gray-500"
                            >
                                Aucune pharmacie disponible correspondant à vos critères.
                            </p>
                            <button
                                v-for="p in pharmaciesZoneEnreg"
                                :key="p.id"
                                type="button"
                                class="flex min-h-[100px] cursor-pointer items-center justify-between gap-3 rounded-[10px] border p-3 text-left transition-all"
                                :class="form.pharmacie_id === String(p.id) ? 'border-[rgba(92,89,89,0.25)] bg-[rgba(91,182,110,0.18)]' : 'border-[rgba(92,89,89,0.25)] bg-white hover:border-gray-300'"
                                @click="form.pharmacie_id = String(p.id)"
                            >
                                <div class="flex min-w-0 flex-1 items-center gap-3 overflow-hidden">
                                    <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#E1F3E7]">
                                        <Pill class="size-5 text-[#3c9054]" />
                                    </div>
                                    <div class="min-w-0 flex-1 overflow-hidden">
                                        <p class="truncate text-[13px] font-bold text-gray-900">{{ p.designation }}</p>
                                        <p class="truncate text-[12px] text-gray-500">{{ p.adresse }} • {{ p.telephone }}</p>
                                        <div v-if="p.heurs" class="mt-0.5 flex items-center gap-1 text-[11px] text-gray-600">
                                            <Clock class="size-3" /> {{ p.heurs.ouverture }}-{{ p.heurs.fermeture }}
                                        </div>
                                    </div>
                                </div>
                                <div class="flex shrink-0 flex-col items-end gap-1">
                                    <div
                                        class="flex size-5 items-center justify-center rounded-full border-2 transition-colors"
                                        :class="form.pharmacie_id === String(p.id) ? 'border-[#0d6efd] bg-[#0d6efd]' : 'border-gray-300'"
                                    >
                                        <CheckCircle2 v-if="form.pharmacie_id === String(p.id)" class="size-3 text-white" />
                                    </div>
                                    <span v-if="isOuverte(p.heurs) === true" class="rounded-[5px] border border-[#016630] bg-white px-2 py-0.5 text-[10px] font-bold text-[#016630]">Ouvert</span>
                                    <span v-else-if="isOuverte(p.heurs) === false" class="rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-bold text-red-600">FERMÉ</span>
                                </div>
                            </button>
                        </div>
                        <p v-if="errors.pharmacie_id" class="text-xs font-medium text-red-600">
                            {{ errors.pharmacie_id }}
                        </p>
                    </div>
                </div>

                <!-- Médicaments -->
                <div class="flex min-w-0 flex-col gap-3 rounded-[10px] border border-[#ccc5c5] bg-white p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="text-[20px] font-black text-[rgba(92,89,89,0.4)]">Médicaments</span>
                        <button
                            type="button"
                            class="flex items-center gap-2 rounded-[10px] bg-[#0d6efd] px-4 py-2 text-[14px] font-black text-white transition-colors hover:bg-blue-600"
                            @click="addProduit"
                        >
                            <Plus class="size-4" />
                            Ajouter un médicament
                        </button>
                    </div>
                    <p v-if="errors.produits" class="text-xs text-red-600">
                        {{ errors.produits }}
                    </p>

                    <div
                        v-for="(p, i) in form.produits"
                        :key="i"
                        class="flex min-w-0 flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="grid min-w-0 flex-1 grid-cols-2 gap-3 md:grid-cols-4">
                                <div class="flex flex-col gap-1">
                                    <Label class="text-[20px] font-light text-black">Nom Médicament</Label>
                                    <Input
                                        v-model="p.designation"
                                        placeholder="Ex : 1000"
                                        class="h-[42px] rounded-[10px] border border-[#ccc5c5] px-3 text-[16px]"
                                        :class="{ 'border-red-500': getProduitError(i, 'designation') }"
                                    />
                                    <p v-if="getProduitError(i, 'designation')" class="text-xs text-red-600">{{ getProduitError(i, 'designation') }}</p>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <Label class="text-[20px] font-light text-black">Dosage</Label>
                                    <Input v-model="p.dosage" placeholder="Ex : 1000" class="h-[42px] rounded-[10px] border border-[#ccc5c5] px-3 text-[16px]" />
                                </div>
                                <div class="flex flex-col gap-1">
                                    <Label class="text-[20px] font-light text-black">Forme</Label>
                                    <select v-model="p.forme" class="h-[42px] w-full rounded-[10px] border border-[#ccc5c5] bg-white px-3 text-[16px]">
                                        <option value="">Choisir la forme</option>
                                        <option v-for="f in formesPharmaceutiques" :key="f" :value="f">{{ f }}</option>
                                    </select>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <Label class="text-[20px] font-light text-black">Quantité</Label>
                                    <div class="flex h-[42px] items-center overflow-hidden rounded-[10px] border border-[#ccc5c5] bg-white px-2" :class="{ 'border-red-500': getProduitError(i, 'quantite') }">
                                        <Input v-model.number="p.quantite" type="number" min="1" class="h-full flex-1 border-0 p-0 text-center text-[16px] font-semibold focus-visible:ring-0" />
                                        <div class="flex flex-col border-l border-gray-200 pl-1">
                                            <button type="button" class="leading-none text-gray-500 hover:text-gray-900" @click="p.quantite++">▴</button>
                                            <button type="button" class="leading-none text-gray-500 hover:text-gray-900" @click="p.quantite > 1 ? p.quantite-- : null">▾</button>
                                        </div>
                                    </div>
                                    <p v-if="getProduitError(i, 'quantite')" class="text-xs text-red-600">{{ getProduitError(i, 'quantite') }}</p>
                                </div>
                            </div>
                            <button type="button" class="mt-6 shrink-0 text-gray-400 transition-colors hover:text-red-500 md:mt-0" @click="removeProduit(i)">
                                <X class="size-4" />
                            </button>
                        </div>
                        <div class="grid min-w-0 grid-cols-2 gap-3 md:grid-cols-4">
                            <div class="flex flex-col gap-1">
                                <Label class="text-[20px] font-light text-black">Prix unitaire</Label>
                                <div class="flex h-[42px] items-center overflow-hidden rounded-[10px] border border-[#ccc5c5]">
                                    <Input v-model.number="p.prix_unitaire" type="number" min="0" step="1" placeholder="Ex : 1000" class="h-full flex-1 border-0 pr-10 text-[16px] focus-visible:ring-0" :class="{ 'border-red-500': getProduitError(i, 'prix_unitaire') }" />
                                    <span class="pr-3 text-[16px] font-medium text-gray-900">xaf</span>
                                </div>
                                <p v-if="getProduitError(i, 'prix_unitaire')" class="text-xs text-red-600">{{ getProduitError(i, 'prix_unitaire') }}</p>
                            </div>
                            <div class="flex flex-col gap-1">
                                <Label class="text-[20px] font-light text-black">Total</Label>
                                <div class="flex h-[42px] items-center overflow-hidden rounded-[10px] border border-[#ccc5c5] bg-gray-50">
                                    <span class="flex-1 px-3 text-[16px] font-semibold">{{ ((p.prix_unitaire || 0) * (p.quantite || 0)).toFixed(1) }}</span>
                                    <span class="pr-3 text-[16px] font-medium text-gray-900">xaf</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p class="text-[20px] font-black text-black">
                        Total montant commande : <span class="text-[25px] font-medium">{{ totalEnreg.toFixed(1) }}</span> <span class="text-[20px] font-medium">xaf</span>
                    </p>
                </div>

                <!-- Informations paiement -->
                <div class="min-w-0 rounded-[10px] border border-[#ccc5c5] bg-white p-4">
                    <p class="mb-3 text-[20px] font-black text-[rgba(92,89,89,0.4)]">Informations paiement</p>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <Label class="shrink-0 text-[20px] font-light text-black">Mode de paiement</Label>
                        <select
                            v-model="form.mode_paiement_id"
                            class="h-[42px] min-w-0 flex-1 rounded-[10px] border border-[#ccc5c5] bg-white px-3 text-[16px] focus:border-blue-500 focus:outline-none"
                        >
                            <option value="">choisir le mode de paiement</option>
                            <option v-for="m in modesPaiement" :key="m.id" :value="m.id">{{ m.designation }}</option>
                        </select>
                    </div>
                </div>

                <!-- Ordonnance + Commentaires -->
                <div class="grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="flex min-w-0 flex-col gap-2">
                        <label
                            for="ordonnance-enreg"
                            class="flex min-h-[120px] min-w-0 cursor-pointer flex-col items-center justify-center gap-3 overflow-hidden rounded-[15px] border-2 border-dashed border-[rgba(92,89,89,0.3)] bg-white p-4 text-center transition-colors hover:border-[#0d6efd] hover:bg-blue-50/30"
                        >
                            <ClipboardList class="size-12 shrink-0 text-[rgba(92,89,89,0.4)]" />
                            <span class="max-w-full truncate text-[20px] font-black text-[rgba(92,89,89,0.4)]">
                                {{
                                    form.ordonnance
                                        ? form.ordonnance.name
                                        : ordonnanceUrlExistante
                                          ? 'Ordonnance existante — cliquez pour en remplacer (facultatif)'
                                          : 'Ajouter une ordonnance'
                                }}
                            </span>
                            <input id="ordonnance-enreg" type="file" class="hidden" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf" @change="onOrdonnanceChange" />
                        </label>
                        <OrdonnanceFilePreview v-if="form.ordonnance" :file="form.ordonnance" max-height="10rem" />
                        <OrdonnanceViewer
                            v-else-if="ordonnanceUrlExistante"
                            :urlfile="ordonnanceUrlExistante"
                            max-height="10rem"
                        />
                    </div>
                    <textarea
                        v-model="form.commentaire"
                        placeholder="Commentaires ..."
                        class="min-h-[150px] min-w-0 w-full resize-none rounded-[10px] border border-[#ccc5c5] bg-white p-4 text-[20px] text-[rgba(92,89,89,0.4)] placeholder:text-[rgba(92,89,89,0.4)] focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    />
                </div>

                <DialogFooter class="flex flex-col gap-4 pt-4 sm:flex-row sm:justify-center">
                    <Button type="button" class="h-[47px] w-full rounded-[15px] bg-[#dc3545] px-8 text-[20px] font-black text-white hover:bg-red-600 sm:w-[120px]" @click="close">
                        Annuler
                    </Button>
                    <Button type="submit" class="h-[47px] w-full rounded-[15px] bg-[#0d6efd] px-8 text-[20px] font-black text-white hover:bg-blue-700 sm:w-[120px]">
                        Envoyer
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
