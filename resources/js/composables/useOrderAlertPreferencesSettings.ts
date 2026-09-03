import { onMounted, onUnmounted, ref } from 'vue';
import { useBackofficePortal } from '@/composables/useBackofficePortal';
import { usePharmacyPortal } from '@/composables/usePharmacyPortal';
import {
    browserNotificationPermission,
    requestOrderBrowserNotificationPermission,
} from '@/lib/orderBrowserNotification';
import type { OrderAlertPortal, OrderAlertSoundPreset } from '@/lib/orderAlertPreferences';
import {
    getOrderAlertSoundPreset,
    isOrderAlertSoundEnabled,
    ORDER_ALERT_PREFS_CHANGED_EVENT,
    ORDER_ALERT_SOUND_PRESET_OPTIONS,
    resetOrderAlertBannerDismissed,
    setOrderAlertSoundEnabled,
    setOrderAlertSoundPreset,
} from '@/lib/orderAlertPreferences';
import {
    playOrderAlertSound,
    previewOrderAlertSoundPreset,
    unlockOrderAlertSound,
} from '@/lib/orderAlertSound';

export function useOrderAlertPreferencesSettings() {
    const { isPharmacyPortalUser } = usePharmacyPortal();
    const { isBackofficePortalUser } = useBackofficePortal();

    const soundEnabled = ref(isOrderAlertSoundEnabled());
    const soundPreset = ref<OrderAlertSoundPreset>(getOrderAlertSoundPreset());
    const browserPermission = ref(browserNotificationPermission());
    const statusMessage = ref('');
    const statusVariant = ref<'success' | 'error' | 'info'>('info');

    function activePortal(): OrderAlertPortal | null {
        if (isPharmacyPortalUser.value) {
            return 'pharma';
        }

        if (isBackofficePortalUser.value) {
            return 'backoffice';
        }

        return null;
    }

    function refreshBrowserPermission(): void {
        browserPermission.value = browserNotificationPermission();
    }

    function onPrefsChanged(): void {
        soundEnabled.value = isOrderAlertSoundEnabled();
        soundPreset.value = getOrderAlertSoundPreset();
        refreshBrowserPermission();
    }

    function setSoundPreset(preset: OrderAlertSoundPreset): void {
        soundPreset.value = preset;
        setOrderAlertSoundPreset(preset);
        statusMessage.value = 'Type de signal mis à jour.';
        statusVariant.value = 'success';

        if (soundEnabled.value) {
            unlockOrderAlertSound();
            previewOrderAlertSoundPreset(preset);
        }
    }

    function setSoundEnabled(enabled: boolean): void {
        soundEnabled.value = enabled;
        setOrderAlertSoundEnabled(enabled);
        statusMessage.value = enabled
            ? 'Son des alertes activé.'
            : 'Son des alertes désactivé.';
        statusVariant.value = 'info';
    }

    async function requestBrowserNotifications(): Promise<void> {
        unlockOrderAlertSound();
        const result = await requestOrderBrowserNotificationPermission();
        refreshBrowserPermission();

        if (result === 'granted') {
            statusMessage.value = 'Notifications navigateur autorisées.';
            statusVariant.value = 'success';
            return;
        }

        if (result === 'denied') {
            statusMessage.value =
                'Notifications refusées. Autorisez BengaDok dans les paramètres du site (icône cadenas → Notifications).';
            statusVariant.value = 'error';
            return;
        }

        statusMessage.value =
            'Autorisation non accordée. Réessayez ou vérifiez les paramètres du navigateur.';
        statusVariant.value = 'error';
    }

    function showReminderBannerAgain(): void {
        const portal = activePortal();
        if (!portal) {
            return;
        }

        resetOrderAlertBannerDismissed(portal);
        statusMessage.value =
            'Le rappel d’activation a été réactivé (visible si les notifications ne sont pas encore autorisées).';
        statusVariant.value = 'success';
    }

    function testAlertSound(): void {
        unlockOrderAlertSound();
        playOrderAlertSound('urgent');
        statusMessage.value = soundEnabled.value
            ? 'Signal sonore de test joué.'
            : 'Activez d’abord le son des alertes.';
        statusVariant.value = soundEnabled.value ? 'success' : 'error';
    }

    function permissionLabel(): string {
        switch (browserPermission.value) {
            case 'granted':
                return 'Autorisées';
            case 'denied':
                return 'Refusées';
            case 'default':
                return 'Non configurées';
            default:
                return 'Non supportées';
        }
    }

    onMounted(() => {
        window.addEventListener(
            ORDER_ALERT_PREFS_CHANGED_EVENT,
            onPrefsChanged,
        );
    });

    onUnmounted(() => {
        window.removeEventListener(
            ORDER_ALERT_PREFS_CHANGED_EVENT,
            onPrefsChanged,
        );
    });

    return {
        isPharmacyPortalUser,
        isBackofficePortalUser,
        soundEnabled,
        soundPreset,
        soundPresetOptions: ORDER_ALERT_SOUND_PRESET_OPTIONS,
        browserPermission,
        statusMessage,
        statusVariant,
        permissionLabel,
        setSoundEnabled,
        setSoundPreset,
        requestBrowserNotifications,
        showReminderBannerAgain,
        testAlertSound,
    };
}
