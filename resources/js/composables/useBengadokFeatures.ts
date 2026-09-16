import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

type BengadokFeatures = {
    pharmacy_vendeur_self_service?: boolean;
};

export function useBengadokFeatures() {
    const page = usePage();

    const features = computed(
        () => (page.props.features ?? {}) as BengadokFeatures,
    );

    const pharmacyVendeurSelfService = computed(
        () => features.value.pharmacy_vendeur_self_service === true,
    );

    return { pharmacyVendeurSelfService };
}
