<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { onMounted, onUnmounted } from 'vue';
import { usePharmacyPortal } from '@/composables/usePharmacyPortal';
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

function handleReloadSuccess(beforeSnapshot: ReturnType<
    typeof takePharmacyAlertSnapshot
> | null): void {
    if (!beforeSnapshot || !isPharmacyPortalUser.value) {
        return;
    }

    const alerts = detectPharmacyOrderAlerts(
        beforeSnapshot,
        takePharmacyAlertSnapshot(page),
    );

    if (alerts.length > 0) {
        dispatchPharmacyOrderAlerts(alerts);
    }
}

/**
 * Même événement WebSocket que la cloche : recharge la liste des commandes
 * si l’utilisateur est sur l’index (pharmacie ou backoffice).
 */
function reloadPropsAfterCommandeBroadcast() {
    const beforeSnapshot = isPharmacyPortalUser.value
        ? takePharmacyAlertSnapshot(page)
        : null;

    const onSuccess = () => {
        handleReloadSuccess(beforeSnapshot);
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

    if (url === '/commandes' || url.startsWith('/commandes?')) {
        router.reload({
            only: ['notifications', 'commandes', 'stats'],
            preserveScroll: true,
        });
        return;
    }

    router.reload({ only: ['notifications'], preserveScroll: true });
}

useEcho(
    `App.Models.User.${props.userId}`,
    '.notifications.refresh',
    reloadPropsAfterCommandeBroadcast,
);

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
