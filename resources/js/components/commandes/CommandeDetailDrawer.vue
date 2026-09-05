<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Check,
    CheckCircle2,
    FileText,
    Pencil,
    Paperclip,
    RefreshCw,
    Truck,
    X,
    XCircle,
} from 'lucide-vue-next';
import { computed, toRef } from 'vue';
import OrdonnanceAnalysisProgressBar from '@/components/OrdonnanceAnalysisProgressBar.vue';
import OrdonnanceViewer from '@/components/OrdonnanceViewer.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useCommandeDetail } from '@/composables/useCommandeDetail';
import {
    classesPivotStatusProduit,
    classesVenteLibrePivot,
    descriptionDecisionVerification,
    formatDateHeureCommande,
    getClientDisplayName,
    libelleDecisionVerification,
    libellePivotStatusProduit,
    libelleVenteLibrePivot,
} from '@/lib/commandeDetailDisplay';
import {
    moduleDetailHeaderClass,
    moduleDetailPanelClass,
    modulePrimaryButtonClass,
    modulePrimaryButtonSolidClass,
    modulePrimaryTextClass,
} from '@/lib/bengadokUi';
import {
    commandeStatutBadgeStyle,
    commandeStatutLabel,
} from '@/types';
import type { CommandeDetail } from '@/types';

type MotifOption = { key: string; label: string; desc: string };

type ReferentielLivreur = {
    id: number;
    nom: string;
    prenom: string;
    tel: string;
};

const props = defineProps<{
    canManageCommandes: boolean;
    canCreateCommande: boolean;
    livreurs: ReferentielLivreur[];
    montantsLivraison: Array<{ id: number; designation: number }>;
    modesPaiement: Array<{ id: number; designation: string }>;
    parapharmaProduitTypes: string[];
    motifOptions: MotifOption[];
    motifsRelance: Record<string, boolean>;
    motifLabelBySlug: Record<string, string>;
    ensureReferentiels: () => void;
}>();

const emit = defineEmits<{
    'open-recu': [commande: CommandeDetail];
    'open-relancer': [commande: CommandeDetail];
}>();

const canCreateCommandeRef = computed(() => props.canCreateCommande);
const canManageCommandesRef = computed(() => props.canManageCommandes);
const motifOptionsRef = computed(() => props.motifOptions);
const motifsRelanceRef = computed(() => props.motifsRelance);
const motifLabelBySlugRef = computed(() => props.motifLabelBySlug);

const {
    detailCommande,
    complementairesForm,
    savingComplementaires,
    showDetailModal,
    showAnnulerModal,
    showValiderModal,
    loadingDetail,
    motifAnnulation,
    noteAnnulation,
    isAgent,
    enAttentePharmacieToutIndisponible,
    peutValiderCommandeEnAttente,
    enfantEnAttenteSansPaiementComplet,
    peutModifierCommandeComplete,
    peutEditerComplementaires,
    detailSplit,
    sousTotal,
    totalDetail,
    motifAutoriseRelance,
    getMotifAnnulationLabel,
    openDetail,
    closeDetail,
    saveComplementaires,
    updateStatus,
    openValiderModal,
    confirmValiderCommande,
    setMontantLivraison,
    setModePaiementCommande,
    peutAssignerLivreurDetail,
    setLivreurCommande,
    openAnnulerModal,
    confirmAnnuler,
    confirmAnnulerEtRelancer,
} = useCommandeDetail({
    canManageCommandes: canManageCommandesRef,
    canCreateCommande: canCreateCommandeRef,
    parapharmaProduitTypes: toRef(props, 'parapharmaProduitTypes'),
    montantsLivraison: toRef(props, 'montantsLivraison'),
    modesPaiement: toRef(props, 'modesPaiement'),
    livreurs: toRef(props, 'livreurs'),
    motifOptions: motifOptionsRef,
    motifsRelance: motifsRelanceRef,
    motifLabelBySlug: motifLabelBySlugRef,
    ensureReferentiels: () => props.ensureReferentiels(),
});

function onAnnulerEtRelancer() {
    confirmAnnulerEtRelancer(() => {
        if (detailCommande.value) {
            emit('open-relancer', detailCommande.value);
        }
    });
}

defineExpose({
    openDetail,
    closeDetail,
    detailCommande,
});
</script>

<template>
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
                    class="size-5 shrink-0 animate-spin text-[#459cd1]"
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
                :class="moduleDetailHeaderClass"
            >
                <div class="min-w-0 flex-1 overflow-hidden">
                    <p
                        class="truncate text-[18px] font-bold text-gray-800"
                        :title="detailCommande.numero"
                    >
                        {{ detailCommande.numero }}
                    </p>
                    <p class="text-[13px] font-medium text-gray-500">
                        Date :
                        {{ formatDateHeureCommande(detailCommande) }}
                    </p>
                </div>
                <div
                    class="flex shrink-0 flex-wrap items-center justify-end gap-2"
                >
                    <span
                        class="rounded-full px-3 py-1 text-[12px] font-bold whitespace-nowrap"
                        :style="commandeStatutBadgeStyle(detailCommande.status)"
                    >
                        {{ commandeStatutLabel(detailCommande.status) }}
                    </span>
                    <Link
                        v-if="peutModifierCommandeComplete"
                        :href="`/commandes/${detailCommande.id}/edit`"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-[#459cd1]/30 bg-[#459cd1]/5 px-3 py-1.5 text-[12px] font-semibold text-[#459cd1] transition-colors hover:bg-[#459cd1]/10"
                    >
                        <Pencil class="size-3.5 shrink-0" />
                        Modifier
                    </Link>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-900"
                        aria-label="Fermer le panneau"
                        @click="closeDetail"
                    >
                        <X class="size-5" />
                    </button>
                </div>
            </div>

            <div class="space-y-4 p-6 pb-8">
                <!-- Informations du client -->
                <div
                    :class="moduleDetailPanelClass"
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
                    :class="moduleDetailPanelClass"
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
                    v-if="detailCommande.enfants?.length"
                    :class="moduleDetailPanelClass"
                >
                    <h3
                        class="mb-2 text-[14px] font-bold text-[#b4b4b4]"
                    >
                        Commandes associées (autres pharmacies)
                    </h3>
                    <p
                        v-if="detailCommande.status === 'en_attente'"
                        class="mb-3 text-[12px] leading-relaxed text-gray-500"
                    >
                        Avant validation globale, le montant de livraison
                        et le mode de paiement doivent être choisis pour
                        chaque maillon encore « en attente ».
                    </p>
                    <p
                        v-else
                        class="mb-3 text-[12px] leading-relaxed text-gray-500"
                    >
                        Suivez chaque maillon jusqu'à sa livraison : le
                        crédit pharmacie n'est déduit qu'une fois son
                        propre statut passé à « Livrée ».
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
                                        class="ml-2 inline-block rounded-full px-2 py-0.5 text-[10px] font-bold"
                                        :style="commandeStatutBadgeStyle(e.status)"
                                    >
                                        {{
                                            commandeStatutLabel(e.status)
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
                                class="shrink-0 rounded-lg bg-[#459cd1]/10 px-3 py-1.5 text-[12px] font-bold text-[#459cd1] transition-colors hover:bg-[#459cd1]/20"
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
                    :class="moduleDetailPanelClass"
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
                                    <span
                                        class="rounded-full border px-2.5 py-0.5 text-[11px] font-semibold"
                                        :class="
                                            classesVenteLibrePivot(
                                                p.pivot.vente_libre,
                                            )
                                        "
                                    >
                                        {{
                                            libelleVenteLibrePivot(
                                                p.pivot.vente_libre,
                                            )
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Parapharmacie -->
                <div
                    v-if="detailSplit.parapharma.length"
                    :class="moduleDetailPanelClass"
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
                    class="rounded-2xl border border-dashed border-gray-300 bg-white p-5 shadow-sm transition-colors hover:border-[#459cd1] dark:border-border dark:bg-card dark:hover:border-[#459cd1]"
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
                        class="mt-4 rounded-lg border border-gray-200 bg-gray-50/80 p-3 text-left text-sm dark:border-border dark:bg-muted/30"
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
                    :class="['space-y-4', moduleDetailPanelClass]"
                >
                    <div v-if="peutEditerComplementaires">
                        <h3
                            class="mb-3 text-[14px] font-bold text-[#b4b4b4]"
                        >
                            Informations complémentaires
                        </h3>
                        <div class="space-y-3">
                            <div>
                                <p
                                    class="text-[13px] font-medium text-gray-600"
                                >
                                    Bénéficiaire
                                </p>
                                <p
                                    class="mt-1 text-[14px] text-gray-800"
                                >
                                    {{
                                        detailCommande.beneficiaire?.trim() ||
                                        '—'
                                    }}
                                </p>
                            </div>
                            <div>
                                <Label
                                    for="detail-commentaire"
                                    class="text-[13px] text-gray-600"
                                    >Commentaire (back-office)</Label
                                >
                                <textarea
                                    id="detail-commentaire"
                                    v-model="complementairesForm.commentaire"
                                    rows="3"
                                    placeholder="Notes internes, consignes de livraison…"
                                    class="mt-1 w-full resize-none rounded-lg border border-gray-200 px-3 py-2 text-[14px] focus:border-[#459cd1] focus:outline-none focus:ring-1 focus:ring-[#459cd1] dark:border-border dark:bg-input dark:text-foreground"
                                />
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                :disabled="savingComplementaires"
                                @click="saveComplementaires"
                            >
                                {{
                                    savingComplementaires
                                        ? 'Enregistrement…'
                                        : 'Enregistrer les compléments'
                                }}
                            </Button>
                        </div>
                    </div>
                    <template v-else>
                        <div>
                            <h3
                                class="mb-3 text-[14px] font-bold text-[#b4b4b4]"
                            >
                                Bénéficiaire
                            </h3>
                            <p
                                class="text-[14px] text-gray-700 leading-relaxed"
                            >
                                {{
                                    detailCommande.beneficiaire?.trim() ||
                                    '—'
                                }}
                            </p>
                        </div>
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
                    </template>
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
                    <div
                        v-if="
                            detailCommande.pieces_jointes?.length
                        "
                    >
                        <h3
                            class="mb-3 flex items-center gap-2 text-[14px] font-bold text-[#b4b4b4]"
                        >
                            <Paperclip class="size-4" />
                            Photos (pharmacie)
                        </h3>
                        <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                            <a
                                v-for="pj in detailCommande.pieces_jointes"
                                :key="pj.id"
                                :href="pj.file_url ?? '#'"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="group overflow-hidden rounded-lg border border-gray-100 bg-gray-50"
                            >
                                <img
                                    v-if="pj.file_url"
                                    :src="pj.file_url"
                                    :alt="
                                        pj.label ??
                                        pj.original_name ??
                                        'Photo'
                                    "
                                    class="aspect-square w-full object-cover transition-transform group-hover:scale-[1.02]"
                                    loading="lazy"
                                />
                                <p
                                    v-if="pj.label || pj.original_name"
                                    class="truncate px-1.5 py-1 text-[10px] text-gray-600"
                                >
                                    {{
                                        pj.label ??
                                        pj.original_name
                                    }}
                                </p>
                            </a>
                        </div>
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
                        class="mb-4 flex items-start gap-3 rounded-lg border border-red-200 bg-white/60 p-3 dark:bg-red-950/20"
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
                        class="flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-[#459cd1] text-[15px] font-bold text-white transition-colors hover:bg-[#3a87b8]"
                        @click="detailCommande && emit('open-relancer', detailCommande)"
                    >
                        <RefreshCw class="size-5" />
                        Relancer la commande
                    </button>
                </div>

                <!-- Informations paiement -->
                <div
                    :class="moduleDetailPanelClass"
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
                                class="h-10 min-w-[12rem] max-w-full rounded-xl border border-gray-200 bg-white px-3 text-[13px] font-semibold text-gray-900 focus:border-[#459cd1] focus:outline-none focus:ring-1 focus:ring-[#459cd1] dark:border-border dark:bg-input dark:text-foreground"
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
                                    class="rounded-full border border-gray-200 bg-gray-50 px-3 py-1.5 text-[12px] font-bold text-gray-700 transition-colors hover:border-[#459cd1] hover:bg-blue-50 hover:text-[#459cd1] dark:border-border dark:bg-muted dark:text-foreground dark:hover:bg-[#459cd1]/15"
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
                    :class="moduleDetailPanelClass"
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
                            class="h-11 w-full rounded-xl border border-gray-200 bg-white px-3 text-[14px] text-gray-900 focus:border-[#459cd1] focus:outline-none focus:ring-1 focus:ring-[#459cd1] dark:border-border dark:bg-input dark:text-foreground"
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
                <div class="border-t border-gray-200 bg-white pt-5 dark:border-border dark:bg-card">
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
                                            ? 'bg-[#459cd1] hover:bg-[#3a87b8]'
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
                                class="flex h-12 w-full items-center justify-center rounded-full bg-[#459cd1] text-[15px] font-bold text-white transition-colors hover:bg-[#3a87b8]"
                                @click="emitOpenRecu"
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
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-[#459cd1]/15"
                >
                    <CheckCircle2 class="size-6 text-[#459cd1]" />
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
                class="rounded-[10px] bg-[#459cd1] font-bold text-white hover:bg-[#3a87b8]"
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
        <p class="shrink-0 text-[13px] text-black dark:text-foreground leading-snug">
            Sélectionner le motif d’annulation. Si les médicaments sont
            indisponibles ou selon la configuration du motif, vous
            pouvez relancer la commande avec une autre pharmacie.
        </p>
        <div class="min-h-0 flex-1 space-y-1.5 overflow-y-auto">
            <p class="text-sm font-black text-black dark:text-foreground">
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
                <p class="text-[13px] font-bold text-black dark:text-foreground">
                    {{ opt.label }}
                </p>
                <p class="mt-0.5 text-[12px] font-light text-black dark:text-foreground">
                    {{ opt.desc }}
                </p>
            </div>
        </div>
        <div class="shrink-0 space-y-1">
            <p class="text-sm font-black text-black dark:text-foreground">
                Note complémentaire
                <span class="font-normal">(optionnel)</span>
            </p>
            <textarea
                v-model="noteAnnulation"
                rows="2"
                placeholder="Ajouter des détails supplémentaires sur l'annulation..."
                class="w-full rounded-[10px] border border-[rgba(92,89,89,0.25)] bg-[rgba(0,0,0,0.11)] px-3 py-1.5 text-[13px] placeholder:text-black dark:text-foreground/60 focus:outline-none focus:ring-2 focus:ring-[#e7000b]/50"
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
                    class="h-9 min-w-[180px] rounded-[10px] bg-[#459cd1] text-sm font-black text-white hover:bg-[#3a87b8]"
                    :disabled="!motifAnnulation"
                    @click="onAnnulerEtRelancer"
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
</template>
