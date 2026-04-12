<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import OrdonnanceAnalysisProgressBar from '@/components/OrdonnanceAnalysisProgressBar.vue';
import OrdonnanceFilePreview from '@/components/OrdonnanceFilePreview.vue';
import OrdonnanceUppy from '@/components/OrdonnanceUppy.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { fieldError, normalizeInertiaErrors } from '@/lib/validationErrors';
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
            pivot: { quantite: number; prix_unitaire: number };
        }>;
        mode_paiement?: { id: number; designation: string };
        montant_livraison?: { id: number; designation: number };
    };
    pharmacies: Array<{
        id: number;
        designation: string;
        zone?: { designation: string };
        adresse?: string;
    }>;
    modesPaiement: Array<{ id: number; designation: string }>;
    montantsLivraison: Array<{ id: number; designation: number }>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de bord', href: dashboard() },
    { title: 'Commandes', href: '/commandes' },
    {
        title: '#' + props.commande.numero,
        href: `/commandes?detail=${props.commande.id}`,
    },
    { title: 'Modifier', href: '#' },
];

const clientId = ref(props.commande.client?.id ?? '');
const clientNom = ref(props.commande.client?.nom ?? '');
const clientPrenom = ref(props.commande.client?.prenom ?? '');
const clientTel = ref(props.commande.client?.tel ?? '');
const clientAdresse = ref(props.commande.client?.adresse ?? '');
const pharmacieId = ref(props.commande.pharmacie?.id ?? '');
const date = ref(props.commande.date?.slice(0, 10) ?? '');
const heurs = ref(props.commande.heurs ?? '08:00');
const beneficiaire = ref(props.commande.beneficiaire ?? '');
const commentaire = ref(props.commande.commentaire ?? '');
const modePaiementId = ref(props.commande.mode_paiement?.id ?? '');
const montantLivraisonId = ref(props.commande.montant_livraison?.id ?? '');
const ordonnanceFile = ref<File | null>(null);
const enSubmission = ref(false);

const page = usePage();
const errors = computed(() =>
    normalizeInertiaErrors(
        (page.props as { errors?: Record<string, unknown> }).errors,
    ),
);
function produitErr(i: number, field: string): string | undefined {
    return fieldError(errors.value, `produits.${i}.${field}`);
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
    quantite: number;
    prix_unitaire: number;
};
const produitsSelection = ref<ProduitLigne[]>([]);

watch(
    () => props.commande?.produits,
    () => {
        produitsSelection.value = (props.commande?.produits ?? []).map((p) => ({
            id: p.id,
            designation: p.designation ?? '',
            dosage: p.dosage ?? '',
            quantite: p.pivot?.quantite ?? 1,
            prix_unitaire: Number(p.pivot?.prix_unitaire) ?? 0,
        }));
    },
    { immediate: true },
);

function ajouterProduit() {
    produitsSelection.value.push({
        designation: '',
        dosage: '',
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
            quantite: p.quantite,
            prix_unitaire: Number(p.prix_unitaire),
        }));
    if (!produitsValides.length) return;

    enSubmission.value = true;

    const payload: Record<string, unknown> = {
        client_id: clientId.value || undefined,
        client_nom: clientNom.value.trim(),
        client_prenom: clientPrenom.value.trim(),
        client_tel: clientTel.value.trim(),
        client_adresse: clientAdresse.value.trim(),
        pharmacie_id: pharmacieId.value || undefined,
        date: date.value,
        heurs: heurs.value,
        beneficiaire: beneficiaire.value.trim(),
        produits: produitsValides,
        mode_paiement_id: modePaiementId.value || undefined,
        montant_livraison_id: montantLivraisonId.value || undefined,
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
        <form class="flex flex-1 flex-col gap-6 p-4" @submit.prevent="submit">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">
                    Modifier la commande {{ commande.numero }}
                </h1>
                <Link :href="`/commandes?detail=${commande.id}`">
                    <Button variant="outline">Annuler</Button>
                </Link>
            </div>

            <div
                v-if="Object.keys(errors).length > 0"
                class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
                role="alert"
            >
                Veuillez corriger les champs indiqués ci-dessous.
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="space-y-4">
                    <h3 class="font-semibold">Client</h3>
                    <div class="space-y-2">
                        <Label>Prénom (recommandé)</Label>
                        <Input v-model="clientPrenom" placeholder="Prénom" />
                        <InputError :message="errors.client_prenom" />
                    </div>
                    <div class="space-y-2">
                        <Label>Nom (facultatif)</Label>
                        <Input v-model="clientNom" placeholder="Nom" />
                        <InputError :message="errors.client_nom" />
                    </div>
                    <div class="space-y-2">
                        <Label
                            >Téléphone
                            <span class="text-red-600">*</span></Label
                        >
                        <Input
                            v-model="clientTel"
                            required
                            placeholder="Téléphone"
                        />
                        <InputError :message="errors.client_tel" />
                    </div>
                    <div class="space-y-2">
                        <Label
                            >Adresse
                            <span class="text-red-600">*</span></Label
                        >
                        <Input
                            v-model="clientAdresse"
                            required
                            placeholder="Adresse"
                        />
                        <InputError :message="errors.client_adresse" />
                    </div>
                </div>

                <div class="space-y-4">
                    <h3 class="font-semibold">Commande</h3>
                    <div class="space-y-2">
                        <Label
                            >Pharmacie
                            <span class="text-red-600">*</span></Label
                        >
                        <select
                            v-model="pharmacieId"
                            required
                            class="w-full rounded-md border border-input bg-background px-3 py-2"
                        >
                            <option value="">Choisir une pharmacie</option>
                            <option
                                v-for="p in pharmacies"
                                :key="p.id"
                                :value="p.id"
                            >
                                {{ p.designation }} ({{ p.zone?.designation }})
                                - {{ p.adresse }}
                            </option>
                        </select>
                        <InputError :message="errors.pharmacie_id" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label
                                >Date
                                <span class="text-red-600">*</span></Label
                            >
                            <Input v-model="date" type="date" required />
                            <InputError :message="errors.date" />
                        </div>
                        <div class="space-y-2">
                            <Label
                                >Heure
                                <span class="text-red-600">*</span></Label
                            >
                            <Input v-model="heurs" type="time" required />
                            <InputError :message="errors.heurs" />
                        </div>
                    </div>
                    <div class="space-y-2">
                        <Label>Bénéficiaire</Label>
                        <Input
                            v-model="beneficiaire"
                            placeholder="Bénéficiaire"
                        />
                        <InputError :message="errors.beneficiaire" />
                    </div>
                    <div class="space-y-2">
                        <Label>Commentaire</Label>
                        <Input
                            v-model="commentaire"
                            placeholder="Commentaire"
                        />
                        <InputError :message="errors.commentaire" />
                    </div>
                </div>
            </div>

            <div>
                <Label
                    >Médicaments
                    <span class="text-red-600">*</span></Label
                >
                <InputError :message="errors.produits" />
                <div class="mt-2 space-y-2">
                    <div
                        v-for="(p, i) in produitsSelection"
                        :key="i"
                        class="flex flex-col gap-1 sm:flex-row sm:flex-wrap sm:items-end"
                    >
                        <div class="flex min-w-[120px] flex-1 flex-col gap-1">
                            <span class="text-xs text-muted-foreground"
                                >Désignation
                                <span class="text-red-600">*</span></span
                            >
                            <Input
                                v-model="p.designation"
                                placeholder="Désignation"
                                class="w-full"
                            />
                            <InputError :message="produitErr(i, 'designation')" />
                        </div>
                        <div class="flex w-24 flex-col gap-1">
                            <span class="text-xs text-muted-foreground"
                                >Dosage</span
                            >
                            <Input
                                v-model="p.dosage"
                                placeholder="Dosage"
                                class="w-full"
                            />
                            <InputError :message="produitErr(i, 'dosage')" />
                        </div>
                        <div class="flex w-16 flex-col gap-1">
                            <span class="text-xs text-muted-foreground"
                                >Qté
                                <span class="text-red-600">*</span></span
                            >
                            <Input
                                v-model.number="p.quantite"
                                type="number"
                                min="1"
                                class="w-full"
                                placeholder="Qté"
                            />
                            <InputError :message="produitErr(i, 'quantite')" />
                        </div>
                        <div class="flex w-24 flex-col gap-1">
                            <span class="text-xs text-muted-foreground"
                                >Prix
                                <span class="text-red-600">*</span></span
                            >
                            <Input
                                v-model.number="p.prix_unitaire"
                                type="number"
                                min="0"
                                class="w-full"
                                placeholder="Prix"
                            />
                            <InputError :message="produitErr(i, 'prix_unitaire')" />
                        </div>
                        <Button
                            type="button"
                            variant="destructive"
                            size="icon"
                            class="self-end"
                            @click="supprimerProduit(i)"
                            >×</Button
                        >
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        @click="ajouterProduit"
                        >+ Ajouter médicament</Button
                    >
                </div>
            </div>

            <div>
                <OrdonnanceUppy
                    v-model="ordonnanceFile"
                    label="Nouvelle ordonnance (remplace l'actuelle)"
                    show-analysis-notice
                    :analysis-notice="analysisNoticeText"
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
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-2">
                    <Label>Mode de paiement</Label>
                    <select
                        v-model="modePaiementId"
                        class="w-full rounded-md border border-input bg-background px-3 py-2"
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
                    <InputError :message="errors.mode_paiement_id" />
                </div>
                <div class="space-y-2">
                    <Label>Montant livraison</Label>
                    <select
                        v-model="montantLivraisonId"
                        class="w-full rounded-md border border-input bg-background px-3 py-2"
                    >
                        <option value="">—</option>
                        <option
                            v-for="m in montantsLivraison"
                            :key="m.id"
                            :value="m.id"
                        >
                            {{ Number(m.designation).toLocaleString('fr-FR') }}
                            XAF
                        </option>
                    </select>
                    <InputError :message="errors.montant_livraison_id" />
                </div>
            </div>

            <Button type="submit">Enregistrer les modifications</Button>
        </form>
    </AppLayout>
</template>
