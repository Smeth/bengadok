import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted } from 'vue';

const POLL_INTERVAL_MS = 30_000; // 30 secondes

/**
 * Rafraîchit la page Inertia actuelle toutes les 30 secondes,
 * uniquement quand l'onglet est visible et qu'aucune visite n'est déjà en cours.
 */
export function usePolling(intervalMs = POLL_INTERVAL_MS): void {
    let timerId: ReturnType<typeof setInterval> | null = null;
    let visitsInFlight = 0;
    let offStart: (() => void) | null = null;
    let offFinish: (() => void) | null = null;

    function tick() {
        if (document.visibilityState !== 'visible') return;
        if (visitsInFlight > 0) return;
        router.reload({ preserveScroll: true });
    }

    onMounted(() => {
        offStart = router.on('start', () => {
            visitsInFlight++;
        });
        offFinish = router.on('finish', () => {
            visitsInFlight = Math.max(0, visitsInFlight - 1);
        });
        timerId = setInterval(tick, intervalMs);
    });

    onBeforeUnmount(() => {
        offStart?.();
        offFinish?.();
        if (timerId) {
            clearInterval(timerId);
        }
    });
}
