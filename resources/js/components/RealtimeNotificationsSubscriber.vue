<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { onMounted, onUnmounted } from 'vue';
import { useBackofficePortal } from '@/composables/useBackofficePortal';
import { usePharmacyPortal } from '@/composables/usePharmacyPortal';
import {
    detectBackofficeOrderAlerts,
    takeBackofficeAlertSnapshot,
} from '@/lib/backofficeOrderAlertDetector';
import { dispatchBackofficeOrderAlerts } from '@/lib/backofficeOrderAlertsBus';
import {
    detectPharmacyOrderAlerts,
    takePharmacyAlertSnapshot,
} from '@/lib/pharmacyOrderAlertDetector';
import { dispatchPharmacyOrderAlerts } from '@/lib/pharmacyOrderAlertsBus';

const props = defineProps<{
    userId: number;
}>();

const emit = defineEmits<{
    hors_ligne: [value: boolean];
}>();

const page = usePage();
const { isPharmacyPortalUser } = usePharmacyPortal();
const { isBackofficePortalUser } = useBackofficePortal();

function handleReloadSuccess(
    beforePharmacySnapshot: ReturnType<typeof takePharmacyAlertSnapshot> | null,
    beforeBackofficeSnapshot: ReturnType<
        typeof takeBackofficeAlertSnapshot
    > | null,
): void {
    if (beforePharmacySnapshot && isPharmacyPortalUser.value) {
        const pharmacyAlerts = detectPharmacyOrderAlerts(
            beforePharmacySnapshot,
            takePharmacyAlertSnapshot(page),
        );

        if (pharmacyAlerts.length > 0) {
            dispatchPharmacyOrderAlerts(pharmacyAlerts);
        }
    }

    if (beforeBackofficeSnapshot && isBackofficePortalUser.value) {
        const backofficeAlerts = detectBackofficeOrderAlerts(
            beforeBackofficeSnapshot,
            takeBackofficeAlertSnapshot(page),
        );

        if (backofficeAlerts.length > 0) {
            dispatchBackofficeOrderAlerts(backofficeAlerts);
        }
    }
}

/**
 * Même événement WebSocket que la cloche : recharge la liste des commandes
 * si l’utilisateur est sur l’index (pharmacie ou backoffice).
 */
function reloadPropsAfterCommandeBroadcast() {
    const beforePharmacySnapshot = isPharmacyPortalUser.value
        ? takePharmacyAlertSnapshot(page)
        : null;
    const beforeBackofficeSnapshot = isBackofficePortalUser.value
        ? takeBackofficeAlertSnapshot(page)
        : null;

    const onSuccess = () => {
        handleReloadSuccess(beforePharmacySnapshot, beforeBackofficeSnapshot);
    };

    const url = page.url;

    if (isPharmacyPortalUser.value) {
        const onCommandesPage =
            url === '/dok-pharma/commandes' ||
            url.startsWith('/dok-pharma/commandes?');

        router.reload({
            only: onCommandesPage
                ? [
                      'notifications',
                      'commandes',
                      'stats',
                      'onglet',
                      'pharmacyStats',
                  ]
                : ['notifications', 'pharmacyStats'],
            preserveScroll: true,
            onSuccess,
        });
        return;
    }

    if (isBackofficePortalUser.value) {
        const onCommandesPage =
            url === '/commandes' || url.startsWith('/commandes?');

        router.reload({
            only: onCommandesPage
                ? ['notifications', 'commandes', 'stats', 'backofficeStats']
                : ['notifications', 'backofficeStats'],
            preserveScroll: true,
            onSuccess,
        });
        return;
    }

    if (url === '/commandes' || url.startsWith('/commandes?')) {
        router.reload({
            only: ['notifications', 'commandes', 'stats'],
            preserveScroll: true,
        });
        return;
    }

    router.reload({ only: ['notifications'], preserveScroll: true });
}

const reverbEnabled = Boolean(import.meta.env.VITE_REVERB_APP_KEY);

if (reverbEnabled) {
    useEcho(
        `App.Models.User.${props.userId}`,
        '.notifications.refresh',
        reloadPropsAfterCommandeBroadcast,
    );
}

function onEtatConnexion({ current }: { current: string }) {
    emit('hors_ligne', current === 'unavailable' || current === 'failed');
}

onMounted(() => {
    const pusher = (window as any).Echo?.connector?.pusher;
    pusher?.connection.bind('state_change', onEtatConnexion);
});

onUnmounted(() => {
    const pusher = (window as any).Echo?.connector?.pusher;
    pusher?.connection.unbind('state_change', onEtatConnexion);
});
</script>

<template>
    <span class="hidden" aria-hidden="true" />
</template>
