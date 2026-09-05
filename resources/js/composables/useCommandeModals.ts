import { router } from '@inertiajs/vue3';
import { type ComputedRef, type Ref, ref } from 'vue';
import { useCommandeReferentiels } from '@/composables/useCommandeReferentiels';
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
}) {
    const referentiels = useCommandeReferentiels(options.canManageCommandes);

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

    return {
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
    };
}

export type { MotifOption };
