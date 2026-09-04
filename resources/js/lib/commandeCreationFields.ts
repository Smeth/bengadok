export type CommandeCreationContext = 'admin' | 'agent';

export type CommandeCreationFieldDefinition = {
    key: string;
    label: string;
    group: 'client' | 'commande';
    required: boolean;
    default: boolean;
    contexts: CommandeCreationContext[];
};

export const COMMANDE_CREATION_FIELD_MESSAGES: Record<string, string> = {
    client_nom: 'Le nom du client est obligatoire.',
    client_prenom: 'Le prénom du client est obligatoire.',
    client_tel: 'Le téléphone est obligatoire.',
    client_adresse: "L'adresse est obligatoire.",
    client_arrondissement: "L'arrondissement est obligatoire.",
    client_sexe: 'La civilité est obligatoire.',
    beneficiaire: 'Le bénéficiaire est obligatoire.',
    ordonnance: "L'ordonnance est obligatoire.",
    mode_paiement_id: 'Le mode de paiement est obligatoire.',
    montant_livraison_id: 'Le montant de livraison est obligatoire.',
    livreur_id: 'Le livreur est obligatoire.',
    commentaire: 'Le commentaire est obligatoire.',
};

export function fieldAppliesInContext(
    def: CommandeCreationFieldDefinition,
    context: CommandeCreationContext,
): boolean {
    return def.contexts.includes(context);
}

export function isCommandeFieldRequired(
    definitions: CommandeCreationFieldDefinition[],
    key: string,
    context: CommandeCreationContext,
    options: { sansClientExistant?: boolean } = {},
): boolean {
    const def = definitions.find((d) => d.key === key);
    if (!def?.required || !fieldAppliesInContext(def, context)) {
        return false;
    }

    const sansClientExistant = options.sansClientExistant ?? true;
    if (key.startsWith('client_') && !sansClientExistant) {
        return false;
    }

    return true;
}

export function validateCommandeCreationFields(
    definitions: CommandeCreationFieldDefinition[],
    context: CommandeCreationContext,
    values: Record<string, unknown>,
    options: {
        sansClientExistant?: boolean;
        skipOrdonnanceIfReused?: boolean;
    } = {},
): Record<string, string> {
    const errors: Record<string, string> = {};
    const sansClientExistant = options.sansClientExistant ?? true;

    const checkString = (key: string, raw: unknown): void => {
        if (
            !isCommandeFieldRequired(definitions, key, context, {
                sansClientExistant,
            })
        ) {
            return;
        }

        const value =
            typeof raw === 'string'
                ? raw.trim()
                : raw === null || raw === undefined
                  ? ''
                  : String(raw).trim();

        if (!value) {
            errors[key] =
                COMMANDE_CREATION_FIELD_MESSAGES[key] ??
                'Ce champ est obligatoire.';
        }
    };

    checkString('client_nom', values.client_nom);
    checkString('client_prenom', values.client_prenom);
    checkString('client_tel', values.client_tel);
    checkString('client_adresse', values.client_adresse);
    checkString('client_arrondissement', values.client_arrondissement);
    checkString('client_sexe', values.client_sexe);
    checkString('beneficiaire', values.beneficiaire);
    checkString('commentaire', values.commentaire);

    if (
        isCommandeFieldRequired(definitions, 'ordonnance', context) &&
        !options.skipOrdonnanceIfReused &&
        !values.ordonnance
    ) {
        errors.ordonnance = COMMANDE_CREATION_FIELD_MESSAGES.ordonnance;
    }

    if (
        isCommandeFieldRequired(definitions, 'mode_paiement_id', context) &&
        !values.mode_paiement_id
    ) {
        errors.mode_paiement_id =
            COMMANDE_CREATION_FIELD_MESSAGES.mode_paiement_id;
    }

    if (
        isCommandeFieldRequired(definitions, 'livreur_id', context) &&
        !values.livreur_id
    ) {
        errors.livreur_id = COMMANDE_CREATION_FIELD_MESSAGES.livreur_id;
    }

    if (
        isCommandeFieldRequired(definitions, 'montant_livraison_id', context) &&
        !values.montant_livraison_id
    ) {
        errors.montant_livraison_id =
            COMMANDE_CREATION_FIELD_MESSAGES.montant_livraison_id;
    }

    return errors;
}

export function contextLabel(contexts: CommandeCreationContext[]): string {
    if (contexts.length === 0) {
        return 'Réservé API';
    }

    const parts: string[] = [];
    if (contexts.includes('admin')) {
        parts.push('back-office');
    }
    if (contexts.includes('agent')) {
        parts.push('agent');
    }

    return parts.join(' · ');
}
