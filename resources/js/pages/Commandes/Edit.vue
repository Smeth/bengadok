<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ChevronDown, FileEdit, Pill, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import OrdonnanceAnalysisProgressBar from '@/components/OrdonnanceAnalysisProgressBar.vue';
import OrdonnanceFilePreview from '@/components/OrdonnanceFilePreview.vue';
import OrdonnanceUppy from '@/components/OrdonnanceUppy.vue';
import PharmacieSearchPicker from '@/components/PharmacieSearchPicker.vue';
import BackLink from '@/components/ui/BackLink.vue';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { fieldError, normalizeInertiaErrors } from '@/lib/validationErrors';
import { formatCommandeDateHeure } from '@/lib/formatDateLocal';
import {
    moduleFormInputClass,
    moduleFormSelectClass,
} from '@/lib/bengadokUi';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const props = defineProps<{
    commande: {
        id: number;
        numero: string;
        date: string;
        heurs: string;
        commentaire?: string;
        beneficiaire?: string;
        client: {
            id: number;
            nom: string;
            prenom: string;
            tel: string;
            adresse?: string;
            arrondissement?: string | null;
        };
        pharmacie: {
            id: number;
            designation: string;
            zone?: { designation: string };
            adresse?: string;
        };
        produits: Array<{
            id: number;
            designation: string;
            dosage?: string;
            forme?: string;
            type?: string | null;
            pivot: {
                quantite: number;
                prix_unitaire: number;
                type?: string | null;
            };
        }>;
        mode_paiement?: { id: number; designation: string };
        ordonnance?: {
            id: number;
            file_url?: string | null;
            is_pdf?: boolean;
        } | null;
    };
    pharmacies: Array<{
        id: number;
        designation: string;
        zone?: { id?: number; designation?: string };
        zone_id?: number;
        adresse?: string;
        telephone?: string;
    }>;
    modesPaiement: Array<{ id: number; designation: string }>;
    arrondissements: string[];
}>();

const inputClass = moduleFormInputClass;
const selectClass = moduleFormSelectClass;
const sectionTitleClass =
    'text-[20px] font-black italic text-[rgba(92,89,89,0.4)]';
const labelClass = 'text-sm font-medium text-black dark:text-foreground';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: dashboard() },
    { title: 'Commandes', href: '/commandes' },
    {
        title: '#' + props.commande.numero,
        href: `/commandes?detail=${props.commande.id}`,
    },
    { title: 'Modifier', href: '#' },
];

const retourHref = `/commandes?detail=${props.commande.id}`;

const clientId = ref(props.commande.client?.id ?? '');
const clientNom = ref(props.commande.client?.nom ?? '');
const clientPrenom = ref(props.commande.client?.prenom ?? '');
const clientTel = ref(props.commande.client?.tel ?? '');
const clientAdresse = ref(props.commande.client?.adresse ?? '');
const clientArrondissement = ref(
    props.commande.client?.arrondissement ?? '',
);
const pharmacieId = ref(props.commande.pharmacie?.id ?? '');
const beneficiaire = ref(props.commande.beneficiaire ?? '');
const commentaire = ref(props.commande.commentaire ?? '');
const modePaiementId = ref(props.commande.mode_paiement?.id ?? '');
const ordonnanceFile = ref<File | null>(null);
const enSubmission = ref(false);

const dateHeureAffichee = computed(() =>
    formatCommandeDateHeure({
        date: props.commande.date,
        heurs: props.commande.heurs,
    }),
);

const page = usePage();
const errors = computed(() =>
    normalizeInertiaErrors(
        (page.props as { errors?: Record<string, unknown> }).errors,
    ),
);
function produitErr(i: number, field: string): string | undefined {
    return fieldError(errors.value, `produits.${i}.${field}`);
}

function fieldHasError(key: string): boolean {
    return Boolean(errors.value[key]);
}

type OvSettings = { enabled?: boolean; execution_mode?: string };
const ordonnanceVerificationSettings = computed(
    () => page.props.ordonnanceVerificationSettings as OvSettings | undefined,
);

const analysisNoticeText = computed(() => {
    const ov = ordonnanceVerificationSettings.value;
    if (ov && ov.enabled === false) {
        return 'La vérification automatique des ordonnances est désactivée dans les paramètres.';
    }
    if (ov?.execution_mode === 'immediate') {
        return 'À l’enregistrement avec un nouveau fichier, l’analyse (OCR et règles) s’exécute pendant la requête. Le résultat est visible sur la fiche commande.';
    }
    return 'Après enregistrement, le fichier est mis en file d’analyse. Le résultat apparaît sur la fiche sous l’aperçu (mise à jour automatique).';
});

const submitProgressLabel = computed(() => {
    const ov = ordonnanceVerificationSettings.value;
    if (!ordonnanceFile.value || ov?.enabled === false) {
        return 'Enregistrement des modifications…';
    }
    if (ov?.execution_mode === 'immediate') {
        return 'Enregistrement et analyse de l’ordonnance en cours…';
    }
    return 'Enregistrement des modifications…';
});

const showSubmitAnalysisProgress = computed(
    () =>
        enSubmission.value &&
        ordonnanceFile.value !== null &&
        ordonnanceVerificationSettings.value?.enabled !== false,
);

type ProduitLigne = {
    id?: number;
    designation: string;
    dosage: string;
    forme: string;
    type?: string;
    quantite: number;
    prix_unitaire: number;
};
const produitsSelection = ref<ProduitLigne[]>([]);

const formesPharmaceutiques = [
    'Comprimé',
    'Gélule',
    'Sirop',
    'Injectable',
    'Pommade',
    'Suppositoire',
    'Collyre',
    'Spray',
    'Sachet',
    'Ampoule',
    'Patch',
] as const;

watch(
    () => props.commande?.produits,
    () => {
        produitsSelection.value = (props.commande?.produits ?? []).map((p) => ({
            id: p.id,
            designation: p.designation ?? '',
            dosage: p.dosage ?? '',
            forme: p.forme ?? '',
            type: p.pivot?.type ?? p.type ?? '',
            quantite: p.pivot?.quantite ?? 1,
            prix_unitaire: Number(p.pivot?.prix_unitaire ?? 0),
        }));
    },
    { immediate: true },
);

function ligneTotal(p: ProduitLigne): string {
    return String(
        Math.round((Number(p.prix_unitaire) || 0) * (p.quantite || 0)),
    );
}

function ajouterProduit() {
    produitsSelection.value.push({
        designation: '',
        dosage: '',
        forme: '',
        quantite: 1,
        prix_unitaire: 0,
    });
}

function supprimerProduit(i: number) {
    produitsSelection.value.splice(i, 1);
}

function submit() {
    const produitsValides = produitsSelection.value
        .filter(
            (p) =>
                p.designation.trim() &&
                p.quantite > 0 &&
                Number(p.prix_unitaire) >= 0,
        )
        .map((p) => ({
            id: p.id,
            designation: p.designation.trim(),
            dosage: (p.dosage ?? '').trim() || null,
            forme: (p.forme ?? '').trim() || null,
            type: (p.type ?? '').trim() || null,
            quantite: p.quantite,
            prix_unitaire: Number(p.prix_unitaire),
        }));
    if (!produitsValides.length) return;
    if (!pharmacieId.value) return;

    enSubmission.value = true;

    const payload: Record<string, unknown> = {
        client_id: clientId.value || undefined,
        client_nom: clientNom.value.trim(),
        client_prenom: clientPrenom.value.trim(),
        client_tel: clientTel.value.trim(),
        client_adresse: clientAdresse.value.trim(),
        client_arrondissement: clientArrondissement.value || undefined,
        pharmacie_id: pharmacieId.value || undefined,
        beneficiaire: beneficiaire.value.trim(),
        produits: produitsValides,
        mode_paiement_id: modePaiementId.value || undefined,
        commentaire: commentaire.value.trim(),
    };

    if (ordonnanceFile.value) {
        const formData = new FormData();
        Object.entries(payload).forEach(([k, v]) => {
            if (v !== undefined && v !== '' && k !== 'produits')
                formData.append(k, String(v));
        });
        formData.append('produits', JSON.stringify(produitsValides));
        formData.append('ordonnance', ordonnanceFile.value);
        formData.append('_method', 'PATCH');
        router.post(`/commandes/${props.commande.id}`, formData, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                enSubmission.value = false;
            },
        });
    } else {
        router.patch(`/commandes/${props.commande.id}`, payload, {
            preserveScroll: true,
            onFinish: () => {
                enSubmission.value = false;
            },
        });
    }
}
</script>

<template>
    <Head :title="`Modifier commande ${commande.numero} - BengaDok`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="relative min-h-full overflow-x-auto rounded-xl p-6 md:p-8"
        >
            <div
                class="relative overflow-hidden rounded-[30px] bg-white p-6 shadow-[0px_4px_10px_rgba(0,0,0,0.25)] dark:border dark:border-border dark:bg-card md:p-8"
            >
                <!-- En-tête -->
                <div
                    class="mb-6 flex flex-col gap-4 border-b border-[#ccc5c5] pb-5 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
                        <BackLink :href="retourHref">
                            Retour à la commande
                        </BackLink>
                        <h1
                            class="flex min-w-0 items-center gap-3 text-xl font-black tracking-wide text-[#459cd1]"
                        >
                            <FileEdit
                                class="size-5 shrink-0 text-[#459cd1]"
                                aria-hidden="true"
                            />
                            <span class="truncate"
                                >Modifier {{ commande.numero }}</span
                            >
                        </h1>
                    </div>
                    <Link
                        :href="retourHref"
                        class="inline-flex shrink-0 items-center justify-center rounded-[10px] border border-[#ccc5c5] bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50"
                    >
                        Annuler
                    </Link>
                </div>

                <div
                    v-if="Object.keys(errors).length > 0"
                    class="mb-5 rounded-[10px] border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
                    role="alert"
                >
                    Veuillez corriger les champs indiqués ci-dessous.
                </div>

                <form class="space-y-5" @submit.prevent="submit">
                    <div class="grid gap-5 lg:grid-cols-2">
                        <!-- Client -->
                        <section
                            class="rounded-[10px] border border-[#ccc5c5] p-5 dark:border-border"
                        >
                            <h2 :class="[sectionTitleClass, 'mb-4']">
                                Informations client
                            </h2>
                            <div class="space-y-4">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="flex flex-col gap-1.5">
                                        <Label :class="labelClass"
                                            >Prénom
                                            <span class="text-[#dc3545]"
                                                >*</span
                                            ></Label
                                        >
                                        <input
                                            v-model="clientPrenom"
                                            type="text"
                                            placeholder="Ex : Dalia"
                                            :class="[
                                                inputClass,
                                                {
                                                    'border-[#dc3545]':
                                                        fieldHasError(
                                                            'client_prenom',
                                                        ),
                                                },
                                            ]"
                                        />
                                        <InputError
                                            :message="errors.client_prenom"
                                        />
                                    </div>
                                    <div class="flex flex-col gap-1.5">
                                        <Label :class="labelClass"
                                            >Nom
                                            <span
                                                class="text-xs font-normal text-[rgba(92,89,89,0.6)]"
                                                >(facultatif)</span
                                            ></Label
                                        >
                                        <input
                                            v-model="clientNom"
                                            type="text"
                                            placeholder="Ex : Fofana"
                                            :class="inputClass"
                                        />
                                        <InputError
                                            :message="errors.client_nom"
                                        />
                                    </div>
                                </div>
                                <div class="flex flex-col gap-1.5">
                                    <Label :class="labelClass"
                                        >Téléphone
                                        <span class="text-[#dc3545]"
                                            >*</span
                                        ></Label
                                    >
                                    <input
                                        v-model="clientTel"
                                        type="text"
                                        required
                                        placeholder="Ex : 068544242"
                                        :class="[
                                            inputClass,
                                            {
                                                'border-[#dc3545]':
                                                    fieldHasError(
                                                        'client_tel',
                                                    ),
                                            },
                                        ]"
                                    />
                                    <InputError
                                        :message="errors.client_tel"
                                    />
                                </div>
                                <div class="flex flex-col gap-1.5">
                                    <Label :class="labelClass"
                                        >Adresse
                                        <span class="text-[#dc3545]"
                                            >*</span
                                        ></Label
                                    >
                                    <input
                                        v-model="clientAdresse"
                                        type="text"
                                        required
                                        placeholder="Ex : La Glacière"
                                        :class="[
                                            inputClass,
                                            {
                                                'border-[#dc3545]':
                                                    fieldHasError(
                                                        'client_adresse',
                                                    ),
                                            },
                                        ]"
                                    />
                                    <InputError
                                        :message="errors.client_adresse"
                                    />
                                </div>
                                <div class="flex flex-col gap-1.5">
                                    <Label :class="labelClass"
                                        >Arrondissement</Label
                                    >
                                    <div class="relative">
                                        <select
                                            v-model="clientArrondissement"
                                            :class="selectClass"
                                        >
                                            <option value="">
                                                Non renseigné
                                            </option>
                                            <option
                                                v-for="a in arrondissements"
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
                                    <InputError
                                        :message="errors.client_arrondissement"
                                    />
                                </div>
                            </div>
                        </section>

                        <!-- Commande -->
                        <section
                            class="rounded-[10px] border border-[#ccc5c5] p-5 dark:border-border"
                        >
                            <h2 :class="[sectionTitleClass, 'mb-4']">
                                Détails commande
                            </h2>
                            <div class="space-y-4">
                                <div class="flex flex-col gap-1.5">
                                    <Label :class="labelClass"
                                        >Pharmacie
                                        <span class="text-[#dc3545]"
                                            >*</span
                                        ></Label
                                    >
                                    <PharmacieSearchPicker
                                        v-model="pharmacieId"
                                        :pharmacies="pharmacies"
                                        :error="errors.pharmacie_id"
                                    />
                                    <InputError
                                        :message="errors.pharmacie_id"
                                    />
                                </div>
                                <div class="flex flex-col gap-1.5">
                                    <Label :class="labelClass"
                                        >Date et heure</Label
                                    >
                                    <p
                                        class="flex h-[42px] items-center rounded-[10px] border border-[#ccc5c5] bg-[#f8fafc] px-3 text-sm text-gray-700"
                                    >
                                        {{ dateHeureAffichee }}
                                    </p>
                                    <p class="text-xs text-[rgba(92,89,89,0.6)]">
                                        Fixées automatiquement à la création de
                                        la commande.
                                    </p>
                                </div>
                                <div class="flex flex-col gap-1.5">
                                    <Label :class="labelClass"
                                        >Bénéficiaire</Label
                                    >
                                    <input
                                        v-model="beneficiaire"
                                        type="text"
                                        placeholder="Ex : Soi-même"
                                        :class="inputClass"
                                    />
                                    <InputError
                                        :message="errors.beneficiaire"
                                    />
                                </div>
                                <div class="flex flex-col gap-1.5">
                                    <Label :class="labelClass"
                                        >Commentaire</Label
                                    >
                                    <textarea
                                        v-model="commentaire"
                                        rows="3"
                                        placeholder="Commentaire interne…"
                                        class="min-h-[88px] w-full resize-y rounded-[10px] border border-[#ccc5c5] bg-white px-3 py-2 text-sm text-gray-900 placeholder:italic placeholder:text-[rgba(92,89,89,0.4)] focus:border-[#459cd1] focus:outline-none focus:ring-1 focus:ring-[#459cd1]"
                                    />
                                    <InputError
                                        :message="errors.commentaire"
                                    />
                                </div>
                            </div>
                        </section>
                    </div>

                    <!-- Médicaments -->
                    <section
                        class="rounded-[10px] border border-[#ccc5c5] p-5 dark:border-border"
                    >
                        <div
                            class="mb-4 flex flex-wrap items-center justify-between gap-2"
                        >
                            <h2 :class="sectionTitleClass">
                                Médicaments
                                <span class="text-[#dc3545] not-italic"
                                    >*</span
                                >
                            </h2>
                            <button
                                type="button"
                                class="flex items-center gap-2 rounded-[10px] bg-[#459cd1] px-3.5 py-2 text-sm font-black text-white transition-colors hover:bg-[#3a87b8]"
                                @click="ajouterProduit"
                            >
                                <Pill class="size-5" />
                                Ajouter un médicament
                            </button>
                        </div>
                        <InputError :message="errors.produits" />

                        <div
                            v-for="(p, i) in produitsSelection"
                            :key="i"
                            class="mb-4 rounded-[10px] border border-[#ccc5c5] bg-[#fafafa] p-4 last:mb-0 dark:border-border dark:bg-muted/30"
                        >
                            <div
                                class="flex items-start justify-between gap-3"
                            >
                                <div
                                    class="grid min-w-0 flex-1 grid-cols-1 gap-x-4 gap-y-4 md:grid-cols-[minmax(9.5rem,1.35fr)_minmax(5.75rem,0.85fr)_minmax(6.5rem,0.95fr)_minmax(7.25rem,1.05fr)]"
                                >
                                    <div class="flex min-w-0 flex-col gap-1">
                                        <Label
                                            class="text-base font-light text-black"
                                            >Nom médicament
                                            <span class="text-[#dc3545]"
                                                >*</span
                                            ></Label
                                        >
                                        <input
                                            v-model="p.designation"
                                            placeholder="Ex : Vivagest"
                                            :class="[
                                                inputClass,
                                                {
                                                    'border-[#dc3545]':
                                                        produitErr(
                                                            i,
                                                            'designation',
                                                        ),
                                                },
                                            ]"
                                        />
                                        <InputError
                                            :message="
                                                produitErr(i, 'designation')
                                            "
                                        />
                                    </div>
                                    <div class="flex min-w-0 flex-col gap-1">
                                        <Label
                                            class="text-base font-light text-black"
                                            >Dosage</Label
                                        >
                                        <input
                                            v-model="p.dosage"
                                            placeholder="Ex : 1000 mg"
                                            :class="inputClass"
                                        />
                                    </div>
                                    <div class="flex min-w-0 flex-col gap-1">
                                        <Label
                                            class="text-base font-light text-black"
                                            >Forme</Label
                                        >
                                        <div class="relative">
                                            <select
                                                v-model="p.forme"
                                                :class="selectClass"
                                            >
                                                <option value="">
                                                    Choisir…
                                                </option>
                                                <option
                                                    v-for="f in formesPharmaceutiques"
                                                    :key="f"
                                                    :value="f"
                                                >
                                                    {{ f }}
                                                </option>
                                            </select>
                                            <ChevronDown
                                                class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-[rgba(92,89,89,0.4)]"
                                            />
                                        </div>
                                    </div>
                                    <div class="flex min-w-0 flex-col gap-1">
                                        <Label
                                            class="text-base font-light text-black"
                                            >Quantité
                                            <span class="text-[#dc3545]"
                                                >*</span
                                            ></Label
                                        >
                                        <input
                                            v-model.number="p.quantite"
                                            type="number"
                                            min="1"
                                            :class="[
                                                inputClass,
                                                'text-center md:max-w-[7.5rem]',
                                                {
                                                    'border-[#dc3545]':
                                                        produitErr(
                                                            i,
                                                            'quantite',
                                                        ),
                                                },
                                            ]"
                                        />
                                        <InputError
                                            :message="produitErr(i, 'quantite')"
                                        />
                                    </div>
                                    <div class="flex min-w-0 flex-col gap-1">
                                        <Label
                                            class="text-base font-light text-black"
                                            >Prix unitaire
                                            <span class="text-[#dc3545]"
                                                >*</span
                                            ></Label
                                        >
                                        <div
                                            class="flex h-[42px] items-center overflow-hidden rounded-[10px] border border-[#ccc5c5] bg-white"
                                            :class="{
                                                'border-[#dc3545]':
                                                    produitErr(
                                                        i,
                                                        'prix_unitaire',
                                                    ),
                                            }"
                                        >
                                            <input
                                                v-model.number="p.prix_unitaire"
                                                type="number"
                                                min="0"
                                                step="1"
                                                placeholder="0"
                                                class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2 text-sm outline-none focus:ring-0"
                                            />
                                            <span
                                                class="pr-3 text-sm font-medium text-black"
                                                >xaf</span
                                            >
                                        </div>
                                        <InputError
                                            :message="
                                                produitErr(i, 'prix_unitaire')
                                            "
                                        />
                                    </div>
                                    <div class="flex min-w-0 flex-col gap-1">
                                        <Label
                                            class="text-base font-light text-black"
                                            >Total</Label
                                        >
                                        <div
                                            class="flex h-[42px] items-center rounded-[10px] border border-[#ccc5c5] bg-[#f8fafc] px-3 text-sm tabular-nums text-black"
                                        >
                                            {{ ligneTotal(p) }}
                                            <span class="ml-1 font-medium"
                                                >xaf</span
                                            >
                                        </div>
                                    </div>
                                </div>
                                <button
                                    v-if="produitsSelection.length > 1"
                                    type="button"
                                    class="mt-1 shrink-0 rounded-lg p-2 text-[rgba(92,89,89,0.5)] transition-colors hover:bg-red-50 hover:text-[#dc3545]"
                                    aria-label="Supprimer la ligne"
                                    @click="supprimerProduit(i)"
                                >
                                    <X class="size-4" />
                                </button>
                            </div>
                        </div>
                    </section>

                    <!-- Ordonnance -->
                    <section
                        class="rounded-[10px] border border-[#ccc5c5] p-5 dark:border-border"
                    >
                        <h2 :class="[sectionTitleClass, 'mb-4']">
                            Ordonnance
                        </h2>
                        <p class="mb-3 text-sm font-medium text-black">
                            Nouvelle ordonnance (remplace l'actuelle)
                        </p>
                        <p
                            v-if="
                                commande.ordonnance?.file_url &&
                                !ordonnanceFile
                            "
                            class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900"
                        >
                            Ordonnance déjà enregistrée —
                            <a
                                :href="commande.ordonnance.file_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="font-medium text-amber-950 underline underline-offset-2 hover:text-amber-800"
                            >
                                Ouvrir l'ordonnance
                            </a>
                            — ajoutez un fichier ci-dessous pour la remplacer
                            (facultatif).
                        </p>
                        <p
                            class="mb-3 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs leading-relaxed text-sky-950"
                        >
                            {{ analysisNoticeText }}
                        </p>
                        <OrdonnanceUppy
                            v-model="ordonnanceFile"
                            variant="card"
                        />
                        <InputError :message="errors.ordonnance" />
                        <OrdonnanceAnalysisProgressBar
                            class="mt-2"
                            :visible="showSubmitAnalysisProgress"
                            :label="submitProgressLabel"
                        />
                        <OrdonnanceFilePreview
                            v-if="ordonnanceFile"
                            :file="ordonnanceFile"
                            class="mt-3"
                            max-height="14rem"
                        />
                    </section>

                    <!-- Paiement -->
                    <section
                        class="rounded-[10px] border border-[#ccc5c5] p-5 dark:border-border"
                    >
                        <h2 :class="[sectionTitleClass, 'mb-4']">
                            Paiement
                        </h2>
                        <div class="max-w-md">
                            <div class="flex flex-col gap-1.5">
                                <Label :class="labelClass"
                                    >Mode de paiement</Label
                                >
                                <div class="relative">
                                    <select
                                        v-model="modePaiementId"
                                        :class="selectClass"
                                    >
                                        <option value="">—</option>
                                        <option
                                            v-for="m in modesPaiement"
                                            :key="m.id"
                                            :value="m.id"
                                        >
                                            {{ m.designation }}
                                        </option>
                                    </select>
                                    <ChevronDown
                                        class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-[rgba(92,89,89,0.4)]"
                                    />
                                </div>
                                <InputError
                                    :message="errors.mode_paiement_id"
                                />
                            </div>
                        </div>
                        <p class="mt-3 text-xs text-muted-foreground">
                            Les frais de livraison se définissent depuis la
                            fiche détail de la commande (après retour
                            pharmacie).
                        </p>
                    </section>

                    <!-- Actions -->
                    <div
                        class="flex flex-wrap items-center justify-end gap-3 border-t border-[#ccc5c5] pt-5"
                    >
                        <Link
                            :href="retourHref"
                            class="inline-flex items-center justify-center rounded-[10px] border border-[#ccc5c5] bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50"
                        >
                            Annuler
                        </Link>
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-[10px] bg-[#459cd1] px-6 py-2.5 text-sm font-black text-white shadow-sm transition-colors hover:bg-[#3a87b8] disabled:opacity-60"
                            :disabled="enSubmission"
                        >
                            {{
                                enSubmission
                                    ? 'Enregistrement…'
                                    : 'Enregistrer les modifications'
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
