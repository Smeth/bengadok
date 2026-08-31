import type { BackofficeOrderAlert } from '@/lib/backofficeOrderAlertDetector';

type AlertHandler = (alerts: BackofficeOrderAlert[]) => void;

let handler: AlertHandler | null = null;

export function registerBackofficeOrderAlertHandler(
    next: AlertHandler,
): () => void {
    handler = next;

    return () => {
        if (handler === next) {
            handler = null;
        }
    };
}

export function dispatchBackofficeOrderAlerts(
    alerts: BackofficeOrderAlert[],
): void {
    if (alerts.length === 0) {
        return;
    }

    handler?.(alerts);
}
