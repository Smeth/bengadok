/**
 * Inertia utilise Axios pour les navigations POST/PUT/etc. Laravel exige X-CSRF-TOKEN ou le cookie XSRF.
 * Les visites avec `only` excluent souvent les props partagées : le jeton doit être fusionné avec
 * AlwaysProp côté PHP + resynchronisé ici après chaque page réussie.
 *
 * @see https://laravel.com/docs/csrf#csrf-x-csrf-token
 */
import { router } from '@inertiajs/vue3';
import type { AxiosError } from 'axios';
import axios from 'axios';

axios.defaults.withCredentials = true;

function readMetaCsrfToken(): string | null {
    return (
        document
            .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? null
    );
}

function applyCsrfToken(token: string | undefined | null): void {
    if (!token || token.length === 0) {
        return;
    }
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
    document
        .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.setAttribute('content', token);
}

function syncCsrfFromInertiaPage(page: {
    props?: Record<string, unknown>;
}): void {
    const raw = page.props?.csrf_token;
    if (typeof raw === 'string' && raw.length > 0) {
        applyCsrfToken(raw);
    }
}

applyCsrfToken(readMetaCsrfToken());

router.on('navigate', (event) =>
    syncCsrfFromInertiaPage(event.detail.page),
);
router.on('success', (event) =>
    syncCsrfFromInertiaPage(event.detail.page),
);

/** Requêtes Axios « hors » Inertia (uploads Uppy, appels métier…) : garder un en-tête aligné sur le méta-tag. */
axios.interceptors.request.use((config) => {
    const method = (config.method ?? 'get').toLowerCase();
    if (['post', 'put', 'patch', 'delete'].includes(method)) {
        const t = readMetaCsrfToken();
        if (t) {
            config.headers.set('X-CSRF-TOKEN', t);
        }
    }
    return config;
});

let reloadingFrom419 = false;

function reloadPageAfter419(): void {
    if (reloadingFrom419 || typeof window === 'undefined') {
        return;
    }
    reloadingFrom419 = true;
    window.location.reload();
}

/**
 * Réponse Axios 419 hors Inertia ou client HTTP custom.
 */
axios.interceptors.response.use(
    (res) => res,
    (error: AxiosError) => {
        if (error.response?.status === 419) {
            reloadPageAfter419();
        }
        return Promise.reject(error);
    },
);

/**
 * Visites Inertia rejetées (CSRF périmé, session expirée en plein clic).
 */
router.on('invalid', (event) => {
    const status = event.detail.response?.status;
    if (status === 419) {
        reloadPageAfter419();
    }
});
