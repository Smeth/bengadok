import { ref } from 'vue';
import type { DokPharmaCommande } from '@/lib/dokPharmaCommande';

export function useDokPharmaAccordion(onExpand?: (cmd: DokPharmaCommande) => void) {
    const expandedCards = ref<Set<number>>(new Set());

    function toggleCard(cmd: DokPharmaCommande) {
        const next = new Set(expandedCards.value);
        if (next.has(cmd.id)) {
            next.delete(cmd.id);
        } else {
            next.add(cmd.id);
            onExpand?.(cmd);
        }
        expandedCards.value = next;
    }

    function collapseCard(cmdId: number) {
        const next = new Set(expandedCards.value);
        next.delete(cmdId);
        expandedCards.value = next;
    }

    return { expandedCards, toggleCard, collapseCard };
}
