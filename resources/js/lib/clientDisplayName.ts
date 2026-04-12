/**
 * Libellé client « prénom nom » sans afficher la chaîne "null"
 * quand l’API envoie null pour nom / prénom (JSON).
 */
export function clientNomComplet(c: {
    nom?: string | null;
    prenom?: string | null;
}): string {
    const p = (c.prenom ?? '').trim();
    const n = (c.nom ?? '').trim();
    if (!p && !n) return '-';
    if (p === n) return p;
    return [p, n].filter(Boolean).join(' ');
}

/** Civilité + prénom + nom (M. / Mme selon sexe M/F). */
export function clientNomAvecCivilite(c: {
    nom?: string | null;
    prenom?: string | null;
    sexe?: string | null;
}): string {
    const p = (c.prenom ?? '').trim();
    const n = (c.nom ?? '').trim();
    if (!p && !n) return '-';
    const civ = c.sexe === 'F' ? 'Mme' : 'M.';
    if (p === n) return `${civ} ${p}`;
    return `${civ} ${[p, n].filter(Boolean).join(' ')}`.trim();
}
