import type { PharmacyAlertKind } from '@/lib/pharmacyOrderAlertDetector';
import {
    playOrderAlertSound,
    unlockOrderAlertSound,
} from '@/lib/orderAlertSound';

export { unlockOrderAlertSound as unlockPharmacyAlertSound };

export function playPharmacyAlertSound(kind: PharmacyAlertKind): void {
    playOrderAlertSound(kind === 'nouvelle' ? 'urgent' : 'info');
}
