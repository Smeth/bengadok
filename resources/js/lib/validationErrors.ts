/** Erreurs de validation Inertia / Laravel (valeurs string ou tableau). */
export function normalizeInertiaErrors(
    raw: Record<string, unknown> | undefined | null,
): Record<string, string> {
    if (!raw || typeof raw !== 'object') {
        return {};
    }
    const out: Record<string, string> = {};
    for (const [k, v] of Object.entries(raw)) {
        if (Array.isArray(v) && v.length) {
            out[k] = String(v[0]);
        } else if (typeof v === 'string') {
            out[k] = v;
        }
    }
    return out;
}

export function fieldError(
    errors: Record<string, string>,
    key: string,
): string | undefined {
    const v = errors[key];
    if (typeof v !== 'string' || v.trim() === '') {
        return undefined;
    }
    return v;
}
