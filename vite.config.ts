import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/js/app.ts',
                // Collision de chunk « Index » : entrée explicite pour que @vite retrouve la page en CI.
                'resources/js/pages/Commandes/Index.vue',
            ],
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
        hmr: {
            overlay: true,
        },
    },
});
