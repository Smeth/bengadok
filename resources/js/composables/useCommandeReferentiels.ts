import { type ComputedRef, type Ref, ref } from 'vue';
import type { CommandeReferentielPharmacie } from '@/lib/commandeEnregistrementTypes';

export function useCommandeReferentiels(
    canManageCommandes: ComputedRef<boolean> | Ref<boolean>,
) {
    const pharmacies = ref<CommandeReferentielPharmacie[]>([]);
    const zones = ref<
        Array<{ id: number; designation: string; pharmacies_count: number }>
    >([]);
    const montantsLivraison = ref<Array<{ id: number; designation: number }>>(
        [],
    );
    const modesPaiement = ref<Array<{ id: number; designation: string }>>([]);
    const livreurs = ref<
        Array<{ id: number; nom: string; prenom: string; tel: string }>
    >([]);
    const arrondissements = ref<string[]>([]);
    const parapharmaProduitTypes = ref<string[]>(['Parapharmacie']);
    const referentielsLoading = ref(false);
    let referentielsLoaded = false;

    async function loadReferentiels(): Promise<void> {
        if (!canManageCommandes.value) {
            return;
        }
        if (referentielsLoaded || referentielsLoading.value) {
            return;
        }
        referentielsLoading.value = true;
        try {
            const r = await fetch('/commandes/referentiels', {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            if (!r.ok) {
                return;
            }
            const json = await r.json();
            pharmacies.value = json.pharmacies ?? [];
            zones.value = json.zones ?? [];
            montantsLivraison.value = json.montantsLivraison ?? [];
            modesPaiement.value = json.modesPaiement ?? [];
            livreurs.value = json.livreurs ?? [];
            arrondissements.value = json.arrondissements ?? [];
            parapharmaProduitTypes.value =
                json.parapharma_produit_types ?? ['Parapharmacie'];
            referentielsLoaded = true;
        } finally {
            referentielsLoading.value = false;
        }
    }

    function ensureReferentiels(): void {
        void loadReferentiels();
    }

    return {
        pharmacies,
        zones,
        montantsLivraison,
        modesPaiement,
        livreurs,
        arrondissements,
        parapharmaProduitTypes,
        referentielsLoading,
        ensureReferentiels,
    };
}
