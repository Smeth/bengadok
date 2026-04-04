<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import { onMounted, onUnmounted } from 'vue';

const props = defineProps<{
    userId: number;
}>();

const emit = defineEmits<{
    hors_ligne: [value: boolean];
}>();

const page = usePage();

/**
 * Même événement WebSocket que la cloche : recharge la liste des commandes
 * si l’utilisateur est sur l’index (pharmacie ou backoffice).
 */
function reloadPropsAfterCommandeBroadcast() {
    const url = page.url;

    if (
        url === '/dok-pharma/commandes' ||
        url.startsWith('/dok-pharma/commandes?')
    ) {
        router.reload({
            only: ['notifications', 'commandes', 'stats', 'onglet'],
            preserveScroll: true,
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
