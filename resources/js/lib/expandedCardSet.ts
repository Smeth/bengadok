import { unref, type Ref } from 'vue';

/** Normalise un Set d’IDs (prop parfois reçue comme Ref après lazy-load). */
export function resolveExpandedCardSet(value: unknown): Set<number> {
    const resolved = unref(value as Set<number> | Ref<Set<number>>);
    return resolved instanceof Set ? resolved : new Set();
}

export function isIdInExpandedSet(value: unknown, id: number): boolean {
    return resolveExpandedCardSet(value).has(id);
}
