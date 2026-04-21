/**
 * Formate une date « jour seul » (YYYY-MM-DD) en calendrier local,
 * sans décalage dû au parsing ISO minuit UTC (new Date('2026-04-04')).
 */
export function formatDateFrLocal(d: string | null | undefined): string {
    if (d == null || String(d).trim() === '') return '-';
    const s = String(d).trim();
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s);
    if (m) {
        const dt = new Date(
            Number(m[1]),
            Number(m[2]) - 1,
            Number(m[3]),
        );
        if (Number.isNaN(dt.getTime())) return '-';
        return dt.toLocaleDateString('fr-FR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        });
    }
    const dt = new Date(s);
    if (Number.isNaN(dt.getTime())) return '-';
    return dt.toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}
