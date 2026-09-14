import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

/**
 * Front Inertia : une seule entrée Vite (app.ts) + pages résolues via import.meta.glob.
 * Ne pas réintroduire @vite(['app.ts', 'pages/…']) dans app.blade.php (manifest manquant → 500).
 * Dev Windows : host localhost (pas [::1]) — voir scripts/vite-predev.mjs.
 */
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
            // Wrapper Node : cwd projet + chemin PHP fiable sous Windows
            command: 'node scripts/wayfinder-generate.mjs',
            // Ne pas relancer à chaque .php (trop lent / timeout HMR) — utiliser npm run wayfinder:generate
            patterns: [],
        }),
    ],
    server: {
        // Évite [::1]:5173 (souvent bloqué / NS_BINDING_ABORTED sous Firefox Windows)
        host: 'localhost',
        hmr: {
            host: 'localhost',
            overlay: true,
        },
    },
});
