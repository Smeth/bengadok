import type { CommandeReferentielPharmacie } from '@/lib/commandeEnregistrementTypes';
import { parseApiValidationErrors } from '@/lib/validationErrors';

export type QuickCreatePharmaciePayload = {
    designation: string;
    arrondissement?: string;
    zone_id?: number;
    telephone?: string;
    adresse?: string;
};

export type QuickCreatePharmacieResult = {
    pharmacie: CommandeReferentielPharmacie;
    created: boolean;
    message: string;
};

function csrfToken(): string {
    return (
        document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

export async function quickCreatePharmacie(
    payload: QuickCreatePharmaciePayload,
): Promise<QuickCreatePharmacieResult> {
    const response = await fetch('/commandes/pharmacies/quick-create', {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
    });

    if (response.ok) {
        return (await response.json()) as QuickCreatePharmacieResult;
    }

    if (response.status === 422) {
        const errors = parseApiValidationErrors(await response.json());
        const first = Object.values(errors)[0];
        throw new Error(first ?? 'Données invalides.');
    }

    throw new Error('Impossible de créer la pharmacie pour le moment.');
}
