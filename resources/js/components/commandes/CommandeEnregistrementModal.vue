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
    ShoppingBag,
    X,
} from 'lucide-vue-next';
import { computed, ref, unref, watch } from 'vue';
import OrdonnanceUppy from '@/components/OrdonnanceUppy.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { isParapharmaType } from '@/lib/commandeTotals';
import {
    commandeModalHeaderClass,
    commandeModalShellClass,
    moduleFormProductCardClass,
    moduleFormSectionClass,
    moduleLabelClass,
    moduleLabelLightClass,
    moduleNativeInputClass,
    moduleNativeSelectClass,
} from '@/lib/bengadokUi';
import { useCommandeCreationFields } from '@/composables/useCommandeCreationFields';
import { quickCreatePharmacie } from '@/lib/commandePharmacieQuickCreate';
import type { CommandeReferentielPharmacie } from '@/lib/commandeEnregistrementTypes';
import { STATUTS_COMMANDE } from '@/types';
import type {
    CommandeRelance,
    FormEnregPayload,
    ProduitEnreg,
} from '@/lib/commandeEnregistrementTypes';

export type { FormEnregPayload, CommandeRelance, ProduitEnreg };

function ligneProduitVide(): ProduitEnreg {
    return {
        designation: '',
        dosage: '',
        forme: '',
        quantite: 1,
        prix_unitaire: 0,
    };
}

type Zone = { id: number; designation: string; pharmacies_count: number };
type Pharmacie = {
    id: number;
    designation: string;
    adresse: string;
    telephone: string;
    zone_id?: number;
    de_garde?: boolean;
    est_partenaire?: boolean;
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
        /** Libellés arrondissements (ex. Brazzaville) */
        arrondissements?: string[];
        /** Types produit considérés comme parapharmacie (paramètres app). */
        parapharmaProduitTypes?: string[];
        montantsLivraison?: Array<{ id: number; designation: number | string }>;
        /** Mode de paiement + livreur (module DB commande / agent). */
        showAgentFields?: boolean;
        /** Saisie avec statut et date initiaux (Gestion commandes). */
        historicalEntry?: boolean;
        /** Création rapide d'une pharmacie absente du référentiel. */
        allowCreatePharmacie?: boolean;
        modesPaiement?: Array<{ id: number; designation: string }>;
        livreurs?: Array<{ id: number; nom: string; prenom: string }>;
    }>(),
    {
        mode: 'nouvelle',
        zones: () => [],
        pharmacies: () => [],
        apiErrors: () => ({}),
        arrondissements: () => [],
        parapharmaProduitTypes: () => ['Parapharmacie'],
        montantsLivraison: () => [],
        showAgentFields: false,
        historicalEntry: false,
        allowCreatePharmacie: false,
        modesPaiement: () => [],
        livreurs: () => [],
    },
);

const statutsHistorique = STATUTS_COMMANDE;

const emit = defineEmits<{
    'update:open': [value: boolean];
    submit: [payload: FormEnregPayload];
    'pharmacie-created': [pharmacie: CommandeReferentielPharmacie];
}>();

/** Props parfois reçues comme Ref (lazy-load) — normalise en tableaux plats. */
function asArray<T>(value: unknown): T[] {
    const resolved = unref(value);
    return Array.isArray(resolved) ? resolved : [];
}

const zonesList = computed(() => asArray<Zone>(props.zones));
const pharmaciesList = computed(() => asArray<Pharmacie>(props.pharmacies));
const arrondissementsList = computed(() => asArray<string>(props.arrondissements));
const montantsLivraisonList = computed(
    () =>
        asArray<{ id: number; designation: number | string }>(
            props.montantsLivraison,
        ),
);
const modesPaiementList = computed(() =>
    asArray<{ id: number; designation: string }>(props.modesPaiement),
);
const livreursList = computed(() =>
    asArray<{ id: number; nom: string; prenom: string }>(props.livreurs),
);
const parapharmaTypesList = computed(() =>
    asArray<string>(props.parapharmaProduitTypes),
);

const defaultParapharmaType = computed(
    () => parapharmaTypesList.value[0] ?? 'Parapharmacie',
);

const page = usePage();
const { isRequired: isFieldRequired, validate: validateCreationFields, applies: fieldApplies } =
    useCommandeCreationFields('admin');

const sansClientExistant = computed(() => {
    if (props.mode === 'relance' && props.commande?.client?.id) {
        return false;
    }

    return true;
});

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
    client_arrondissement: '',
    client_sexe: '' as '' | 'M' | 'F',
    pharmacie_id: '',
    beneficiaire: '',
    montant_livraison_id: '',
    produits: [ligneProduitVide()] as ProduitEnreg[],
    produitsParapharma: [ligneProduitVide()] as ProduitEnreg[],
    ordonnance: null as File | null,
    commentaire: '',
    mode_paiement_id: '',
    livreur_id: '',
    initial_status: 'nouvelle',
    date_commande: '',
    heurs_commande: '',
});

const errors = ref<Record<string, string>>({});
/** Fichier déjà enregistré (relance) — affichage tant qu’aucun nouveau fichier n’est choisi */
const ordonnanceUrlExistante = ref<string | null>(null);
const zoneEnreg = ref<number | ''>('');
const filtreTypeEnreg = ref<'tous' | 'jour' | 'nuit' | 'garde'>('tous');
const searchPharmacieEnreg = ref('');
const showCreatePharmacie = ref(false);
const createPharmacieLoading = ref(false);
const createPharmacieErrors = ref<Record<string, string>>({});
const createPharmacieForm = ref({
    designation: '',
    arrondissement: '',
    telephone: '',
    adresse: '',
});

const pharmaciesZoneEnreg = computed(() => {
    if (!zoneEnreg.value) return [];
    const zoneId = Number(zoneEnreg.value);
    let list = pharmaciesList.value.filter(
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

function isOuverte(
    heurs?: {
        ouverture: string;
        fermeture: string;
    },
    deGarde = false,
): boolean | null {
    if (deGarde) {
        return true;
    }

    if (!heurs?.ouverture || !heurs?.fermeture) return null;
    const now = new Date();
    const [oh, om] = heurs.ouverture.split(':').map(Number);
    const [fh, fm] = heurs.fermeture.split(':').map(Number);
    const n = now.getHours() * 60 + now.getMinutes();
    const open = oh * 60 + om;
    const close = fh * 60 + fm;

    if (close >= open) {
        return n >= open && n <= close;
    }

    // Pharmacie de nuit (ex. 19:00 → 08:00)
    return n >= open || n <= close;
}

function estDeGarde(p: { de_garde?: boolean; type_pharmacie?: { designation?: string } }): boolean {
    if (p.de_garde) {
        return true;
    }

    return (p.type_pharmacie?.designation ?? '').toLowerCase().includes('garde');
}

function getProduitError(index: number, field: string): string {
    return errors.value[`produits.${index}.${field}`] ?? '';
}

function getProduitParapharmaError(index: number, field: string): string {
    return errors.value[`produits_parapharma.${index}.${field}`] ?? '';
}

function addProduit() {
    form.value.produits.push(ligneProduitVide());
}

function removeProduit(i: number) {
    form.value.produits.splice(i, 1);
}

function addProduitParapharma() {
    form.value.produitsParapharma.push(ligneProduitVide());
}

function removeProduitParapharma(i: number) {
    form.value.produitsParapharma.splice(i, 1);
}

function fillFromCommande(cmd: NonNullable<typeof props.commande>) {
    const medicaments: ProduitEnreg[] = [];
    const parapharma: ProduitEnreg[] = [];

    for (const p of cmd.produits ?? []) {
        const base: ProduitEnreg = {
            designation: p.designation ?? '',
            dosage: p.dosage ?? '',
            forme: p.forme ?? '',
            quantite: p.pivot?.quantite ?? 1,
            prix_unitaire: Number(p.pivot?.prix_unitaire) ?? 0,
        };
        if (isParapharmaType(p.type, parapharmaTypesList.value)) {
            parapharma.push(base);
        } else {
            medicaments.push(base);
        }
    }

    form.value = {
        client_nom: cmd.client?.nom ?? '',
        client_prenom: cmd.client?.prenom ?? '',
        client_tel: cmd.client?.tel ?? '',
        client_adresse: cmd.client?.adresse ?? '',
        client_arrondissement: cmd.client?.arrondissement ?? '',
        client_sexe: (cmd.client?.sexe === 'M' || cmd.client?.sexe === 'F'
            ? cmd.client.sexe
            : '') as '' | 'M' | 'F',
        pharmacie_id: '',
        beneficiaire: 'Soi-même',
        montant_livraison_id: '',
        produits: medicaments.length ? medicaments : [ligneProduitVide()],
        produitsParapharma: parapharma.length
            ? parapharma
            : [ligneProduitVide()],
        ordonnance: null,
        commentaire: '',
        mode_paiement_id: '',
        livreur_id: '',
        initial_status: 'nouvelle',
        date_commande: '',
        heurs_commande: '',
    };
    ordonnanceUrlExistante.value = cmd.ordonnance?.file_url?.trim() || null;
    const ph = cmd.pharmacie;
    if (ph?.id && pharmaciesList.value.length) {
        let zoneId = ph.zone_id ?? ph.zone?.id;
        if (!zoneId) {
            const found = pharmaciesList.value.find((p) => p.id === ph.id);
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

function resetCreatePharmacieForm() {
    createPharmacieForm.value = {
        designation: '',
        arrondissement: '',
        telephone: '',
        adresse: '',
    };
    createPharmacieErrors.value = {};
    showCreatePharmacie.value = false;
}

function openCreatePharmaciePanel() {
    const zone = zonesList.value.find((z) => z.id === zoneEnreg.value);
    createPharmacieForm.value = {
        designation: searchPharmacieEnreg.value.trim(),
        arrondissement: zone?.designation ?? '',
        telephone: '',
        adresse: '',
    };
    createPharmacieErrors.value = {};
    showCreatePharmacie.value = true;
}

function selectCreatedPharmacie(pharmacie: CommandeReferentielPharmacie) {
    const zoneId = pharmacie.zone_id ?? pharmacie.zone?.id;
    if (zoneId) {
        zoneEnreg.value = zoneId;
    }
    form.value.pharmacie_id = String(pharmacie.id);
    errors.value = { ...errors.value, pharmacie_id: '' };
}

async function submitCreatePharmacie() {
    createPharmacieErrors.value = {};
    const designation = createPharmacieForm.value.designation.trim();
    if (!designation) {
        createPharmacieErrors.value = {
            designation: 'Indiquez le nom de la pharmacie.',
        };
        return;
    }

    createPharmacieLoading.value = true;
    try {
        const zone = zonesList.value.find((z) => z.id === zoneEnreg.value);
        const result = await quickCreatePharmacie({
            designation,
            arrondissement:
                createPharmacieForm.value.arrondissement.trim() ||
                zone?.designation ||
                undefined,
            zone_id: zoneEnreg.value ? Number(zoneEnreg.value) : undefined,
            telephone: createPharmacieForm.value.telephone.trim() || undefined,
            adresse: createPharmacieForm.value.adresse.trim() || undefined,
        });

        emit('pharmacie-created', result.pharmacie);
        selectCreatedPharmacie(result.pharmacie);
        resetCreatePharmacieForm();
    } catch (error) {
        const message =
            error instanceof Error
                ? error.message
                : 'Impossible de créer la pharmacie.';
        createPharmacieErrors.value = { designation: message };
    } finally {
        createPharmacieLoading.value = false;
    }
}

function resetForm() {
    form.value = {
        client_nom: '',
        client_prenom: '',
        client_tel: '',
        client_adresse: '',
        client_arrondissement: '',
        client_sexe: '' as '' | 'M' | 'F',
        pharmacie_id: '',
        beneficiaire: 'Soi-même',
        montant_livraison_id: '',
        produits: [ligneProduitVide()],
        produitsParapharma: [ligneProduitVide()],
        ordonnance: null,
        commentaire: '',
        mode_paiement_id: '',
        livreur_id: '',
        initial_status: 'nouvelle',
        date_commande: '',
        heurs_commande: '',
    };
    zoneEnreg.value = '';
    filtreTypeEnreg.value = 'tous';
    searchPharmacieEnreg.value = '';
    errors.value = {};
    ordonnanceUrlExistante.value = null;
    resetCreatePharmacieForm();
}

function close() {
    onDialogOpenChange(false);
}

function onDialogOpenChange(open: boolean) {
    emit('update:open', open);
    if (!open) {
        resetForm();
    }
}

function onSubmit() {
    const skipOrdonnanceIfReused =
        props.mode === 'relance' &&
        !!props.commande?.id &&
        !form.value.ordonnance &&
        !!ordonnanceUrlExistante.value;

    const err: Record<string, string> = {
        ...validateCreationFields(
            {
                client_nom: form.value.client_nom,
                client_prenom: form.value.client_prenom,
                client_tel: form.value.client_tel,
                client_adresse: form.value.client_adresse,
                client_arrondissement: form.value.client_arrondissement,
                client_sexe: form.value.client_sexe,
                beneficiaire: form.value.beneficiaire,
                montant_livraison_id: form.value.montant_livraison_id,
                ordonnance: form.value.ordonnance,
                commentaire: form.value.commentaire,
            },
            {
                sansClientExistant: sansClientExistant.value,
                skipOrdonnanceIfReused,
            },
        ),
    };

    if (!form.value.pharmacie_id)
        err.pharmacie_id = 'Veuillez sélectionner une pharmacie.';
    const produitsMedicamentsValides = form.value.produits
        .filter(
            (p) =>
                p.designation.trim() &&
                p.quantite > 0 &&
                Number(p.prix_unitaire) >= 0,
        )
        .map((p) => ({
            designation: p.designation.trim(),
            dosage: (p.dosage ?? '').trim() || null,
            forme: (p.forme ?? '').trim() || null,
            quantite: p.quantite,
            prix_unitaire: Number(p.prix_unitaire),
            type: null,
        }));

    const produitsParapharmaValides = form.value.produitsParapharma
        .filter(
            (p) =>
                p.designation.trim() &&
                p.quantite > 0 &&
                Number(p.prix_unitaire) >= 0,
        )
        .map((p) => ({
            designation: p.designation.trim(),
            dosage: (p.dosage ?? '').trim() || null,
            forme: (p.forme ?? '').trim() || null,
            quantite: p.quantite,
            prix_unitaire: Number(p.prix_unitaire),
            type: defaultParapharmaType.value,
        }));

    const produitsValides = [
        ...produitsMedicamentsValides,
        ...produitsParapharmaValides,
    ];

    if (!produitsValides.length) {
        err.produits =
            'Ajoutez au moins un médicament ou un produit parapharmacie avec désignation, quantité et prix unitaire.';
    }
    form.value.produits.forEach((p, i) => {
        const hasContent =
            p.designation?.trim() ||
            p.dosage?.trim() ||
            p.forme?.trim() ||
            Number(p.prix_unitaire) > 0 ||
            p.quantite !== 1;
        if (!hasContent) {
            return;
        }
        if (!p.designation?.trim())
            err[`produits.${i}.designation`] =
                'La désignation est obligatoire.';
        if (!p.quantite || p.quantite < 1)
            err[`produits.${i}.quantite`] = 'La quantité doit être au moins 1.';
        if (Number(p.prix_unitaire) < 0)
            err[`produits.${i}.prix_unitaire`] =
                'Le prix unitaire doit être ≥ 0.';
    });
    form.value.produitsParapharma.forEach((p, i) => {
        const hasContent =
            p.designation?.trim() ||
            p.dosage?.trim() ||
            p.forme?.trim() ||
            Number(p.prix_unitaire) > 0 ||
            p.quantite !== 1;
        if (!hasContent) {
            return;
        }
        if (!p.designation?.trim())
            err[`produits_parapharma.${i}.designation`] =
                'La désignation est obligatoire.';
        if (!p.quantite || p.quantite < 1)
            err[`produits_parapharma.${i}.quantite`] =
                'La quantité doit être au moins 1.';
        if (Number(p.prix_unitaire) < 0)
            err[`produits_parapharma.${i}.prix_unitaire`] =
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
        client_arrondissement: form.value.client_arrondissement,
        client_sexe: form.value.client_sexe,
        pharmacie_id: form.value.pharmacie_id,
        beneficiaire: form.value.beneficiaire || '',
        montant_livraison_id: fieldApplies('montant_livraison_id')
            ? form.value.montant_livraison_id || undefined
            : undefined,
        produits: produitsValides,
        ordonnance: form.value.ordonnance,
        commentaire: form.value.commentaire || '',
        mode_paiement_id: props.showAgentFields
            ? form.value.mode_paiement_id || undefined
            : undefined,
        livreur_id: props.showAgentFields
            ? form.value.livreur_id || undefined
            : undefined,
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
    if (props.historicalEntry) {
        payload.initial_status = form.value.initial_status || 'nouvelle';
        payload.date =
            form.value.date_commande ||
            new Date().toISOString().slice(0, 10);
        if (form.value.heurs_commande) {
            payload.heurs = form.value.heurs_commande;
        }
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
    (v, wasOpen) => {
        if (v) {
            if (props.mode === 'relance' && props.commande) {
                fillFromCommande(props.commande);
            } else {
                resetForm();
            }
        } else if (wasOpen) {
            resetForm();
        }
    },
);

watch(
    () => unref(props.apiErrors),
    (v) => {
        if (v && Object.keys(v).length) {
            errors.value = { ...errors.value, ...v };
        }
    },
    { deep: true },
);
</script>

<template>
    <Dialog :open="open" @update:open="onDialogOpenChange">
        <DialogContent
            :class="commandeModalShellClass"
            :show-close-button="false"
        >
            <!-- Header sticky : rounded-t pour épouser le parent (clip par overflow-hidden) -->
            <div
                :class="commandeModalHeaderClass"
            >
                <h2
                    class="flex items-center gap-3 text-xl font-black tracking-[2.8px] text-[#459cd1]"
                >
                    <FileEdit class="size-5 shrink-0 text-[#459cd1]" />
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
                    <div
                        v-if="historicalEntry && mode !== 'relance'"
                        :class="[moduleFormSectionClass, 'space-y-4']"
                    >
                        <h3 class="text-sm font-semibold text-[#459cd1]">
                            Statut et date de la commande
                        </h3>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div class="flex flex-col gap-1.5">
                                <Label :class="moduleLabelClass">Statut initial</Label>
                                <select
                                    v-model="form.initial_status"
                                    :class="moduleNativeSelectClass"
                                >
                                    <option
                                        v-for="st in statutsHistorique"
                                        :key="st.key"
                                        :value="st.key"
                                    >
                                        {{ st.label }}
                                    </option>
                                </select>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <Label :class="moduleLabelClass">Date commande</Label>
                                <input
                                    v-model="form.date_commande"
                                    type="date"
                                    :class="moduleNativeInputClass"
                                />
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <Label :class="moduleLabelClass">Heure (optionnel)</Label>
                                <input
                                    v-model="form.heurs_commande"
                                    type="time"
                                    :class="moduleNativeInputClass"
                                />
                            </div>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Laissez la date vide pour aujourd’hui. Utile pour
                            saisir une commande déjà livrée ou validée.
                        </p>
                    </div>

                    <!-- Section 1 — Infos Client (Figma: Nom, Prénom, Tél / Bénéficiaire, Adresse) -->
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div class="flex flex-col gap-1.5">
                                <Label :class="moduleLabelClass"
                                    >Prénom du client
                                    <span
                                        v-if="
                                            isFieldRequired('client_prenom', {
                                                sansClientExistant,
                                            })
                                        "
                                        class="text-[#dc3545]"
                                        >*</span
                                    ></Label
                                >
                                <input
                                    v-model="form.client_prenom"
                                    type="text"
                                    placeholder="Ex : Didier"
                                    class="h-[42px] rounded-[10px] border border-[#ccc5c5] dark:border-border px-3 py-2 text-sm placeholder:italic placeholder:text-[rgba(92,89,89,0.4)] focus:border-[#459cd1] focus:outline-none focus:ring-1 focus:ring-[#459cd1]"
                                    :class="{
                                        'border-[#dc3545]': errors.client_prenom,
                                    }"
                                />
                                <p
                                    v-if="errors.client_prenom"
                                    class="text-xs text-[#dc3545]"
                                >
                                    {{ errors.client_prenom }}
                                </p>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <Label :class="moduleLabelClass"
                                    >Nom du client
                                    <span
                                        v-if="
                                            isFieldRequired('client_nom', {
                                                sansClientExistant,
                                            })
                                        "
                                        class="text-[#dc3545]"
                                        >*</span
                                    >
                                    <span
                                        v-else
                                        class="text-xs font-normal text-[rgba(92,89,89,0.6)]"
                                        >(facultatif)</span
                                    ></Label
                                >
                                <input
                                    v-model="form.client_nom"
                                    type="text"
                                    placeholder="Ex : Fofana"
                                    class="h-[42px] rounded-[10px] border border-[#ccc5c5] dark:border-border px-3 py-2 text-sm placeholder:italic placeholder:text-[rgba(92,89,89,0.4)] focus:border-[#459cd1] focus:outline-none focus:ring-1 focus:ring-[#459cd1]"
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
                                <Label :class="moduleLabelClass"
                                    >Téléphone
                                    <span
                                        v-if="
                                            isFieldRequired('client_tel', {
                                                sansClientExistant,
                                            })
                                        "
                                        class="text-[#dc3545]"
                                        >*</span
                                    ></Label
                                >
                                <div
                                    class="flex h-[42px] overflow-hidden rounded-[10px] border border-[#ccc5c5] dark:border-border focus-within:border-[#459cd1] focus-within:ring-1 focus-within:ring-[#459cd1]"
                                    :class="{
                                        'border-[#dc3545]': errors.client_tel,
                                    }"
                                >
                                    <span
                                        class="flex items-center gap-1.5 border-r border-[#ccc5c5] bg-white dark:bg-input pl-3 pr-2 text-sm text-[rgba(92,89,89,0.4)]"
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
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="flex flex-col gap-1.5">
                                <Label :class="moduleLabelClass"
                                    >Genre
                                    <span
                                        v-if="
                                            isFieldRequired('client_sexe', {
                                                sansClientExistant,
                                            })
                                        "
                                        class="text-[#dc3545]"
                                        >*</span
                                    ></Label
                                >
                                <div class="relative">
                                    <select
                                        v-model="form.client_sexe"
                                        class="h-[42px] w-full appearance-none rounded-[10px] border border-[#ccc5c5] dark:border-border bg-white dark:bg-input px-3 py-2 pr-10 text-sm focus:border-[#459cd1] focus:outline-none focus:ring-1 focus:ring-[#459cd1]"
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
                                <Label :class="moduleLabelClass"
                                    >Bénéficiaire
                                    <span
                                        v-if="isFieldRequired('beneficiaire')"
                                        class="text-[#dc3545]"
                                        >*</span
                                    ></Label
                                >
                                <div class="relative">
                                    <select
                                        v-model="form.beneficiaire"
                                        class="h-[42px] w-full appearance-none rounded-[10px] border border-[#ccc5c5] dark:border-border bg-white dark:bg-input px-3 py-2 pr-10 text-sm placeholder:italic placeholder:text-[rgba(92,89,89,0.4)] focus:border-[#459cd1] focus:outline-none focus:ring-1 focus:ring-[#459cd1]"
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
                        </div>
                        <div
                            v-if="
                                showAgentFields ||
                                fieldApplies('montant_livraison_id')
                            "
                            class="grid grid-cols-1 gap-4 md:grid-cols-3"
                        >
                            <div class="flex flex-col gap-1.5">
                                <Label :class="moduleLabelClass"
                                    >Montant livraison
                                    <span
                                        v-if="
                                            isFieldRequired('montant_livraison_id')
                                        "
                                        class="text-[#dc3545]"
                                        >*</span
                                    ></Label
                                >
                                <div class="relative">
                                    <select
                                        v-model="form.montant_livraison_id"
                                        class="h-[42px] w-full appearance-none rounded-[10px] border border-[#ccc5c5] dark:border-border bg-white dark:bg-input px-3 py-2 pr-10 text-sm focus:border-[#459cd1] focus:outline-none focus:ring-1 focus:ring-[#459cd1]"
                                        :class="{
                                            'border-[#dc3545]':
                                                errors.montant_livraison_id,
                                        }"
                                    >
                                        <option value="">
                                            Choisir un montant
                                        </option>
                                        <option
                                            v-for="m in montantsLivraisonList"
                                            :key="m.id"
                                            :value="String(m.id)"
                                        >
                                            {{ m.designation }} xaf
                                        </option>
                                    </select>
                                    <ChevronDown
                                        class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-[rgba(92,89,89,0.4)]"
                                    />
                                </div>
                                <p
                                    v-if="errors.montant_livraison_id"
                                    class="text-xs text-[#dc3545]"
                                >
                                    {{ errors.montant_livraison_id }}
                                </p>
                            </div>
                        </div>
                        <div
                            v-if="showAgentFields"
                            class="grid grid-cols-1 gap-4 md:grid-cols-2"
                        >
                            <div class="flex flex-col gap-1.5">
                                <Label :class="moduleLabelClass"
                                    >Mode de paiement</Label
                                >
                                <div class="relative">
                                    <select
                                        v-model="form.mode_paiement_id"
                                        class="h-[42px] w-full appearance-none rounded-[10px] border border-[#ccc5c5] bg-white px-3 py-2 pr-10 text-sm dark:border-border dark:bg-input"
                                    >
                                        <option value="">—</option>
                                        <option
                                            v-for="m in modesPaiementList"
                                            :key="m.id"
                                            :value="String(m.id)"
                                        >
                                            {{ m.designation }}
                                        </option>
                                    </select>
                                    <ChevronDown
                                        class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-[rgba(92,89,89,0.4)]"
                                    />
                                </div>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <Label :class="moduleLabelClass">Livreur</Label>
                                <div class="relative">
                                    <select
                                        v-model="form.livreur_id"
                                        class="h-[42px] w-full appearance-none rounded-[10px] border border-[#ccc5c5] bg-white px-3 py-2 pr-10 text-sm dark:border-border dark:bg-input"
                                    >
                                        <option value="">—</option>
                                        <option
                                            v-for="l in livreursList"
                                            :key="l.id"
                                            :value="String(l.id)"
                                        >
                                            {{ l.prenom }} {{ l.nom }}
                                        </option>
                                    </select>
                                    <ChevronDown
                                        class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-[rgba(92,89,89,0.4)]"
                                    />
                                </div>
                            </div>
                        </div>
                        <div
                            class="grid grid-cols-1 gap-4 md:grid-cols-2 md:items-start"
                        >
                            <div class="flex min-w-0 flex-col gap-1.5">
                                <Label :class="moduleLabelClass"
                                    >Adresse
                                    <span
                                        v-if="
                                            isFieldRequired('client_adresse', {
                                                sansClientExistant,
                                            })
                                        "
                                        class="text-[#dc3545]"
                                        >*</span
                                    ></Label
                                >
                                <input
                                    v-model="form.client_adresse"
                                    type="text"
                                    placeholder="Ex : 20 rue Loby Moungali"
                                    class="h-[42px] w-full min-w-0 rounded-[10px] border border-[#ccc5c5] dark:border-border px-3 py-2 text-sm placeholder:italic placeholder:text-[rgba(92,89,89,0.4)] focus:border-[#459cd1] focus:outline-none focus:ring-1 focus:ring-[#459cd1]"
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
                            <div class="flex min-w-0 flex-col gap-1.5">
                                <Label :class="moduleLabelClass"
                                    >Arrondissement
                                    <span
                                        v-if="
                                            isFieldRequired(
                                                'client_arrondissement',
                                                { sansClientExistant },
                                            )
                                        "
                                        class="text-[#dc3545]"
                                        >*</span
                                    ></Label
                                >
                                <div class="relative">
                                    <select
                                        v-model="form.client_arrondissement"
                                        class="h-[42px] w-full min-w-0 appearance-none rounded-[10px] border border-[#ccc5c5] dark:border-border bg-white dark:bg-input px-3 py-2 pr-10 text-sm focus:border-[#459cd1] focus:outline-none focus:ring-1 focus:ring-[#459cd1]"
                                        :class="{
                                            'border-[#dc3545]':
                                                errors.client_arrondissement,
                                        }"
                                    >
                                        <option value="">
                                            Choisir un arrondissement…
                                        </option>
                                        <option
                                            v-for="a in arrondissementsList"
                                            :key="a"
                                            :value="a"
                                        >
                                            {{ a }}
                                        </option>
                                    </select>
                                    <ChevronDown
                                        class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-[rgba(92,89,89,0.4)]"
                                    />
                                </div>
                                <p
                                    v-if="errors.client_arrondissement"
                                    class="text-xs text-[#dc3545]"
                                >
                                    {{ errors.client_arrondissement }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2 — Pharmacie -->
                    <div :class="moduleFormSectionClass">
                        <p
                            class="mb-1 text-[21px] font-black italic text-[rgba(92,89,89,0.4)]"
                        >
                            {{
                                allowCreatePharmacie
                                    ? 'Pharmacie'
                                    : 'Pharmacie Partenaire'
                            }}
                        </p>
                        <p class="mb-4 text-base text-black dark:text-foreground">
                            <template v-if="allowCreatePharmacie">
                                Choisissez une pharmacie existante ou créez-en
                                une si elle n'est pas répertoriée
                            </template>
                            <template v-else>
                                Sélectionner une pharmacie
                            </template>
                            <span class="text-[#dc3545]">*</span>
                        </p>

                        <div
                            v-if="allowCreatePharmacie && showCreatePharmacie"
                            class="rounded-[10px] border border-[#459cd1]/40 bg-[#459cd1]/5 p-4 space-y-3"
                        >
                            <p class="text-sm font-semibold text-[#459cd1]">
                                Créer une pharmacie non partenaire
                            </p>
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                <div class="flex flex-col gap-1.5 md:col-span-2">
                                    <Label :class="moduleLabelClass"
                                        >Nom de la pharmacie
                                        <span class="text-[#dc3545]">*</span></Label
                                    >
                                    <input
                                        v-model="createPharmacieForm.designation"
                                        type="text"
                                        placeholder="Ex : Auréole"
                                        class="h-[42px] rounded-[10px] border border-[#ccc5c5] px-3 text-sm focus:border-[#459cd1] focus:outline-none focus:ring-1 focus:ring-[#459cd1]"
                                        :class="{
                                            'border-[#dc3545]':
                                                createPharmacieErrors.designation,
                                        }"
                                    />
                                    <p
                                        v-if="createPharmacieErrors.designation"
                                        class="text-xs text-[#dc3545]"
                                    >
                                        {{ createPharmacieErrors.designation }}
                                    </p>
                                </div>
                                <div class="flex flex-col gap-1.5">
                                    <Label :class="moduleLabelClass"
                                        >Arrondissement</Label
                                    >
                                    <select
                                        v-model="createPharmacieForm.arrondissement"
                                        :class="moduleNativeSelectClass"
                                    >
                                        <option value="">
                                            Sélectionner un arrondissement
                                        </option>
                                        <option
                                            v-for="a in arrondissementsList"
                                            :key="a"
                                            :value="a"
                                        >
                                            {{ a }}
                                        </option>
                                    </select>
                                </div>
                                <div class="flex flex-col gap-1.5">
                                    <Label :class="moduleLabelClass"
                                        >Téléphone (optionnel)</Label
                                    >
                                    <input
                                        v-model="createPharmacieForm.telephone"
                                        type="text"
                                        placeholder="+242 06 000 00 00"
                                        class="h-[42px] rounded-[10px] border border-[#ccc5c5] px-3 text-sm focus:border-[#459cd1] focus:outline-none focus:ring-1 focus:ring-[#459cd1]"
                                    />
                                </div>
                                <div class="flex flex-col gap-1.5 md:col-span-2">
                                    <Label :class="moduleLabelClass"
                                        >Adresse (optionnel)</Label
                                    >
                                    <input
                                        v-model="createPharmacieForm.adresse"
                                        type="text"
                                        placeholder="Rue, quartier…"
                                        class="h-[42px] rounded-[10px] border border-[#ccc5c5] px-3 text-sm focus:border-[#459cd1] focus:outline-none focus:ring-1 focus:ring-[#459cd1]"
                                    />
                                </div>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Seul le nom est obligatoire. La pharmacie sera
                                enregistrée comme non partenaire ; horaires,
                                gérant et crédits pourront être complétés dans
                                le module Pharmacies.
                            </p>
                            <div class="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="resetCreatePharmacieForm"
                                >
                                    Annuler
                                </Button>
                                <Button
                                    type="button"
                                    class="bg-[#459cd1] text-white hover:bg-[#459cd1]/90"
                                    :disabled="createPharmacieLoading"
                                    @click="submitCreatePharmacie"
                                >
                                    {{
                                        createPharmacieLoading
                                            ? 'Création…'
                                            : 'Créer et sélectionner'
                                    }}
                                </Button>
                            </div>
                        </div>

                        <div
                            v-else-if="!zoneEnreg"
                            class="space-y-4"
                        >
                        <div
                            class="grid grid-cols-2 gap-3 sm:grid-cols-4"
                        >
                            <button
                                v-for="zone in zonesList"
                                :key="zone.id"
                                type="button"
                                class="flex min-h-[90px] min-w-[110px] flex-col items-center justify-center gap-2 rounded-[10px] border border-[#ccc5c5] dark:border-border p-3 text-center transition-all hover:border-[#459cd1] hover:bg-[#459cd1]/10"
                                @click="
                                    zoneEnreg = zone.id;
                                    filtreTypeEnreg = 'tous';
                                    searchPharmacieEnreg = '';
                                "
                            >
                                <Building2
                                    class="size-8 text-black dark:text-foreground"
                                    stroke-width="1.5"
                                />
                                <span
                                    class="text-[13px] font-bold text-black dark:text-foreground"
                                    >{{ zone.designation }}</span
                                >
                                <span class="text-[11px] text-black dark:text-foreground"
                                    >{{
                                        zone.pharmacies_count
                                    }}
                                    Pharmacies</span
                                >
                            </button>
                        </div>
                            <div
                                v-if="allowCreatePharmacie"
                                class="rounded-[10px] border border-dashed border-[#459cd1]/40 bg-[#459cd1]/5 p-4"
                            >
                                <p class="mb-2 text-sm text-muted-foreground">
                                    La pharmacie n'est pas dans la liste ?
                                </p>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="gap-2 border-[#459cd1] text-[#459cd1]"
                                    @click="openCreatePharmaciePanel"
                                >
                                    <Building2 class="size-4" />
                                    Créer une pharmacie absente
                                </Button>
                            </div>
                        </div>

                        <div v-else class="space-y-3">
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    class="flex shrink-0 items-center gap-1.5 rounded-[8px] border border-black px-2 py-1.5 text-xs font-medium text-black dark:text-foreground hover:bg-gray-100 dark:hover:bg-muted"
                                    @click="
                                        form.pharmacie_id = '';
                                        zoneEnreg = '';
                                    "
                                >
                                    <ChevronLeft class="size-4" />
                                    Retour
                                </button>
                                <span class="text-base text-black dark:text-foreground"
                                    >Sélectionner une pharmacie
                                    <span class="text-[#dc3545]">*</span></span
                                >
                            </div>
                            <div class="flex flex-col gap-3 sm:flex-row">
                                <div
                                    class="flex min-w-0 flex-1 items-center overflow-hidden rounded-[10px] border border-[#ccc5c5] dark:border-border bg-white dark:bg-input pl-3 focus-within:border-[#459cd1] focus-within:ring-1 focus-within:ring-[#459cd1]"
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
                                <Button
                                    v-if="allowCreatePharmacie"
                                    type="button"
                                    variant="outline"
                                    class="h-[42px] shrink-0 gap-2 border-[#459cd1] text-[#459cd1]"
                                    @click="openCreatePharmaciePanel"
                                >
                                    <Building2 class="size-4" />
                                    Nouvelle pharmacie
                                </Button>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="f in filtresType"
                                        :key="f.key"
                                        type="button"
                                        class="rounded-[8px] border border-black px-3 py-1.5 text-[11px] font-medium transition-all"
                                        :class="
                                            filtreTypeEnreg === f.key
                                                ? 'border-[#459cd1] bg-[#459cd1] text-white'
                                                : 'border-black text-black dark:text-foreground hover:bg-gray-100 dark:hover:bg-muted'
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
                                <div
                                    v-if="
                                        allowCreatePharmacie &&
                                        !pharmaciesZoneEnreg.length &&
                                        searchPharmacieEnreg.trim()
                                    "
                                    class="col-span-full rounded-[10px] border border-dashed border-[#459cd1]/40 bg-[#459cd1]/5 p-4 text-center"
                                >
                                    <p class="text-sm text-muted-foreground">
                                        Aucun résultat pour «
                                        {{ searchPharmacieEnreg.trim() }} ».
                                    </p>
                                    <Button
                                        type="button"
                                        variant="link"
                                        class="mt-1 text-[#459cd1]"
                                        @click="openCreatePharmaciePanel"
                                    >
                                        Créer « {{ searchPharmacieEnreg.trim() }} »
                                    </Button>
                                </div>
                                <p
                                    v-else-if="!pharmaciesZoneEnreg.length"
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
                                        <div class="flex min-w-0 items-center gap-1.5">
                                            <p
                                                class="truncate text-[13px] font-bold text-[#374151]"
                                            >
                                                {{ p.designation }}
                                            </p>
                                            <span
                                                v-if="p.est_partenaire === false"
                                                class="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-medium bg-amber-100 text-amber-800"
                                            >
                                                Non partenaire
                                            </span>
                                        </div>
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
                                                    ? 'border-[#016630] bg-white dark:bg-input'
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
                                            v-if="estDeGarde(p)"
                                            class="rounded border border-amber-400 bg-amber-50 px-2 py-0.5 text-[7px] font-bold text-amber-800"
                                            >De garde</span
                                        >
                                        <span
                                            v-else-if="
                                                isOuverte(p.heurs) === true
                                            "
                                            class="rounded border border-[#016630] bg-white dark:bg-input px-2 py-0.5 text-[7px] font-bold text-[#016630]"
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

                    <!-- Section 3 — Médicaments : ligne 1 (Nom | Dosage | Forme | Qté), ligne 2 (Prix unitaire | Total) -->
                    <div :class="moduleFormSectionClass">
                        <div
                            class="mb-4 flex flex-wrap items-center justify-between gap-2"
                        >
                            <p
                                class="text-[20px] font-black italic text-[rgba(92,89,89,0.4)]"
                            >
                                Médicaments
                                <span class="text-[#dc3545] not-italic">*</span>
                            </p>
                            <button
                                type="button"
                                class="flex items-center gap-2 rounded-[10px] bg-[#459cd1] px-3.5 py-2 text-sm font-black text-white hover:bg-[#3a87b8]"
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
                            :class="moduleFormProductCardClass"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div
                                    class="grid min-w-0 flex-1 grid-cols-1 gap-x-4 gap-y-4 pr-1 md:grid-cols-[minmax(9.5rem,1.35fr)_minmax(5.75rem,0.85fr)_minmax(6.5rem,0.95fr)_minmax(7.25rem,1.05fr)]"
                                >
                                    <!-- Ligne 1 — colonnes inégales pour éviter que « Nom Médicament » empiète sur Dosage -->
                                    <div class="flex min-w-0 flex-col gap-1 pr-0.5">
                                        <Label
                                            :class="moduleLabelLightClass"
                                            >Nom Médicament
                                            <span class="text-[#dc3545]">*</span></Label
                                        >
                                        <input
                                            v-model="p.designation"
                                            placeholder="Ex : 1000"
                                            class="h-[42px] rounded-[10px] border border-[#ccc5c5] dark:border-border bg-white dark:bg-input px-3 py-2 text-sm placeholder:italic placeholder:text-[rgba(92,89,89,0.4)] focus:border-[#459cd1] focus:outline-none"
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
                                    <div class="flex min-w-0 flex-col gap-1 pl-0.5">
                                        <Label
                                            :class="moduleLabelLightClass"
                                            >Dosage</Label
                                        >
                                        <input
                                            v-model="p.dosage"
                                            placeholder="Ex : 1000"
                                            class="h-[42px] rounded-[10px] border border-[#ccc5c5] dark:border-border bg-white dark:bg-input px-3 py-2 text-sm placeholder:italic placeholder:text-[rgba(92,89,89,0.4)] focus:border-[#459cd1] focus:outline-none"
                                        />
                                    </div>
                                    <div class="flex min-w-0 flex-col gap-1">
                                        <Label
                                            :class="moduleLabelLightClass"
                                            >Forme</Label
                                        >
                                        <select
                                            v-model="p.forme"
                                            class="h-[42px] w-full appearance-none rounded-[10px] border border-[#ccc5c5] dark:border-border bg-white dark:bg-input px-3 py-2 pr-8 text-sm focus:border-[#459cd1] focus:outline-none focus:ring-1 focus:ring-[#459cd1]"
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
                                    <div
                                        class="flex min-w-0 flex-col gap-1 md:min-w-[7.25rem]"
                                    >
                                        <Label
                                            :class="moduleLabelLightClass"
                                            >Quantité
                                            <span class="text-[#dc3545]">*</span></Label
                                        >
                                        <input
                                            v-model.number="p.quantite"
                                            type="number"
                                            min="1"
                                            class="box-border h-[42px] w-full min-w-0 max-w-full rounded-[10px] border border-[#ccc5c5] dark:border-border bg-white dark:bg-input px-2 py-2 text-center text-base text-[#5c5959] focus:border-[#459cd1] focus:outline-none md:max-w-[7.5rem]"
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
                                    <!-- Ligne 2 : sous Nom + Dosage (2 premières colonnes en md+) -->
                                    <div class="flex min-w-0 flex-col gap-1">
                                        <Label
                                            :class="moduleLabelLightClass"
                                            >Prix unitaire
                                            <span class="text-[#dc3545]">*</span></Label
                                        >
                                        <div
                                            class="flex h-[42px] items-center overflow-hidden rounded-[10px] border border-[#ccc5c5] dark:border-border bg-white dark:bg-input"
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
                                                class="pr-3 text-base font-medium text-black dark:text-foreground"
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
                                    <div class="flex min-w-0 flex-col gap-1">
                                        <Label
                                            :class="moduleLabelLightClass"
                                            >Total</Label
                                        >
                                        <div
                                            class="flex h-[42px] items-center overflow-hidden rounded-[10px] border border-[#ccc5c5] dark:border-border bg-[#f8fafc] px-3 text-sm text-black dark:text-foreground"
                                        >
                                            <span class="min-w-0 flex-1 font-medium tabular-nums">{{
                                                (
                                                    (Number(p.prix_unitaire) ||
                                                        0) *
                                                    (p.quantite || 0)
                                                ).toFixed(1)
                                            }}</span>
                                            <span
                                                class="pl-1 text-base font-medium text-black dark:text-foreground"
                                                >xaf</span
                                            >
                                        </div>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="mt-1 shrink-0 self-start text-[rgba(92,89,89,0.4)] hover:text-[#dc3545]"
                                    @click="removeProduit(i)"
                                >
                                    <X class="size-4" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Section 4 — Parapharmacie : même grille que Médicaments -->
                    <div :class="moduleFormSectionClass">
                        <div
                            class="mb-4 flex flex-wrap items-center justify-between gap-2"
                        >
                            <p
                                class="text-[20px] font-black italic text-[rgba(92,89,89,0.4)]"
                            >
                                Parapharmacie
                            </p>
                            <button
                                type="button"
                                class="flex items-center gap-2 rounded-[10px] bg-[#459cd1] px-3.5 py-2 text-sm font-black text-white hover:bg-[#3a87b8]"
                                @click="addProduitParapharma"
                            >
                                <ShoppingBag class="size-5" />
                                Ajouter un produit parapharmacie
                            </button>
                        </div>

                        <div
                            v-for="(p, i) in form.produitsParapharma"
                            :key="`para-${i}`"
                            :class="moduleFormProductCardClass"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div
                                    class="grid min-w-0 flex-1 grid-cols-1 gap-x-4 gap-y-4 pr-1 md:grid-cols-[minmax(9.5rem,1.35fr)_minmax(5.75rem,0.85fr)_minmax(6.5rem,0.95fr)_minmax(7.25rem,1.05fr)]"
                                >
                                    <div class="flex min-w-0 flex-col gap-1 pr-0.5">
                                        <Label
                                            :class="moduleLabelLightClass"
                                            >Nom produit
                                            <span class="text-[#dc3545]">*</span></Label
                                        >
                                        <input
                                            v-model="p.designation"
                                            placeholder="Ex : 1000"
                                            class="h-[42px] rounded-[10px] border border-[#ccc5c5] dark:border-border bg-white dark:bg-input px-3 py-2 text-sm placeholder:italic placeholder:text-[rgba(92,89,89,0.4)] focus:border-[#459cd1] focus:outline-none"
                                            :class="{
                                                'border-[#dc3545]':
                                                    getProduitParapharmaError(
                                                        i,
                                                        'designation',
                                                    ),
                                            }"
                                        />
                                        <p
                                            v-if="
                                                getProduitParapharmaError(
                                                    i,
                                                    'designation',
                                                )
                                            "
                                            class="text-xs text-[#dc3545]"
                                        >
                                            {{
                                                getProduitParapharmaError(
                                                    i,
                                                    'designation',
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div class="flex min-w-0 flex-col gap-1 pl-0.5">
                                        <Label
                                            :class="moduleLabelLightClass"
                                            >Dosage</Label
                                        >
                                        <input
                                            v-model="p.dosage"
                                            placeholder="Ex : 1000"
                                            class="h-[42px] rounded-[10px] border border-[#ccc5c5] dark:border-border bg-white dark:bg-input px-3 py-2 text-sm placeholder:italic placeholder:text-[rgba(92,89,89,0.4)] focus:border-[#459cd1] focus:outline-none"
                                        />
                                    </div>
                                    <div class="flex min-w-0 flex-col gap-1">
                                        <Label
                                            :class="moduleLabelLightClass"
                                            >Forme</Label
                                        >
                                        <select
                                            v-model="p.forme"
                                            class="h-[42px] w-full appearance-none rounded-[10px] border border-[#ccc5c5] dark:border-border bg-white dark:bg-input px-3 py-2 pr-8 text-sm focus:border-[#459cd1] focus:outline-none focus:ring-1 focus:ring-[#459cd1]"
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
                                    <div
                                        class="flex min-w-0 flex-col gap-1 md:min-w-[7.25rem]"
                                    >
                                        <Label
                                            :class="moduleLabelLightClass"
                                            >Quantité
                                            <span class="text-[#dc3545]">*</span></Label
                                        >
                                        <input
                                            v-model.number="p.quantite"
                                            type="number"
                                            min="1"
                                            class="box-border h-[42px] w-full min-w-0 max-w-full rounded-[10px] border border-[#ccc5c5] dark:border-border bg-white dark:bg-input px-2 py-2 text-center text-base text-[#5c5959] focus:border-[#459cd1] focus:outline-none md:max-w-[7.5rem]"
                                            :class="{
                                                'border-[#dc3545]':
                                                    getProduitParapharmaError(
                                                        i,
                                                        'quantite',
                                                    ),
                                            }"
                                        />
                                        <p
                                            v-if="
                                                getProduitParapharmaError(
                                                    i,
                                                    'quantite',
                                                )
                                            "
                                            class="text-xs text-[#dc3545]"
                                        >
                                            {{
                                                getProduitParapharmaError(
                                                    i,
                                                    'quantite',
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div class="flex min-w-0 flex-col gap-1">
                                        <Label
                                            :class="moduleLabelLightClass"
                                            >Prix unitaire
                                            <span class="text-[#dc3545]">*</span></Label
                                        >
                                        <div
                                            class="flex h-[42px] items-center overflow-hidden rounded-[10px] border border-[#ccc5c5] dark:border-border bg-white dark:bg-input"
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
                                                        getProduitParapharmaError(
                                                            i,
                                                            'prix_unitaire',
                                                        ),
                                                }"
                                            />
                                            <span
                                                class="pr-3 text-base font-medium text-black dark:text-foreground"
                                                >xaf</span
                                            >
                                        </div>
                                        <p
                                            v-if="
                                                getProduitParapharmaError(
                                                    i,
                                                    'prix_unitaire',
                                                )
                                            "
                                            class="text-xs text-[#dc3545]"
                                        >
                                            {{
                                                getProduitParapharmaError(
                                                    i,
                                                    'prix_unitaire',
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div class="flex min-w-0 flex-col gap-1">
                                        <Label
                                            :class="moduleLabelLightClass"
                                            >Total</Label
                                        >
                                        <div
                                            class="flex h-[42px] items-center overflow-hidden rounded-[10px] border border-[#ccc5c5] dark:border-border bg-[#f8fafc] px-3 text-sm text-black dark:text-foreground"
                                        >
                                            <span class="min-w-0 flex-1 font-medium tabular-nums">{{
                                                (
                                                    (Number(p.prix_unitaire) ||
                                                        0) *
                                                    (p.quantite || 0)
                                                ).toFixed(1)
                                            }}</span>
                                            <span
                                                class="pl-1 text-base font-medium text-black dark:text-foreground"
                                                >xaf</span
                                            >
                                        </div>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="mt-1 shrink-0 self-start text-[rgba(92,89,89,0.4)] hover:text-[#dc3545]"
                                    @click="removeProduitParapharma(i)"
                                >
                                    <X class="size-4" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Ordonnance (dashed #e2e8f0) + Commentaires (solid #e2e8f0) : deux blocs égaux côte à côte -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="flex flex-col gap-2">
                            <Label class="text-sm font-medium text-black dark:text-foreground"
                                >Ordonnance
                                <span
                                    v-if="
                                        isFieldRequired('ordonnance') &&
                                        !(
                                            mode === 'relance' &&
                                            ordonnanceUrlExistante &&
                                            !form.ordonnance
                                        )
                                    "
                                    class="text-[#dc3545]"
                                    >*</span
                                ></Label
                            >
                            <p
                                v-if="
                                    ordonnanceUrlExistante && !form.ordonnance
                                "
                                class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900"
                            >
                                Ordonnance déjà enregistrée —
                                <a
                                    :href="ordonnanceUrlExistante"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="font-medium text-amber-950 underline underline-offset-2 hover:text-amber-800"
                                >
                                    Ouvrir l’ordonnance
                                </a>
                                — ajoutez un fichier ci-dessous pour la
                                remplacer (facultatif).
                            </p>
                            <OrdonnanceUppy
                                v-model="form.ordonnance"
                                variant="card"
                            />
                            <p
                                v-if="errors.ordonnance"
                                class="text-xs text-[#dc3545]"
                            >
                                {{ errors.ordonnance }}
                            </p>
                        </div>
                        <div class="flex flex-col">
                            <Label class="mb-1.5 text-sm font-medium text-black dark:text-foreground"
                                >Commentaires
                                <span
                                    v-if="isFieldRequired('commentaire')"
                                    class="text-[#dc3545]"
                                    >*</span
                                ></Label
                            >
                            <textarea
                                v-model="form.commentaire"
                                placeholder="Commentaires ..."
                                rows="4"
                                class="min-h-[120px] resize-none rounded-[10px] border border-[#e2e8f0] bg-white dark:bg-input p-3 text-sm placeholder:italic placeholder:text-[#94a3b8] focus:border-[#459cd1] focus:outline-none focus:ring-1 focus:ring-[#459cd1]"
                                :class="{
                                    'border-[#dc3545]': errors.commentaire,
                                }"
                            />
                            <p
                                v-if="errors.commentaire"
                                class="mt-1 text-xs text-[#dc3545]"
                            >
                                {{ errors.commentaire }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Footer sticky : Annuler #EF4444, Envoyer #459cd1, rounded 8–10px, padding ~40px -->
                <div
                    class="sticky bottom-0 flex justify-center gap-6 border-t border-[#e2e8f0] bg-white px-6 py-5 shadow-[0_-2px_10px_rgba(0,0,0,0.04)] dark:border-border dark:bg-card"
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
                        class="rounded-[10px] bg-[#459cd1] px-10 py-2.5 text-[15px] font-bold text-white hover:bg-[#3a87b8]"
                    >
                        Envoyer
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
