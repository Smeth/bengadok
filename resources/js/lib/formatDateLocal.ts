/**
 * Formate une date « jour seul » (YYYY-MM-DD) en calendrier local,
 * sans décalage dû au parsing ISO minuit UTC (new Date('2026-04-04')).
 */
export function formatDateFrLocal(d: string | null | undefined): string {
    if (d == null || String(d).trim() === '') return '-';
    const parts = parseDatePartLocal(d);
    if (!parts) return '-';
    const [y, mo, day] = parts;
    const dt = new Date(y, mo - 1, day);
    if (Number.isNaN(dt.getTime())) return '-';
    return dt.toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

const DEFAULT_TZ = 'Africa/Brazzaville';

/** Extrait HH:MM depuis le champ métier `heurs` (ex. "14:30" ou "08:00-19:00"). */
export function extractHeureCommande(
    heursRaw: string | null | undefined,
): string | null {
    if (heursRaw == null || String(heursRaw).trim() === '') {
        return null;
    }
    const s = String(heursRaw).trim();
    const exact = /^(\d{1,2}:\d{2})$/.exec(s);
    if (exact) return exact[1];
    const plage = /^(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})/.exec(s);
    if (plage) return plage[1];
    const any = /(\d{1,2}:\d{2})/.exec(s);
    return any ? any[1] : null;
}

/**
 * Date + heure commande (aligné sur CommandeDateFormatter côté Laravel).
 * Le champ `heurs` est déjà en heure locale métier — pas de conversion fuseau.
 */
export function formatCommandeDateHeure(
    date: string | null | undefined,
    heurs?: string | null,
    createdAt?: string | null,
    timeZone: string = DEFAULT_TZ,
): string {
    // Déjà formaté côté serveur (ex. DokPharma) : "dd/mm/yyyy HH:mm"
    const already =
        typeof date === 'string'
            ? /^(\d{2})\/(\d{2})\/(\d{4})(?:\s+(\d{1,2}:\d{2}))?/.exec(
                  date.trim(),
              )
            : null;
    if (already) {
        if (already[4]) {
            const [hh, mm] = already[4].split(':');
            return `${already[1]}/${already[2]}/${already[3]} ${String(Number(hh)).padStart(2, '0')}:${String(Number(mm)).padStart(2, '0')}`;
        }
        const time =
            extractHeureCommande(heurs) ??
            extractTimeFromIso(createdAt, timeZone) ??
            '00:00';
        return `${already[1]}/${already[2]}/${already[3]} ${time}`;
    }

    const datePart = parseDatePartLocal(date, timeZone);
    if (datePart) {
        const time =
            extractHeureCommande(heurs) ??
            extractTimeFromIso(createdAt, timeZone) ??
            '00:00';
        const [y, mo, d] = datePart;
        const [hhRaw, mmRaw] = time.split(':');
        const hh = String(Number(hhRaw)).padStart(2, '0');
        const mm = String(Number(mmRaw)).padStart(2, '0');
        const day = String(d).padStart(2, '0');
        const month = String(mo).padStart(2, '0');

        return `${day}/${month}/${y} ${hh}:${mm}`;
    }
    if (createdAt) {
        const dt = new Date(createdAt);
        if (Number.isNaN(dt.getTime())) return '-';
        return dt.toLocaleString('fr-FR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            timeZone,
        });
    }
    return '-';
}

function parseDatePartLocal(
    date: string | null | undefined,
    timeZone: string = DEFAULT_TZ,
): [number, number, number] | null {
    if (date == null || String(date).trim() === '') return null;
    const s = String(date).trim();

    // Jour calendaire pur (sans heure) — ne pas interpréter en UTC.
    const dayOnly = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s);
    if (dayOnly) {
        return [Number(dayOnly[1]), Number(dayOnly[2]), Number(dayOnly[3])];
    }

    // ISO datetime : prendre le jour dans le fuseau métier (ex. WAT),
    // pas le préfixe UTC (minuit WAT → veille en Z).
    if (/^\d{4}-\d{2}-\d{2}T/.test(s)) {
        const dt = new Date(s);
        if (Number.isNaN(dt.getTime())) return null;
        const parts = new Intl.DateTimeFormat('en-CA', {
            timeZone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
        }).formatToParts(dt);
        const y = Number(parts.find((p) => p.type === 'year')?.value);
        const mo = Number(parts.find((p) => p.type === 'month')?.value);
        const d = Number(parts.find((p) => p.type === 'day')?.value);
        if (!y || !mo || !d) return null;
        return [y, mo, d];
    }

    const dt = new Date(s);
    if (Number.isNaN(dt.getTime())) return null;
    return [dt.getFullYear(), dt.getMonth() + 1, dt.getDate()];
}

function extractTimeFromIso(
    iso: string | null | undefined,
    timeZone: string,
): string | null {
    if (!iso) return null;
    const dt = new Date(iso);
    if (Number.isNaN(dt.getTime())) return null;
    const parts = new Intl.DateTimeFormat('fr-FR', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
        timeZone,
    }).formatToParts(dt);
    const h = parts.find((p) => p.type === 'hour')?.value;
    const min = parts.find((p) => p.type === 'minute')?.value;
    if (!h || !min) return null;
    return `${h.padStart(2, '0')}:${min.padStart(2, '0')}`;
}
