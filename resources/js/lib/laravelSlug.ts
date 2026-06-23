/** Aligné sur `Illuminate\Support\Str::slug()` (séparateur `-`). */
export function laravelSlug(text: string): string {
    return text
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

/** Prévisualisation identifiant pharmacie (format backend après création). */
export function previewPharmacieUsername(
    pharmacieDesignation: string,
    role: string,
    userName: string,
    nextUserId: number,
): string {
    if (!userName.trim()) {
        return 'pharmacie_role_nom_?';
    }

    const slugPharma = laravelSlug(pharmacieDesignation || 'pharmacie');
    const slugNom = laravelSlug(userName);

    return `${slugPharma}_${role}_${slugNom}_${nextUserId}`;
}
