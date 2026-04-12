import type { Auth } from '@/types/auth';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            /** Motifs d’annulation configurés (slug, label, relance) */
            motifs_annulation: Array<{
                slug: string;
                label: string;
                autorise_relance: boolean;
            }>;
            /** Heures : même pharmacie interdite en relance pendant ce délai après annulation (0 = désactivé) */
            delai_relance_meme_pharmacie_heures: number;
            /** Jeton CSRF (réponse Laravel, mis à jour à chaque navigation Inertia) */
            csrf_token: string;
            /** Réinitialisations destructives (local ou configuration serveur) */
            allowPharmacyReset: boolean;
            /** Réinitialisation « base neuve » réservée à l’environnement de développement */
            allowLocalAppReset: boolean;
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}
