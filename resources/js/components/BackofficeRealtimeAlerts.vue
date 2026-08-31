<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch } from 'vue';
import AppToast from '@/components/AppToast.vue';
import type { BackofficeOrderAlert } from '@/lib/backofficeOrderAlertDetector';
import { registerBackofficeOrderAlertHandler } from '@/lib/backofficeOrderAlertsBus';
import {
    browserNotificationPermission,
    requestOrderBrowserNotificationPermission,
    showOrderBrowserNotification,
} from '@/lib/orderBrowserNotification';
import { playOrderAlertSound, unlockOrderAlertSound } from '@/lib/orderAlertSound';
import {
    dismissOrderAlertBanner,
    ORDER_ALERT_PREFS_CHANGED_EVENT,
    shouldShowOrderAlertBanner,
} from '@/lib/orderAlertPreferences';

type ToastState = {
    show: boolean;
    title: string;
    description: string;
    variant: 'urgent' | 'info';
    durationMs: number;
};

const toast = ref<ToastState>({
    show: false,
    title: '',
    description: '',
    variant: 'urgent',
    durationMs: 9000,
});

const queue = ref<BackofficeOrderAlert[]>([]);
const showPermissionBanner = ref(false);

function dismissPermissionBanner(): void {
    showPermissionBanner.value = false;
    dismissOrderAlertBanner('backoffice');
}

async function enableAggressiveAlerts(): Promise<void> {
    unlockOrderAlertSound();
    await requestOrderBrowserNotificationPermission();
    dismissPermissionBanner();
}

function syncPermissionBanner(): void {
    showPermissionBanner.value = shouldShowOrderAlertBanner(
        'backoffice',
        browserNotificationPermission(),
    );
}

function pushAlerts(alerts: BackofficeOrderAlert[]): void {
    queue.value.push(...alerts);
    drainQueue();
}

function drainQueue(): void {
    if (toast.value.show || queue.value.length === 0) {
        return;
    }

    const next = queue.value.shift();
    if (!next) {
        return;
    }

    playOrderAlertSound(next.soundProfile);

    showOrderBrowserNotification({
        title: next.title,
        body: next.description,
        url: next.url,
        tag: `backoffice-${next.kind}-${next.count}`,
    });

    toast.value = {
        show: true,
        title: next.title,
        description: next.description,
        variant: next.toastVariant,
        durationMs: next.kind === 'en_attente' ? 10000 : 8500,
    };
}

watch(
    () => toast.value.show,
    (visible) => {
        if (!visible) {
            window.setTimeout(drainQueue, 250);
        }
    },
);

function onDocumentClick(): void {
    unlockOrderAlertSound();
}

let unregisterAlertHandler: (() => void) | null = null;

onMounted(() => {
    syncPermissionBanner();

    document.addEventListener('click', onDocumentClick, { passive: true });
    window.addEventListener(
        ORDER_ALERT_PREFS_CHANGED_EVENT,
        syncPermissionBanner,
    );
    unregisterAlertHandler = registerBackofficeOrderAlertHandler(pushAlerts);
});

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
    window.removeEventListener(
        ORDER_ALERT_PREFS_CHANGED_EVENT,
        syncPermissionBanner,
    );
    unregisterAlertHandler?.();
    unregisterAlertHandler = null;
});
</script>

<template>
    <AppToast
        v-model:show="toast.show"
        :title="toast.title"
        :description="toast.description"
        :variant="toast.variant"
        :duration-ms="toast.durationMs"
    />

    <div
        v-if="showPermissionBanner"
        class="fixed inset-x-4 top-4 z-[260] mx-auto flex max-w-xl items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 p-4 shadow-lg"
        role="alert"
    >
        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-amber-950">
                Activer les alertes commandes
            </p>
            <p class="mt-1 text-sm text-amber-900">
                Autorisez le son et les notifications navigateur pour ne
                manquer aucun retour pharmacie ni nouvelle commande.
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
                <button
                    type="button"
                    class="rounded-lg bg-amber-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-amber-700"
                    @click="enableAggressiveAlerts"
                >
                    Activer les alertes
                </button>
                <button
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium text-amber-900 hover:bg-amber-100"
                    @click="dismissPermissionBanner"
                >
                    Plus tard
                </button>
            </div>
        </div>
    </div>
</template>
