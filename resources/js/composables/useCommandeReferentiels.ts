import { type ComputedRef, type Ref, computed, ref, unref } from 'vue';
import type { CommandeReferentielPharmacie } from '@/lib/commandeEnregistrementTypes';

export type CommandePharmacyScope = 'partenaires' | 'toutes';

export function useCommandeReferentiels(
    canManageCommandes: ComputedRef<boolean> | Ref<boolean>,
    pharmacyScope: ComputedRef<CommandePharmacyScope> | Ref<CommandePharmacyScope> = ref('partenaires'),
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
    let referentielsLoadedScope: CommandePharmacyScope | null = null;

    const scopeKey = computed(() => unref(pharmacyScope));

    async function loadReferentiels(): Promise<void> {
        if (!canManageCommandes.value) {
            return;
        }
        const scope = scopeKey.value;
        if (
            (referentielsLoadedScope === scope && pharmacies.value.length > 0) ||
            referentielsLoading.value
        ) {
            return;
        }
        referentielsLoading.value = true;
        try {
            const params =
                scope === 'toutes' ? '?pharmacies=toutes' : '';
            const r = await fetch(`/commandes/referentiels${params}`, {
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
            referentielsLoadedScope = scope;
        } finally {
            referentielsLoading.value = false;
        }
    }

    function ensureReferentiels(): void {
        void loadReferentiels();
    }

    function addPharmacie(pharmacie: CommandeReferentielPharmacie): void {
        if (pharmacies.value.some((p) => p.id === pharmacie.id)) {
            return;
        }

        pharmacies.value = [...pharmacies.value, pharmacie];

        const zoneId = pharmacie.zone_id ?? pharmacie.zone?.id;
        if (zoneId) {
            zones.value = zones.value.map((zone) =>
                zone.id === zoneId
                    ? {
                          ...zone,
                          pharmacies_count: (zone.pharmacies_count ?? 0) + 1,
                      }
                    : zone,
            );
        }
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
        addPharmacie,
    };
}
