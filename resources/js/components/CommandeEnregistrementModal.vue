<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import {
    Building2,
    CheckCircle2,
    ChevronDown,
    ChevronLeft,
    Clock,
    FileEdit,
    Phone,
    Pill,
    Search,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import OrdonnanceUppy from '@/components/OrdonnanceUppy.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

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
    /** M ou F — civilité affichée côté liste / détail */
    client_sexe: '' | 'M' | 'F';
    pharmacie_id: string;
    beneficiaire: string;
    produits: Array<{
        designation: string;
        dosage: string | null;
        quantite: number;
        prix_unitaire: number;
    }>;
    ordonnance: File | null;
    commentaire: string;
    client_id?: number;
    /** Relance sans nouveau fichier : réutiliser l’ordonnance de cette commande annulée */
    reutiliser_ordonnance_commande_id?: number;
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
        sexe?: string;
    };
    pharmacie?: { id?: number; zone_id?: number; zone?: { id: number } };
    produits?: Array<{
        designation?: string;
        dosage?: string;
        pivot: { quantite: number; prix_unitaire: number };
    }>;
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
const props = withDefaults(
    defineProps<{
        open: boolean;
        mode?: 'nouvelle' | 'relance';
        commande?: CommandeRelance;
        zones?: Zone[];
        pharmacies?: Pharmacie[];
        apiErrors?: Record<string, string>;
    }>(),
    {
        mode: 'nouvelle',
        zones: () => [],
        pharmacies: () => [],
        apiErrors: () => ({}),
    },
);

const emit = defineEmits<{
    'update:open': [value: boolean];
    submit: [payload: FormEnregPayload];
}>();

const page = usePage();
const delaiRelanceHeures = computed(() =>
    Number(
        (page.props as { delai_relance_meme_pharmacie_heures?: number })
            .delai_relance_meme_pharmacie_heures ?? 0,
    ),
);

function finDelaiRelancePharmacieSource(): Date | null {
    if (props.mode !== 'relance' || !props.commande?.updated_at) {
        return null;
    }
    const h = delaiRelanceHeures.value;
    if (h <= 0) {
        return null;
    }
    const t = new Date(props.commande.updated_at);
    if (Number.isNaN(t.getTime())) {
        return null;
    }
    return new Date(t.getTime() + h * 3600 * 1000);
}

function isPharmacieBloqueePourRelance(pharmacyId: number): boolean {
    if (props.mode !== 'relance' || !props.commande) {
        return false;
    }
    const srcId = props.commande.pharmacie?.id;
    if (!srcId || Number(pharmacyId) !== Number(srcId)) {
        return false;
    }
    const fin = finDelaiRelancePharmacieSource();
    if (!fin) {
        return false;
    }
    return Date.now() < fin.getTime();
}

function libelleDelaiRelance(p: Pharmacie): string {
    if (!isPharmacieBloqueePourRelance(p.id)) {
        return '';
    }
    const fin = finDelaiRelancePharmacieSource();
    if (!fin) {
        return '';
    }
    return `Indisponible jusqu’au ${fin.toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' })} (délai relance)`;
}

const beneficiaires = [
    'Soi-même',
    'Sa mère',
    'Son père',
    'Son enfant',
    'Autre',
];
const formesPharmaceutiques = [
    'Comprimé',
    'Sirop',
    'Gélule',
    'Suppositoire',
    'Injectable',
    'Crème',
    'Pommade',
    'Sachets',
    'Gouttes',
    'Spray',
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
    client_sexe: '' as '' | 'M' | 'F',
    pharmacie_id: '',
    beneficiaire: '',
    produits: [
        {
            designation: '',
            dosage: '',
            forme: '',
            quantite: 1,
            prix_unitaire: 0,
        },
    ] as ProduitEnreg[],
    ordonnance: null as File | null,
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
    let list = (props.pharmacies ?? []).filter(
        (p) => (p.zone_id ?? (p.zone as { id?: number })?.id) === zoneId,
    );
    if (filtreTypeEnreg.value !== 'tous') {
        list = list.filter((p) => {
            const t = (p.type_pharmacie?.designation ?? '').toLowerCase();
            if (filtreTypeEnreg.value === 'garde')
                return p.de_garde || t.includes('garde');
            return t.includes(filtreTypeEnreg.value);
        });
    }
    if (searchPharmacieEnreg.value) {
        const q = searchPharmacieEnreg.value.toLowerCase();
        list = list.filter(
            (p) =>
                p.designation.toLowerCase().includes(q) ||
                (p.adresse ?? '').toLowerCase().includes(q),
        );
    }
    return list;
});

function isOuverte(heurs?: {
    ouverture: string;
    fermeture: string;
}): boolean | null {
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
        0,
    ),
);

function getProduitError(index: number, field: string): string {
    return errors.value[`produits.${index}.${field}`] ?? '';
}

function addProduit() {
    form.value.produits.push({
        designation: '',
        dosage: '',
        forme: '',
        quantite: 1,
        prix_unitaire: 0,
    });
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
        client_sexe: (cmd.client?.sexe === 'M' || cmd.client?.sexe === 'F'
            ? cmd.client.sexe
            : '') as '' | 'M' | 'F',
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
            : [
                  {
                      designation: '',
                      dosage: '',
                      forme: '',
                      quantite: 1,
                      prix_unitaire: 0,
                  },
              ]) as ProduitEnreg[],
        ordonnance: null,
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
            if (
                props.mode === 'relance' &&
                isPharmacieBloqueePourRelance(ph.id)
            ) {
                form.value.pharmacie_id = '';
            }
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
        client_sexe: '' as '' | 'M' | 'F',
        pharmacie_id: '',
        beneficiaire: '',
        produits: [
            {
                designation: '',
                dosage: '',
                forme: '',
                quantite: 1,
                prix_unitaire: 0,
            },
        ],
        ordonnance: null,
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
    if (!form.value.client_nom?.trim())
        err.client_nom = 'Le nom du client est obligatoire.';
    if (!form.value.client_tel?.trim())
        err.client_tel = 'Le téléphone est obligatoire.';
    if (!form.value.client_adresse?.trim())
        err.client_adresse = "L'adresse est obligatoire.";
    if (!form.value.pharmacie_id)
        err.pharmacie_id = 'Veuillez sélectionner une pharmacie.';
    const produitsValides = form.value.produits
        .filter(
            (p) =>
                p.designation.trim() &&
                p.quantite > 0 &&
                Number(p.prix_unitaire) >= 0,
        )
        .map((p) => ({
            designation: p.designation.trim(),
            dosage: (p.dosage ?? '').trim() || null,
            quantite: p.quantite,
            prix_unitaire: Number(p.prix_unitaire),
        }));
    if (!produitsValides.length) {
        err.produits =
            'Ajoutez au moins un médicament avec désignation, quantité et prix unitaire.';
    }
    form.value.produits.forEach((p, i) => {
        if (!p.designation?.trim())
            err[`produits.${i}.designation`] =
                'La désignation est obligatoire.';
        if (!p.quantite || p.quantite < 1)
            err[`produits.${i}.quantite`] = 'La quantité doit être au moins 1.';
        if (Number(p.prix_unitaire) < 0)
            err[`produits.${i}.prix_unitaire`] =
                'Le prix unitaire doit être ≥ 0.';
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
        client_sexe: form.value.client_sexe,
        pharmacie_id: form.value.pharmacie_id,
        beneficiaire: form.value.beneficiaire || '',
        produits: produitsValides,
        ordonnance: form.value.ordonnance,
        commentaire: form.value.commentaire || '',
    };
    if (props.mode === 'relance' && props.commande?.client?.id) {
        payload.client_id = props.commande.client.id;
    }
    if (
        props.mode === 'relance' &&
        props.commande?.id &&
        !form.value.ordonnance &&
        ordonnanceUrlExistante.value
    ) {
        payload.reutiliser_ordonnance_commande_id = props.commande.id;
    }
    emit('submit', payload);
}

watch(
    () => form.value.ordonnance,
    (f) => {
        if (f) ordonnanceUrlExistante.value = null;
    },
);

watch(
    () => props.open,
    (v) => {
        if (v) {
            if (props.mode === 'relance' && props.commande) {
                fillFromCommande(props.commande);
            } else {
                resetForm();
            }
        }
    },
);

watch(
    () => props.apiErrors,
    (v) => {
        if (v && Object.keys(v).length)
            errors.value = { ...errors.value, ...v };
    },
    { deep: true },
);
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent
            class="!left-[50%] !top-[50%] !w-[650px] !max-w-[95vw] !-translate-x-1/2 !-translate-y-1/2 !max-h-[80vh] !overflow-hidden !rounded-[15px] !border !border-[#ccc5c5] !bg-white !p-0 !shadow-xl"
            :show-close-button="false"
        >
            <!-- Header sticky : rounded-t pour épouser le parent (clip par overflow-hidden) -->
            <div
                class="sticky top-0 z-10 flex items-center justify-between gap-2 rounded-t-[15px] border-b border-[#ccc5c5] bg-white px-6 py-4 shadow-[0px_2px_8px_rgba(0,0,0,0.06)]"
            >
                <h2
                    class="flex items-center gap-3 text-xl font-black tracking-[2.8px] text-[#3995d2]"
                >
                    <FileEdit class="size-5 shrink-0 text-[#3995d2]" />
                    {{
                        mode === 'relance'
                            ? 'Relancer la commande'
                            : 'Enregistrement Commande'
                    }}
                </h2>
                <button
                    type="button"
                    class="text-[#dc3545] transition-colors hover:opacity-80"
                    aria-label="Fermer"
                    @click="close"
                >
                    <X class="size-5" />
                </button>
            </div>

            <!-- Body scrollable -->
            <form
                class="flex max-h-[calc(80vh-130px)] flex-col overflow-y-auto"
                @submit.prevent="onSubmit"
            >
                <div class="flex flex-col gap-5 px-6 py-5">
                    <!-- Section 1 — Infos Client (Figma: Nom, Prénom, Tél / Bénéficiaire, Adresse) -->
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div class="flex flex-col gap-1.5">
                                <Label class="text-sm font-medium text-black"
                                    >Nom du Client</Label
                                >
                                <input
                                    v-model="form.client_nom"
                                    type="text"
                                    placeholder="Ex : Fofana Didier"
                                    class="h-[42px] rounded-[10px] border border-[#ccc5c5] px-3 py-2 text-sm placeholder:italic placeholder:text-[rgba(92,89,89,0.4)] focus:border-[#0d6efd] focus:outline-none focus:ring-1 focus:ring-[#0d6efd]"
                                    :class="{
                                        'border-[#dc3545]': errors.client_nom,
                                    }"
                                />
                                <p
                                    v-if="errors.client_nom"
                                    class="text-xs text-[#dc3545]"
                                >
                                    {{ errors.client_nom }}
                                </p>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <Label class="text-sm font-medium text-black"
                                    >Prénom du Client</Label
                                >
                                <input
                                    v-model="form.client_prenom"
                                    type="text"
                                    placeholder="Ex : Fofana Didier"
                                    class="h-[42px] rounded-[10px] border border-[#ccc5c5] px-3 py-2 text-sm placeholder:italic placeholder:text-[rgba(92,89,89,0.4)] focus:border-[#0d6efd] focus:outline-none focus:ring-1 focus:ring-[#0d6efd]"
                                />
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <Label class="text-sm font-medium text-black"
                                    >Téléphone</Label
                                >
                                <div
                                    class="flex h-[42px] overflow-hidden rounded-[10px] border border-[#ccc5c5] focus-within:border-[#0d6efd] focus-within:ring-1 focus-within:ring-[#0d6efd]"
                                    :class="{
                                        'border-[#dc3545]': errors.client_tel,
                                    }"
                                >
                                    <span
                                        class="flex items-center gap-1.5 border-r border-[#ccc5c5] bg-white pl-3 pr-2 text-sm text-[rgba(92,89,89,0.4)]"
                                    >
                                        <Phone class="size-5 shrink-0" />
                                    </span>
                                    <input
                                        v-model="form.client_tel"
                                        type="tel"
                                        placeholder="+242 06 800 8008"
                                        class="min-w-0 flex-1 bg-transparent px-3 py-2 text-sm outline-none placeholder:italic placeholder:text-[rgba(92,89,89,0.4)]"
                                    />
                                </div>
                                <p
                                    v-if="errors.client_tel"
                                    class="text-xs text-[#dc3545]"
                                >
                                    {{ errors.client_tel }}
                                </p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div class="flex flex-col gap-1.5">
                                <Label class="text-sm font-medium text-black"
                                    >Genre</Label
                                >
                                <div class="relative">
                                    <select
                                        v-model="form.client_sexe"
                                        class="h-[42px] w-full appearance-none rounded-[10px] border border-[#ccc5c5] bg-white px-3 py-2 pr-10 text-sm focus:border-[#0d6efd] focus:outline-none focus:ring-1 focus:ring-[#0d6efd]"
                                    >
                                        <option value="">Non précisé</option>
                                        <option value="M">M (Mr)</option>
                                        <option value="F">F (Mme)</option>
                                    </select>
                                    <ChevronDown
                                        class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-[rgba(92,89,89,0.4)]"
                                    />
                                </div>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <Label class="text-sm font-medium text-black"
                                    >Bénéficiaire</Label
                                >
                                <div class="relative">
                                    <select
                                        v-model="form.beneficiaire"
                                        class="h-[42px] w-full appearance-none rounded-[10px] border border-[#ccc5c5] bg-white px-3 py-2 pr-10 text-sm placeholder:italic placeholder:text-[rgba(92,89,89,0.4)] focus:border-[#0d6efd] focus:outline-none focus:ring-1 focus:ring-[#0d6efd]"
                                    >
                                        <option value="">
                                            choisir un bénéficiaire
                                        </option>
                                        <option
                                            v-for="b in beneficiaires"
                                            :key="b"
                                            :value="b"
                                        >
                                            {{ b }}
                                        </option>
                                    </select>
                                    <ChevronDown
                                        class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-[rgba(92,89,89,0.4)]"
                                    />
                                </div>
                            </div>
                            <div class="flex flex-col gap-1.5 md:col-span-2">
                                <Label class="text-sm font-medium text-black"
                                    >Adresse</Label
                                >
                                <input
                                    v-model="form.client_adresse"
                                    type="text"
                                    placeholder="Ex : 20 rue Loby Moungali"
                                    class="h-[42px] rounded-[10px] border border-[#ccc5c5] px-3 py-2 text-sm placeholder:italic placeholder:text-[rgba(92,89,89,0.4)] focus:border-[#0d6efd] focus:outline-none focus:ring-1 focus:ring-[#0d6efd]"
                                    :class="{
                                        'border-[#dc3545]':
                                            errors.client_adresse,
                                    }"
                                />
                                <p
                                    v-if="errors.client_adresse"
                                    class="text-xs text-[#dc3545]"
                                >
                                    {{ errors.client_adresse }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2 — Pharmacie Partenaire (Figma: border #ccc5c5, cartes sélectionnées vertes) -->
                    <div class="rounded-[10px] border border-[#ccc5c5] p-5">
                        <p
                            class="mb-1 text-[21px] font-black italic text-[rgba(92,89,89,0.4)]"
                        >
                            Pharmacie Partenaire
                        </p>
                        <p class="mb-4 text-base text-black">
                            Sélectionner une pharmacie
                        </p>

                        <div
                            v-if="!zoneEnreg"
                            class="grid grid-cols-2 gap-3 sm:grid-cols-4"
                        >
                            <button
                                v-for="zone in zones"
                                :key="zone.id"
                                type="button"
                                class="flex min-h-[90px] min-w-[110px] flex-col items-center justify-center gap-2 rounded-[10px] border border-[#ccc5c5] p-3 text-center transition-all hover:border-[#0d6efd] hover:bg-blue-50/30"
                                @click="
                                    zoneEnreg = zone.id;
                                    filtreTypeEnreg = 'tous';
                                    searchPharmacieEnreg = '';
                                "
                            >
                                <Building2
                                    class="size-8 text-black"
                                    stroke-width="1.5"
                                />
                                <span
                                    class="text-[13px] font-bold text-black"
                                    >{{ zone.designation }}</span
                                >
                                <span class="text-[11px] text-black"
                                    >{{
                                        zone.pharmacies_count
                                    }}
                                    Pharmacies</span
                                >
                            </button>
                        </div>

                        <div v-else class="space-y-3">
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    class="flex shrink-0 items-center gap-1.5 rounded-[8px] border border-black px-2 py-1.5 text-xs font-medium text-black hover:bg-gray-100"
                                    @click="
                                        form.pharmacie_id = '';
                                        zoneEnreg = '';
                                    "
                                >
                                    <ChevronLeft class="size-4" />
                                    Retour
                                </button>
                                <span class="text-base text-black"
                                    >Sélectionner une pharmacie</span
                                >
                            </div>
                            <div class="flex flex-col gap-3 sm:flex-row">
                                <div
                                    class="flex min-w-0 flex-1 items-center overflow-hidden rounded-[10px] border border-[#ccc5c5] bg-white pl-3 focus-within:border-[#0d6efd] focus-within:ring-1 focus-within:ring-[#0d6efd]"
                                >
                                    <Search
                                        class="mr-2 size-4 shrink-0 text-[rgba(102,102,102,0.6)]"
                                    />
                                    <input
                                        v-model="searchPharmacieEnreg"
                                        placeholder="Recherche une pharmacie"
                                        class="min-w-0 flex-1 bg-transparent py-2.5 text-sm outline-none placeholder:text-[rgba(102,102,102,0.6)]"
                                    />
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="f in filtresType"
                                        :key="f.key"
                                        type="button"
                                        class="rounded-[8px] border border-black px-3 py-1.5 text-[11px] font-medium transition-all"
                                        :class="
                                            filtreTypeEnreg === f.key
                                                ? 'border-[#0d6efd] bg-[#0d6efd] text-white'
                                                : 'border-black text-black hover:bg-gray-100'
                                        "
                                        @click="filtreTypeEnreg = f.key"
                                    >
                                        {{ f.label }}
                                    </button>
                                </div>
                            </div>
                            <div
                                class="grid max-h-48 grid-cols-1 gap-2 overflow-y-auto sm:grid-cols-2"
                            >
                                <p
                                    v-if="!pharmaciesZoneEnreg.length"
                                    class="col-span-full py-6 text-center text-sm text-[rgba(92,89,89,0.4)]"
                                >
                                    Aucune pharmacie disponible.
                                </p>
                                <button
                                    v-for="p in pharmaciesZoneEnreg"
                                    :key="p.id"
                                    type="button"
                                    :disabled="
                                        mode === 'relance' &&
                                        isPharmacieBloqueePourRelance(p.id)
                                    "
                                    class="flex min-h-[100px] items-center justify-between gap-3 rounded-[10px] border p-3 text-left transition-all"
                                    :class="[
                                        form.pharmacie_id === String(p.id)
                                            ? 'border-[rgba(92,89,89,0.25)] bg-[rgba(91,182,110,0.18)]'
                                            : 'border-[rgba(92,89,89,0.25)] hover:bg-[rgba(91,182,110,0.08)]',
                                        mode === 'relance' &&
                                        isPharmacieBloqueePourRelance(p.id)
                                            ? 'cursor-not-allowed opacity-55'
                                            : 'cursor-pointer',
                                    ]"
                                    @click="
                                        !(
                                            mode === 'relance' &&
                                            isPharmacieBloqueePourRelance(p.id)
                                        ) && (form.pharmacie_id = String(p.id))
                                    "
                                >
                                    <div class="min-w-0 flex-1">
                                        <p
                                            class="truncate text-[13px] font-bold text-[#374151]"
                                        >
                                            {{ p.designation }}
                                        </p>
                                        <p
                                            class="truncate text-[11px] text-[#94a3b8]"
                                        >
                                            {{ p.adresse }} • {{ p.telephone }}
                                        </p>
                                        <p
                                            v-if="
                                                mode === 'relance' &&
                                                libelleDelaiRelance(p)
                                            "
                                            class="mt-1 text-[11px] font-medium text-amber-700"
                                        >
                                            {{ libelleDelaiRelance(p) }}
                                        </p>
                                        <div
                                            v-if="p.heurs"
                                            class="mt-0.5 flex items-center gap-1 text-[11px] text-[#94a3b8]"
                                        >
                                            <Clock class="size-3" />
                                            {{ p.heurs.ouverture }}-{{
                                                p.heurs.fermeture
                                            }}
                                        </div>
                                    </div>
                                    <div
                                        class="flex shrink-0 flex-col items-end gap-1"
                                    >
                                        <div
                                            class="flex size-5 items-center justify-center rounded-full border-2"
                                            :class="
                                                form.pharmacie_id ===
                                                String(p.id)
                                                    ? 'border-[#016630] bg-white'
                                                    : 'border-[#ccc5c5]'
                                            "
                                        >
                                            <CheckCircle2
                                                v-if="
                                                    form.pharmacie_id ===
                                                    String(p.id)
                                                "
                                                class="size-3 text-[#016630]"
                                            />
                                        </div>
                                        <span
                                            v-if="isOuverte(p.heurs) === true"
                                            class="rounded border border-[#016630] bg-white px-2 py-0.5 text-[7px] font-bold text-[#016630]"
                                            >Ouvert</span
                                        >
                                        <span
                                            v-else-if="
                                                isOuverte(p.heurs) === false
                                            "
                                            class="rounded bg-red-100 px-2 py-0.5 text-[7px] font-bold text-red-600"
                                            >FERMÉ</span
                                        >
                                    </div>
                                </button>
                            </div>
                            <p
                                v-if="errors.pharmacie_id"
                                class="text-xs text-[#dc3545]"
                            >
                                {{ errors.pharmacie_id }}
                            </p>
                        </div>
                    </div>

                    <!-- Section 3 — Médicaments (Figma: Nom | Quantité | Prix unitaire | Total, bouton #0d6efd) -->
                    <div class="rounded-[10px] border border-[#ccc5c5] p-5">
                        <div
                            class="mb-4 flex flex-wrap items-center justify-between gap-2"
                        >
                            <p
                                class="text-[20px] font-black italic text-[rgba(92,89,89,0.4)]"
                            >
                                Médicaments
                            </p>
                            <button
                                type="button"
                                class="flex items-center gap-2 rounded-[10px] bg-[#0d6efd] px-3.5 py-2 text-sm font-black text-white hover:bg-blue-700"
                                @click="addProduit"
                            >
                                <Pill class="size-5" />
                                Ajouter un médicament
                            </button>
                        </div>
                        <p
                            v-if="errors.produits"
                            class="mb-2 text-xs text-[#dc3545]"
                        >
                            {{ errors.produits }}
                        </p>

                        <div
                            v-for="(p, i) in form.produits"
                            :key="i"
                            class="mb-4 flex flex-col gap-3 rounded-[10px] border border-[#ccc5c5] bg-white p-4 last:mb-0"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div
                                    class="grid min-w-0 flex-1 grid-cols-2 gap-3 sm:grid-cols-4"
                                >
                                    <div class="flex flex-col gap-1">
                                        <Label
                                            class="text-base font-light text-black"
                                            >Nom Médicament</Label
                                        >
                                        <input
                                            v-model="p.designation"
                                            placeholder="Ex : 1000"
                                            class="h-[42px] rounded-[10px] border border-[#ccc5c5] bg-white px-3 py-2 text-sm placeholder:italic placeholder:text-[rgba(92,89,89,0.4)] focus:border-[#0d6efd] focus:outline-none"
                                            :class="{
                                                'border-[#dc3545]':
                                                    getProduitError(
                                                        i,
                                                        'designation',
                                                    ),
                                            }"
                                        />
                                        <p
                                            v-if="
                                                getProduitError(
                                                    i,
                                                    'designation',
                                                )
                                            "
                                            class="text-xs text-[#dc3545]"
                                        >
                                            {{
                                                getProduitError(
                                                    i,
                                                    'designation',
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <Label
                                            class="text-base font-light text-black"
                                            >Quantité</Label
                                        >
                                        <input
                                            v-model.number="p.quantite"
                                            type="number"
                                            min="1"
                                            class="h-[42px] w-[59px] rounded-[10px] border border-[#ccc5c5] bg-white px-2 py-2 text-center text-base text-[#5c5959] focus:border-[#0d6efd] focus:outline-none"
                                            :class="{
                                                'border-[#dc3545]':
                                                    getProduitError(
                                                        i,
                                                        'quantite',
                                                    ),
                                            }"
                                        />
                                        <p
                                            v-if="
                                                getProduitError(i, 'quantite')
                                            "
                                            class="text-xs text-[#dc3545]"
                                        >
                                            {{ getProduitError(i, 'quantite') }}
                                        </p>
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <Label
                                            class="text-base font-light text-black"
                                            >Prix unitaire</Label
                                        >
                                        <div
                                            class="flex h-[42px] items-center overflow-hidden rounded-[10px] border border-[#ccc5c5] bg-white"
                                        >
                                            <input
                                                v-model.number="p.prix_unitaire"
                                                type="number"
                                                min="0"
                                                step="1"
                                                placeholder="Ex : 1000"
                                                class="min-w-0 flex-1 border-0 px-3 py-2 text-sm outline-none placeholder:italic placeholder:text-[rgba(92,89,89,0.4)] focus:ring-0"
                                                :class="{
                                                    'ring-1 ring-[#dc3545]':
                                                        getProduitError(
                                                            i,
                                                            'prix_unitaire',
                                                        ),
                                                }"
                                            />
                                            <span
                                                class="pr-3 text-base font-medium text-black"
                                                >xaf</span
                                            >
                                        </div>
                                        <p
                                            v-if="
                                                getProduitError(
                                                    i,
                                                    'prix_unitaire',
                                                )
                                            "
                                            class="text-xs text-[#dc3545]"
                                        >
                                            {{
                                                getProduitError(
                                                    i,
                                                    'prix_unitaire',
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <Label
                                            class="text-base font-light text-black"
                                            >Total</Label
                                        >
                                        <div
                                            class="flex h-[42px] items-center overflow-hidden rounded-[10px] border border-[#ccc5c5] bg-white"
                                        >
                                            <span
                                                class="flex-1 px-3 text-base font-medium text-black"
                                                >{{
                                                    (
                                                        (p.prix_unitaire || 0) *
                                                        (p.quantite || 0)
                                                    ).toFixed(1)
                                                }}</span
                                            >
                                            <span
                                                class="pr-3 text-base font-medium text-black"
                                                >xaf</span
                                            >
                                        </div>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="shrink-0 text-[rgba(92,89,89,0.4)] hover:text-[#dc3545]"
                                    @click="removeProduit(i)"
                                >
                                    <X class="size-4" />
                                </button>
                            </div>
                            <div
                                class="grid grid-cols-2 gap-3 border-t border-[#ccc5c5] pt-2 sm:grid-cols-4"
                            >
                                <div class="flex flex-col gap-1">
                                    <Label
                                        class="text-xs font-medium text-black"
                                        >Dosage</Label
                                    >
                                    <input
                                        v-model="p.dosage"
                                        placeholder="Ex : 500mg"
                                        class="h-[38px] rounded-[10px] border border-[#ccc5c5] bg-white px-3 py-2 text-sm placeholder:italic placeholder:text-[rgba(92,89,89,0.4)] focus:border-[#0d6efd] focus:outline-none"
                                    />
                                </div>
                                <div class="flex flex-col gap-1">
                                    <Label
                                        class="text-xs font-medium text-black"
                                        >Forme</Label
                                    >
                                    <select
                                        v-model="p.forme"
                                        class="h-[38px] w-full appearance-none rounded-[10px] border border-[#ccc5c5] bg-white px-3 py-2 pr-8 text-sm focus:border-[#0d6efd] focus:outline-none focus:ring-1 focus:ring-[#0d6efd]"
                                    >
                                        <option value="">
                                            Choisir la forme
                                        </option>
                                        <option
                                            v-for="f in formesPharmaceutiques"
                                            :key="f"
                                            :value="f"
                                        >
                                            {{ f }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 4 — Total -->
                    <p class="text-base font-bold text-[#1a1a2e]">
                        Total montant commande :
                        <span class="text-[20px] font-bold">{{
                            totalEnreg.toFixed(1)
                        }}</span>
                        <span class="ml-1 text-sm font-normal text-[#94a3b8]"
                            >xaf</span
                        >
                    </p>

                    <!-- Ordonnance (dashed #e2e8f0) + Commentaires (solid #e2e8f0) : deux blocs égaux côte à côte -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="flex flex-col gap-2">
                            <p
                                v-if="
                                    ordonnanceUrlExistante && !form.ordonnance
                                "
                                class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900"
                            >
                                Ordonnance déjà enregistrée —
                                <a
                                    :href="`/storage/${ordonnanceUrlExistante}`"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="font-medium text-amber-950 underline underline-offset-2 hover:text-amber-800"
                                >
                                    Ouvrir l’ordonnance
                                </a>
                                — ajoutez un fichier ci-dessous pour la
                                remplacer (facultatif).
                            </p>
                            <OrdonnanceUppy v-model="form.ordonnance" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label class="text-sm font-medium text-[#374151]"
                                >Commentaires</Label
                            >
                            <textarea
                                v-model="form.commentaire"
                                placeholder="Commentaires ..."
                                rows="4"
                                class="min-h-[120px] resize-none rounded-[10px] border border-[#e2e8f0] bg-white p-3 text-sm placeholder:italic placeholder:text-[#94a3b8] focus:border-[#3B82F6] focus:outline-none focus:ring-1 focus:ring-[#3B82F6]"
                            />
                        </div>
                    </div>
                </div>

                <!-- Footer sticky : Annuler #EF4444, Envoyer #3B82F6, rounded 8–10px, padding ~40px -->
                <div
                    class="sticky bottom-0 flex justify-center gap-6 border-t border-[#e2e8f0] bg-white px-6 py-5 shadow-[0_-2px_10px_rgba(0,0,0,0.04)]"
                >
                    <Button
                        type="button"
                        class="rounded-[10px] bg-[#EF4444] px-10 py-2.5 text-[15px] font-bold text-white hover:bg-red-600"
                        @click="close"
                    >
                        Annuler
                    </Button>
                    <Button
                        type="submit"
                        class="rounded-[10px] bg-[#3B82F6] px-10 py-2.5 text-[15px] font-bold text-white hover:bg-blue-600"
                    >
                        Envoyer
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
