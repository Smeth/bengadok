/**
 * Design tokens BengaDok — backoffice modules (Clients, Commandes, Médicaments…)
 */

export const colors = {
    primary: '#459cd1',
    primaryHover: '#3a87b8',
    primaryRing: 'ring-[#459cd1]/40',
    accent: '#459cd1',
    accentHover: '#3a87b8',
    emerald: '#016630',
    textMuted: '#5c5959',
} as const;

/** Conteneur page standard (sur fond dégradé). */
export const modulePageClass =
    'relative flex min-h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6 md:p-8';

/** Carte blanche module. */
export const moduleCardClass =
    'rounded-xl border border-border bg-white shadow-sm dark:border-white/10 dark:bg-white/[0.96]';

/** Panneau filtres blanc. */
export const moduleFilterPanelClass =
    'rounded-xl border border-white/80 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/[0.96] sm:p-5';

/** Conteneur pagination sur fond dégradé (aligné filtres / tableaux). */
export const modulePaginationWrapperClass = moduleFilterPanelClass;

/** Select natif harmonisé. */
export const moduleSelectClass =
    'flex h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-900 shadow-sm';

/** Input date harmonisé. */
export const moduleInputDateClass =
    'flex h-10 w-[180px] rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-900 shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#459cd1]/40';

/** Champ recherche pill (dans panneau filtres). */
export const moduleSearchInputClass =
    'h-10 w-full rounded-full border-0 bg-white pl-10 pr-4 text-sm text-slate-900 shadow-sm ring-1 ring-black/10 placeholder:text-slate-500 focus-visible:ring-2 focus-visible:ring-[#459cd1]/40';

/** Anneau de focus clavier commun (onglets, boutons module). */
export const moduleTabFocusClass =
    'outline-none focus-visible:ring-2 focus-visible:ring-[#459cd1]/40 focus-visible:ring-offset-2 focus-visible:ring-offset-transparent';

/** Onglet actif (navigation par lien). */
export const moduleTabActiveClass =
    'inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium bg-[#459cd1] text-white shadow-sm';

/** Onglet inactif (navigation par lien). */
export const moduleTabInactiveClass =
    'inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium transition-colors bg-white/80 text-muted-foreground hover:bg-white hover:text-foreground';

/** Bouton primaire module. */
export const modulePrimaryButtonClass =
    'rounded-lg bg-[#459cd1] text-white hover:bg-[#3a87b8]';

/** Badge compteur par défaut. */
export const moduleCounterDefaultClass = 'bg-[#459cd1]';

/** Pagination — lien actif. */
export const modulePaginationActiveClass =
    'border-[#459cd1] bg-[#459cd1] text-white';

/** Pagination — lien inactif. */
export const modulePaginationInactiveClass =
    'border-input bg-white text-foreground';

/** Onglet inactif sur fond clair (réglages). */
export const moduleTabInactiveOnLightClass =
    'inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium transition-colors bg-transparent text-muted-foreground hover:bg-muted/80 hover:text-foreground';

/** Focus champs (tiroirs, formulaires). */
export const moduleInputFocusClass =
    'focus:border-[#459cd1] focus:outline-none focus:ring-1 focus:ring-[#459cd1]/40';

/** Rétrocompatibilité Clients. */
export const clientsSelectClass = moduleSelectClass;
