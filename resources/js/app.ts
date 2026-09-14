import './bootstrap-csrf';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h, Fragment } from 'vue';
import GlobalToastHost from '@/components/GlobalToastHost.vue';
import { syncUploadLimitsFromPage } from '@/lib/uploadLimits';
import '../css/app.css';
import { configureEcho } from '@laravel/echo-vue';
import { initializeTheme } from './composables/useAppearance';

/** Sans clé Reverb, ne pas initialiser Echo (évite POST /broadcasting/auth → 403). */
if (import.meta.env.VITE_REVERB_APP_KEY) {
    configureEcho({
        broadcaster: 'reverb',
    });
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props: initialPage, plugin }) {
        syncUploadLimitsFromPage(
            (initialPage as { props?: Record<string, unknown> }).props,
        );

        createApp({
            render: () =>
                h(Fragment, null, [
                    h(App, initialPage),
                    h(GlobalToastHost),
                ]),
        })
            .use(plugin)
            .mount(el);
    },
    // Désactivé : la barre de progression pouvait rester affichée et bloquer
    // l'interaction avec le formulaire de connexion (champs, bouton).
    progress: false,
});

// This will set light / dark mode on page load...
initializeTheme();
