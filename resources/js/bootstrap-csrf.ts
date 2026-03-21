/**
 * Inertia envoie les requêtes via Axios. Sans X-CSRF-TOKEN, Laravel répond 419 sur les POST.
 * @see https://laravel.com/docs/csrf#csrf-x-csrf-token
 */
import axios from 'axios';
import { router } from '@inertiajs/vue3';

function applyCsrfToken(token: string | undefined | null): void {
    if (!token) {
        return;
    }
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.setAttribute('content', token);
}

const metaToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.getAttribute('content');
applyCsrfToken(metaToken);

router.on('navigate', (event) => {
    const page = event.detail.page as { props?: { csrf_token?: string } };
    const t = page.props?.csrf_token;
    if (typeof t === 'string') {
        applyCsrfToken(t);
    }
});
