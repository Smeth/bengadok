<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch } from 'vue';
import AppToast from '@/components/AppToast.vue';
import {
    browserNotificationPermission,
    requestPharmacyBrowserNotificationPermission,
    showPharmacyBrowserNotification,
} from '@/lib/pharmacyBrowserNotification';
import { playPharmacyAlertSound, unlockPharmacyAlertSound } from '@/lib/pharmacyAlertSound';
import type { PharmacyOrderAlert } from '@/lib/pharmacyOrderAlertDetector';
import { registerPharmacyOrderAlertHandler } from '@/lib/pharmacyOrderAlertsBus';

const PERMISSION_BANNER_KEY = 'bengadok_pharma_alert_banner_dismissed';

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

const queue = ref<PharmacyOrderAlert[]>([]);
const showPermissionBanner = ref(false);

function dismissPermissionBanner(): void {
    showPermissionBanner.value = false;
    localStorage.setItem(PERMISSION_BANNER_KEY, '1');
}

async function enableAggressiveAlerts(): Promise<void> {
    unlockPharmacyAlertSound();
    await requestPharmacyBrowserNotificationPermission();
    dismissPermissionBanner();
}

function pushAlerts(alerts: PharmacyOrderAlert[]): void {
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

    playPharmacyAlertSound(next.kind);

    showPharmacyBrowserNotification({
        title: next.title,
        body: next.description,
        url: next.url,
        tag: `pharmacy-${next.kind}-${next.count}`,
    });

    toast.value = {
        show: true,
        title: next.title,
        description: next.description,
        variant: next.kind === 'nouvelle' ? 'urgent' : 'info',
        durationMs: next.kind === 'nouvelle' ? 10000 : 8500,
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
    unlockPharmacyAlertSound();
}

let unregisterAlertHandler: (() => void) | null = null;

onMounted(() => {
    const permission = browserNotificationPermission();
    const bannerDismissed =
        localStorage.getItem(PERMISSION_BANNER_KEY) === '1';

    showPermissionBanner.value =
        permission !== 'unsupported' &&
        permission !== 'granted' &&
        !bannerDismissed;

    document.addEventListener('click', onDocumentClick, { passive: true });
    unregisterAlertHandler = registerPharmacyOrderAlertHandler(pushAlerts);
});

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
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
                manquer aucune nouvelle commande ni validation back-office.
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
