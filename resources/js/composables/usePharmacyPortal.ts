import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/** Utilisateur connecté sur l’espace Dok-Pharma (gérant / vendeur) */
export function usePharmacyPortal() {
    const page = usePage();
    const isPharmacyPortalUser = computed(() => {
        const roles =
            (page.props.auth as { user?: { roles?: string[] } })?.user?.roles ??
            [];
        return roles.includes('gerant') || roles.includes('vendeur');
    });
    return { isPharmacyPortalUser };
}
