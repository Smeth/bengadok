export type OrderAlertPortal = 'pharma' | 'backoffice';

const SOUND_ENABLED_KEY = 'bengadok_order_alert_sound_enabled';
const PHARMA_BANNER_DISMISSED_KEY = 'bengadok_pharma_alert_banner_dismissed';
const BACKOFFICE_BANNER_DISMISSED_KEY = 'bengadok_backoffice_alert_banner_dismissed';

export const ORDER_ALERT_PREFS_CHANGED_EVENT = 'bengadok-order-alert-prefs-changed';

function bannerKey(portal: OrderAlertPortal): string {
    return portal === 'pharma'
        ? PHARMA_BANNER_DISMISSED_KEY
        : BACKOFFICE_BANNER_DISMISSED_KEY;
}

export function notifyOrderAlertPreferencesChanged(): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.dispatchEvent(new Event(ORDER_ALERT_PREFS_CHANGED_EVENT));
}

export function isOrderAlertSoundEnabled(): boolean {
    if (typeof localStorage === 'undefined') {
        return true;
    }

    return localStorage.getItem(SOUND_ENABLED_KEY) !== '0';
}

export function setOrderAlertSoundEnabled(enabled: boolean): void {
    localStorage.setItem(SOUND_ENABLED_KEY, enabled ? '1' : '0');
    notifyOrderAlertPreferencesChanged();
}

export function isOrderAlertBannerDismissed(portal: OrderAlertPortal): boolean {
    if (typeof localStorage === 'undefined') {
        return false;
    }

    return localStorage.getItem(bannerKey(portal)) === '1';
}

export function dismissOrderAlertBanner(portal: OrderAlertPortal): void {
    localStorage.setItem(bannerKey(portal), '1');
    notifyOrderAlertPreferencesChanged();
}

export function resetOrderAlertBannerDismissed(
    portal: OrderAlertPortal | 'all',
): void {
    if (portal === 'all' || portal === 'pharma') {
        localStorage.removeItem(PHARMA_BANNER_DISMISSED_KEY);
    }

    if (portal === 'all' || portal === 'backoffice') {
        localStorage.removeItem(BACKOFFICE_BANNER_DISMISSED_KEY);
    }

    notifyOrderAlertPreferencesChanged();
}

export function shouldShowOrderAlertBanner(
    portal: OrderAlertPortal,
    permission: NotificationPermission | 'unsupported',
): boolean {
    return (
        permission !== 'unsupported' &&
        permission !== 'granted' &&
        !isOrderAlertBannerDismissed(portal)
    );
}
