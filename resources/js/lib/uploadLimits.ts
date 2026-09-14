export type UploadLimitsPayload = {
    max_bytes: number;
    max_label: string;
    app_max_bytes: number;
    app_max_label: string;
    message: string;
};

const DEFAULT_LIMITS: UploadLimitsPayload = {
    max_bytes: 10 * 1024 * 1024,
    max_label: '10 Mo',
    app_max_bytes: 10 * 1024 * 1024,
    app_max_label: '10 Mo',
    message:
        'Fichier ou formulaire trop volumineux. Taille maximale acceptée : 10 Mo.',
};

let cachedLimits: UploadLimitsPayload = DEFAULT_LIMITS;

export function syncUploadLimitsFromPage(
    props?: Record<string, unknown>,
): void {
    const raw = props?.upload_limits;
    if (raw && typeof raw === 'object' && 'max_bytes' in raw) {
        cachedLimits = raw as UploadLimitsPayload;
    }
}

export function getUploadLimits(): UploadLimitsPayload {
    return cachedLimits;
}

export function payloadTooLargeMessage(): string {
    return cachedLimits.message;
}

export function validateFileSize(file: File): string | null {
    if (file.size <= cachedLimits.max_bytes) {
        return null;
    }

    return cachedLimits.message;
}

export function uploadMaxSizeNote(prefix = ''): string {
    const label = cachedLimits.max_label;
    return prefix ? `${prefix} — max. ${label}` : `max. ${label}`;
}
