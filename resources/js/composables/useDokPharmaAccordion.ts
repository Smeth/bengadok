import { ref, type Ref } from 'vue';
import type { DokPharmaCommande } from '@/lib/dokPharmaCommande';

export function useDokPharmaAccordion(
    onExpand?: (cmd: DokPharmaCommande) => void,
    externalExpandedCards?: Ref<Set<number>>,
) {
    const expandedCards = externalExpandedCards ?? ref<Set<number>>(new Set());

    function isExpanded(id: number): boolean {
        return expandedCards.value.has(id);
    }

    function toggleCard(cmd: DokPharmaCommande) {
        const next = new Set(expandedCards.value);
        if (next.has(cmd.id)) {
            next.delete(cmd.id);
        } else {
            try {
                onExpand?.(cmd);
            } catch (error) {
                console.error('[DokPharma] Erreur initialisation formulaire', error);
                return;
            }
            next.add(cmd.id);
        }
        expandedCards.value = next;
    }

    function collapseCard(cmdId: number) {
        const next = new Set(expandedCards.value);
        next.delete(cmdId);
        expandedCards.value = next;
    }

    return { expandedCards, isExpanded, toggleCard, collapseCard };
}
