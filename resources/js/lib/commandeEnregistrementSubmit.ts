import { router } from '@inertiajs/vue3';
import { ordonnanceFilesFromValue } from '@/lib/commandeCreationFields';
import type { FormEnregPayload } from '@/lib/commandeEnregistrementTypes';
import { validateFileSize } from '@/lib/uploadLimits';
import { parseApiValidationErrors } from '@/lib/validationErrors';

export { parseApiValidationErrors };

function appendHistoricalFields(
    target: FormData | Record<string, unknown>,
    payload: FormEnregPayload,
): void {
    if (payload.date) {
        if (target instanceof FormData) {
            target.append('date', payload.date);
        } else {
            target.date = payload.date;
        }
    }
    if (payload.heurs) {
        if (target instanceof FormData) {
            target.append('heurs', payload.heurs);
        } else {
            target.heurs = payload.heurs;
        }
    }
    if (payload.initial_status) {
        if (target instanceof FormData) {
            target.append('initial_status', payload.initial_status);
        } else {
            target.initial_status = payload.initial_status;
        }
    }
    if (payload._return_hub) {
        if (target instanceof FormData) {
            target.append('_return_hub', payload._return_hub);
        } else {
            target._return_hub = payload._return_hub;
        }
    }
}

export function appendEnregistrementFields(
    formData: FormData,
    payload: FormEnregPayload,
): void {
    if (payload.client_id) {
        formData.append('client_id', String(payload.client_id));
    } else {
        formData.append('client_nom', payload.client_nom);
        formData.append('client_prenom', payload.client_prenom);
        formData.append('client_tel', payload.client_tel);
        formData.append('client_adresse', payload.client_adresse);
    }
    formData.append('client_arrondissement', payload.client_arrondissement);
    if (payload.client_sexe) {
        formData.append('client_sexe', payload.client_sexe);
    }
    formData.append('pharmacie_id', payload.pharmacie_id);
    if (payload.beneficiaire) {
        formData.append('beneficiaire', payload.beneficiaire);
    }
    formData.append('produits', JSON.stringify(payload.produits));
    if (payload.commentaire) {
        formData.append('commentaire', payload.commentaire);
    }
    if (payload.montant_livraison_id) {
        formData.append('montant_livraison_id', payload.montant_livraison_id);
    }
    appendHistoricalFields(formData, payload);
}

type SubmitCallbacks = {
    onSuccess: () => void;
    onError: (errors: Record<string, string>) => void;
};

function rejectOversizedOrdonnance(
    files: File[],
    callbacks: SubmitCallbacks,
): boolean {
    for (const file of files) {
        const sizeError = validateFileSize(file);
        if (sizeError) {
            callbacks.onError({ ordonnance: sizeError });
            return true;
        }
    }

    return false;
}

function appendOrdonnanceFiles(formData: FormData, files: File[]): void {
    if (files.length === 0) {
        return;
    }

    formData.append('ordonnance', files[0]);
    files.slice(1).forEach((file) => {
        formData.append('ordonnances[]', file);
    });
}

export function submitCommandeEnregistrement(
    payload: FormEnregPayload,
    callbacks: SubmitCallbacks,
): void {
    const ordonnanceFiles = ordonnanceFilesFromValue(payload.ordonnance);
    if (ordonnanceFiles.length > 0) {
        if (rejectOversizedOrdonnance(ordonnanceFiles, callbacks)) {
            return;
        }

        const formData = new FormData();
        appendEnregistrementFields(formData, payload);
        appendOrdonnanceFiles(formData, ordonnanceFiles);

        router.post('/commandes', formData, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: callbacks.onSuccess,
            onError: (e) => callbacks.onError(parseApiValidationErrors(e)),
        });
        return;
    }

    const data: Record<string, unknown> = {
        client_nom: payload.client_nom,
        client_prenom: payload.client_prenom,
        client_tel: payload.client_tel,
        client_adresse: payload.client_adresse,
        client_arrondissement: payload.client_arrondissement,
        client_sexe: payload.client_sexe || undefined,
        pharmacie_id: payload.pharmacie_id,
        beneficiaire: payload.beneficiaire || undefined,
        produits: payload.produits,
        commentaire: payload.commentaire || undefined,
        date: payload.date || undefined,
        heurs: payload.heurs || undefined,
        montant_livraison_id: payload.montant_livraison_id || undefined,
    };
    if (payload.client_id) {
        data.client_id = payload.client_id;
    }
    appendHistoricalFields(data, payload);

    router.post('/commandes', data, {
        preserveScroll: true,
        onSuccess: callbacks.onSuccess,
        onError: (e) => callbacks.onError(parseApiValidationErrors(e)),
    });
}

export function submitCommandeRelance(
    payload: FormEnregPayload,
    callbacks: SubmitCallbacks,
): void {
    const data: Record<string, unknown> = {
        pharmacie_id: payload.pharmacie_id,
        beneficiaire: payload.beneficiaire || undefined,
        produits: payload.produits,
        commentaire: payload.commentaire || undefined,
    };
    if (payload.client_sexe) {
        data.client_sexe = payload.client_sexe;
    }
    if (payload.client_id) {
        data.client_id = payload.client_id;
        data.client_arrondissement = payload.client_arrondissement;
    } else {
        data.client_nom = payload.client_nom;
        data.client_prenom = payload.client_prenom;
        data.client_tel = payload.client_tel;
        data.client_adresse = payload.client_adresse;
        data.client_arrondissement = payload.client_arrondissement;
    }

    const ordonnanceFiles = ordonnanceFilesFromValue(payload.ordonnance);
    if (ordonnanceFiles.length > 0) {
        if (rejectOversizedOrdonnance(ordonnanceFiles, callbacks)) {
            return;
        }

        const formData = new FormData();
        if (payload.client_id) {
            formData.append('client_id', String(payload.client_id));
            formData.append(
                'client_arrondissement',
                payload.client_arrondissement,
            );
        } else {
            formData.append('client_nom', payload.client_nom);
            formData.append('client_prenom', payload.client_prenom);
            formData.append('client_tel', payload.client_tel);
            formData.append('client_adresse', payload.client_adresse);
            formData.append(
                'client_arrondissement',
                payload.client_arrondissement,
            );
        }
        if (payload.client_sexe) {
            formData.append('client_sexe', payload.client_sexe);
        }
        formData.append('pharmacie_id', payload.pharmacie_id);
        if (payload.beneficiaire) {
            formData.append('beneficiaire', payload.beneficiaire);
        }
        formData.append('produits', JSON.stringify(payload.produits));
        appendOrdonnanceFiles(formData, ordonnanceFiles);
        if (payload.commentaire) {
            formData.append('commentaire', payload.commentaire);
        }
        router.post('/commandes', formData, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: callbacks.onSuccess,
            onError: (e) => callbacks.onError(parseApiValidationErrors(e)),
        });
        return;
    }

    if (payload.reutiliser_ordonnance_commande_id) {
        data.reutiliser_ordonnance_commande_id =
            payload.reutiliser_ordonnance_commande_id;
    }

    router.post('/commandes', data, {
        preserveScroll: true,
        onSuccess: callbacks.onSuccess,
        onError: (e) => callbacks.onError(parseApiValidationErrors(e)),
    });
}
