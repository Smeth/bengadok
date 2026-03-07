import { onBeforeUnmount, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';

const POLL_INTERVAL_MS = 30_000; // 30 secondes

/**
 * Rafraîchit la page Inertia actuelle toutes les 30 secondes,
 * uniquement quand l'onglet est visible.
 */
export function usePolling(intervalMs = POLL_INTERVAL_MS): void {
    let timerId: ReturnType<typeof setInterval> | null = null;

    function tick() {
        if (document.visibilityState === 'visible') {
            router.reload({ preserveScroll: true });
        }
    }

    onMounted(() => {
        timerId = setInterval(tick, intervalMs);
    });

    onBeforeUnmount(() => {
        if (timerId) {
            clearInterval(timerId);
        }
    });
}
