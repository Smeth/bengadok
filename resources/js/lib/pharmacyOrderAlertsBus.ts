import type { PharmacyOrderAlert } from '@/lib/pharmacyOrderAlertDetector';

type AlertHandler = (alerts: PharmacyOrderAlert[]) => void;

let handler: AlertHandler | null = null;

export function registerPharmacyOrderAlertHandler(next: AlertHandler): () => void {
    handler = next;

    return () => {
        if (handler === next) {
            handler = null;
        }
    };
}

export function dispatchPharmacyOrderAlerts(alerts: PharmacyOrderAlert[]): void {
    if (alerts.length === 0) {
        return;
    }

    handler?.(alerts);
}
