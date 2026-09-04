import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { CommandeCreationContext } from '@/lib/commandeCreationFields';
import {
    fieldAppliesInContext,
    isCommandeFieldRequired,
    type CommandeCreationFieldDefinition,
    validateCommandeCreationFields,
} from '@/lib/commandeCreationFields';

const FALLBACK_DEFINITIONS: CommandeCreationFieldDefinition[] = [
    {
        key: 'client_prenom',
        label: 'Prénom du client',
        group: 'client',
        required: true,
        default: true,
        contexts: ['admin', 'agent'],
    },
    {
        key: 'client_tel',
        label: 'Téléphone',
        group: 'client',
        required: true,
        default: true,
        contexts: ['admin', 'agent'],
    },
    {
        key: 'client_adresse',
        label: 'Adresse',
        group: 'client',
        required: true,
        default: true,
        contexts: ['admin', 'agent'],
    },
    {
        key: 'client_arrondissement',
        label: 'Arrondissement',
        group: 'client',
        required: true,
        default: true,
        contexts: ['admin', 'agent'],
    },
];

export function useCommandeCreationFields(context: CommandeCreationContext) {
    const page = usePage();

    const definitions = computed((): CommandeCreationFieldDefinition[] => {
        const shared = (
            page.props as {
                commandeCreationFields?: CommandeCreationFieldDefinition[];
            }
        ).commandeCreationFields;

        return shared?.length ? shared : FALLBACK_DEFINITIONS;
    });

    function applies(key: string): boolean {
        const def = definitions.value.find((d) => d.key === key);

        return def ? fieldAppliesInContext(def, context) : false;
    }

    function isRequired(
        key: string,
        options: { sansClientExistant?: boolean } = {},
    ): boolean {
        return isCommandeFieldRequired(
            definitions.value,
            key,
            context,
            options,
        );
    }

    function validate(
        values: Record<string, unknown>,
        options: {
            sansClientExistant?: boolean;
            skipOrdonnanceIfReused?: boolean;
        } = {},
    ): Record<string, string> {
        return validateCommandeCreationFields(
            definitions.value,
            context,
            values,
            options,
        );
    }

    const clientFields = computed(() =>
        definitions.value.filter((d) => d.group === 'client'),
    );

    const commandeFields = computed(() =>
        definitions.value.filter((d) => d.group === 'commande'),
    );

    return {
        definitions,
        clientFields,
        commandeFields,
        applies,
        isRequired,
        validate,
    };
}
