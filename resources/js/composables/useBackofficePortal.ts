import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/** Utilisateur connecté sur le back-office (admin / agent). */
export function useBackofficePortal() {
    const page = usePage();
    const isBackofficePortalUser = computed(() => {
        const roles =
            (page.props.auth as { user?: { roles?: string[] } })?.user?.roles ??
            [];

        return (
            roles.includes('admin') ||
            roles.includes('super_admin') ||
            roles.includes('agent_call_center')
        );
    });

    return { isBackofficePortalUser };
}
