import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch, type ComputedRef, type Ref } from 'vue';
import { splitProduitsCommande } from '@/lib/commandeTotals';
import type { CommandeDetail } from '@/types';

type MotifOption = {
    key: string;
    label: string;
    desc: string;
};

type ReferentielLivreur = {
    id: number;
    nom: string;
    prenom: string;
    tel: string;
};

type ReferentielMontant = { id: number; designation: number };

type ReferentielModePaiement = { id: number; designation: string };

export function useCommandeDetail(options: {
    canManageCommandes: ComputedRef<boolean> | Ref<boolean>;
    canCreateCommande: ComputedRef<boolean>;
    parapharmaProduitTypes: Ref<string[]>;
    montantsLivraison: Ref<ReferentielMontant[]>;
    modesPaiement: Ref<ReferentielModePaiement[]>;
    livreurs: Ref<ReferentielLivreur[]>;
    motifOptions: ComputedRef<MotifOption[]>;
    motifsRelance: ComputedRef<Record<string, boolean>>;
    motifLabelBySlug: ComputedRef<Record<string, string>>;
    ensureReferentiels: () => void;
}) {
    const detailCommande = ref<CommandeDetail | null>(null);
    const complementairesForm = ref({ commentaire: '' });
    const savingComplementaires = ref(false);
    const showDetailModal = ref(false);
    const showAnnulerModal = ref(false);
    const showValiderModal = ref(false);
    const loadingDetail = ref(false);
    const motifAnnulation = ref('');
    const noteAnnulation = ref('');

    const isAgent = computed(() => options.canCreateCommande.value);

    const enAttentePharmacieToutIndisponible = computed(() => {
        const c = detailCommande.value;
        return (
            !!c &&
            c.status === 'en_attente' &&
            c.status_pharmacie === 'indisponible'
        );
    });

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

    const peutModifierCommandeComplete = computed(() => {
        const c = detailCommande.value;
        return (
            options.canManageCommandes.value &&
            c &&
            (c.status === 'nouvelle' || c.status === 'en_attente')
        );
    });

    const peutEditerComplementaires = computed(() => {
        const c = detailCommande.value;
        return (
            options.canManageCommandes.value && c && c.status !== 'annulee'
        );
    });

    const detailSplit = computed(() =>
        splitProduitsCommande(
            detailCommande.value?.produits,
            options.parapharmaProduitTypes.value,
        ),
    );

    const sousTotal = () => detailSplit.value.sousTotal;
    const livraison = () =>
        Number(detailCommande.value?.montant_livraison?.designation ?? 0);
    const totalDetail = () => sousTotal() + livraison();

    watch(detailCommande, (c) => {
        complementairesForm.value = {
            commentaire: c?.commentaire ?? '',
        };
    });

    function motifAutoriseRelance(motif: string | undefined): boolean {
        if (!motif) return false;
        return !!options.motifsRelance.value[motif];
    }

    function getMotifAnnulationLabel(key: string | undefined): string {
        return (
            (key && options.motifLabelBySlug.value[key]) || key || 'Non précisé'
        );
    }

    let detailFetchGeneration = 0;

    async function openDetail(id: number) {
        options.ensureReferentiels();
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

    function closeDetail() {
        clearVerificationPoll();
        showDetailModal.value = false;
        detailCommande.value = null;
    }

    function saveComplementaires() {
        if (!detailCommande.value || !peutEditerComplementaires.value) return;
        savingComplementaires.value = true;
        router.patch(
            `/commandes/${detailCommande.value.id}/complementaires`,
            {
                commentaire: complementairesForm.value.commentaire,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    void refreshDetailSilently(detailCommande.value!.id);
                },
                onFinish: () => {
                    savingComplementaires.value = false;
                },
            },
        );
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
                    void openDetail(id);
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

    function csrfToken(): string {
        return (
            document
                .querySelector('meta[name="csrf-token"]')
                ?.getAttribute('content') ?? ''
        );
    }

    async function setMontantLivraison(montantId: number) {
        if (!detailCommande.value) return;
        const id = detailCommande.value.id;
        const montant = options.montantsLivraison.value.find(
            (m) => m.id === montantId,
        );

        if (montant) {
            detailCommande.value = {
                ...detailCommande.value,
                montant_livraison_id: montantId,
                montant_livraison: montant,
            };
        }

        try {
            const r = await fetch(`/commandes/${id}/montant-livraison`, {
                method: 'PATCH',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ montant_livraison_id: montantId }),
            });
            if (!r.ok) {
                await refreshDetailSilently(id);
                return;
            }
            const json = (await r.json()) as {
                commande?: typeof detailCommande.value;
            };
            if (json.commande && detailCommande.value?.id === id) {
                detailCommande.value = json.commande;
            }
        } catch {
            await refreshDetailSilently(id);
        }
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

    function confirmAnnulerEtRelancer(onRelancer: () => void) {
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
                        onRelancer();
                        router.reload({
                            only: ['commandes', 'stats'],
                            preserveState: true,
                        });
                    })();
                },
            },
        );
    }

    return {
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
        livraison,
        totalDetail,
        motifAutoriseRelance,
        getMotifAnnulationLabel,
        openDetail,
        refreshDetailSilently,
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
    };
}
