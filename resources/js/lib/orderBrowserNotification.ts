const DEFAULT_ICON = '/images/figma-assets/icon-orders.svg';

export function canUseBrowserNotifications(): boolean {
    return typeof window !== 'undefined' && 'Notification' in window;
}

export function browserNotificationPermission(): NotificationPermission | 'unsupported' {
    if (!canUseBrowserNotifications()) {
        return 'unsupported';
    }

    return Notification.permission;
}

export async function requestOrderBrowserNotificationPermission(): Promise<
    NotificationPermission | 'unsupported'
> {
    if (!canUseBrowserNotifications()) {
        return 'unsupported';
    }

    if (Notification.permission === 'granted') {
        return 'granted';
    }

    if (Notification.permission === 'denied') {
        return 'denied';
    }

    return Notification.requestPermission();
}

export function showOrderBrowserNotification(options: {
    title: string;
    body: string;
    url: string;
    tag?: string;
    icon?: string;
}): void {
    if (!canUseBrowserNotifications() || Notification.permission !== 'granted') {
        return;
    }

    const notification = new Notification(options.title, {
        body: options.body,
        icon: options.icon ?? DEFAULT_ICON,
        tag: options.tag ?? options.title,
        requireInteraction: true,
    });

    notification.onclick = () => {
        window.focus();
        window.location.assign(options.url);
        notification.close();
    };
}
