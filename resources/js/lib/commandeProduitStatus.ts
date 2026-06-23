export type StatutDisponibiliteLigne =
    | 'en_attente'
    | 'disponible'
    | 'indisponible'
    | 'partiel';

export function normaliserStatutDisponibiliteLigne(
    status: string | null | undefined,
): StatutDisponibiliteLigne {
    const v = (status ?? 'en_attente').toLowerCase();
    if (v === 'indisponible') return 'indisponible';
    if (v === 'disponible') return 'disponible';
    if (v === 'partiel') return 'partiel';

    return 'en_attente';
}

export function libelleStatutDisponibiliteLigne(
    status: string | null | undefined,
): string {
    const map: Record<StatutDisponibiliteLigne, string> = {
        en_attente: 'En attente',
        disponible: 'Disponible',
        indisponible: 'Indisponible',
        partiel: 'Partiel',
    };

    return map[normaliserStatutDisponibiliteLigne(status)];
}

export function classesStatutDisponibiliteLigne(
    status: string | null | undefined,
): string {
    const st = normaliserStatutDisponibiliteLigne(status);
    if (st === 'indisponible') {
        return 'border-red-400 bg-red-50 text-red-700';
    }
    if (st === 'en_attente') {
        return 'border-amber-400 bg-amber-50 text-amber-800';
    }
    if (st === 'partiel') {
        return 'border-orange-400 bg-orange-50 text-orange-800';
    }

    return 'border-[#016630] bg-[#e1f3e7] text-[#016630]';
}

/** Ligne comptée dans un total pharmacie (prix confirmé). */
export function ligneDisponibiliteCompteDansTotal(
    status: string | null | undefined,
): boolean {
    const st = normaliserStatutDisponibiliteLigne(status);

    return st === 'disponible' || st === 'partiel';
}
