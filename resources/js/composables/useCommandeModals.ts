import { router } from '@inertiajs/vue3';
import { type ComputedRef, type Ref, computed, reactive, ref, unref, watch } from 'vue';
import {
    type CommandePharmacyScope,
    useCommandeReferentiels,
} from '@/composables/useCommandeReferentiels';
import type { FormEnregPayload } from '@/lib/commandeEnregistrementTypes';
import {
    submitCommandeEnregistrement,
    submitCommandeRelance,
} from '@/lib/commandeEnregistrementSubmit';
import type { CommandeDetail } from '@/types';

type MotifOption = {
    key: string;
    label: string;
    desc: string;
};

export function useCommandeModals(options: {
    canManageCommandes: ComputedRef<boolean> | Ref<boolean>;
    selectedIds: Ref<Set<number>>;
    clearSelection: () => void;
    onRelanceSuccess?: () => void;
    returnHub?: ComputedRef<'gestion' | undefined> | Ref<'gestion' | undefined>;
}) {
    const pharmacyScope = computed<CommandePharmacyScope>(() =>
        unref(options.returnHub) === 'gestion' ? 'toutes' : 'partenaires',
    );
    const referentiels = useCommandeReferentiels(
        options.canManageCommandes,
        pharmacyScope,
    );

    const relancerCommande = ref<CommandeDetail | null>(null);
    const recuCommande = ref<CommandeDetail | null>(null);
    const showEnregistrementModal = ref(false);
    const showRecuModal = ref(false);
    const showRelancerModal = ref(false);
    const showBulkAnnulerModal = ref(false);
    const motifBulkAnnulation = ref('');
    const apiErrorsEnreg = ref<Record<string, string>>({});
    const errorsRelancer = ref<Record<string, string>>({});

    function openEnregistrementModal() {
        apiErrorsEnreg.value = {};
        referentiels.ensureReferentiels();
        showEnregistrementModal.value = true;
    }

    function onOpenRecu(commande: CommandeDetail) {
        recuCommande.value = commande;
        showRecuModal.value = true;
    }

    function onOpenRelancer(commande: CommandeDetail) {
        relancerCommande.value = commande;
        errorsRelancer.value = {};
        referentiels.ensureReferentiels();
        showRelancerModal.value = true;
    }

    function openBulkAnnulerModal() {
        motifBulkAnnulation.value = '';
        showBulkAnnulerModal.value = true;
    }

    function confirmBulkAnnuler() {
        if (!motifBulkAnnulation.value || options.selectedIds.value.size === 0) {
            return;
        }
        const ids = Array.from(options.selectedIds.value);
        router.post(
            '/commandes/bulk-annuler',
            { ids, motif_annulation: motifBulkAnnulation.value },
            {
                preserveScroll: true,
                onSuccess: () => {
                    showBulkAnnulerModal.value = false;
                    options.clearSelection();
                },
            },
        );
    }

    function submitEnregistrementFromModal(payload: FormEnregPayload) {
        apiErrorsEnreg.value = {};
        const returnHub = unref(options.returnHub);
        if (returnHub) {
            payload._return_hub = returnHub;
        }
        submitCommandeEnregistrement(payload, {
            onSuccess: () => {
                showEnregistrementModal.value = false;
            },
            onError: (errors) => {
                apiErrorsEnreg.value = errors;
            },
        });
    }

    function submitRelancerFromModal(payload: FormEnregPayload) {
        submitCommandeRelance(payload, {
            onSuccess: () => {
                errorsRelancer.value = {};
                showRelancerModal.value = false;
                options.onRelanceSuccess?.();
            },
            onError: (errors) => {
                errorsRelancer.value = errors;
            },
        });
    }

    watch(showEnregistrementModal, (open) => {
        if (!open) {
            apiErrorsEnreg.value = {};
        }
    });

    watch(showRelancerModal, (open) => {
        if (!open) {
            errorsRelancer.value = {};
        }
    });

    return reactive({
        ...referentiels,
        relancerCommande,
        recuCommande,
        showEnregistrementModal,
        showRecuModal,
        showRelancerModal,
        showBulkAnnulerModal,
        motifBulkAnnulation,
        apiErrorsEnreg,
        errorsRelancer,
        openEnregistrementModal,
        onOpenRecu,
        onOpenRelancer,
        openBulkAnnulerModal,
        confirmBulkAnnuler,
        submitEnregistrementFromModal,
        submitRelancerFromModal,
    });
}

export type { MotifOption };
