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
    'rounded-xl border border-border bg-white shadow-sm dark:border-white/10 dark:bg-card';

/** Panneau filtres blanc. */
export const moduleFilterPanelClass =
    'rounded-xl border border-white/80 bg-white p-4 shadow-sm dark:border-border dark:bg-card sm:p-5';

/** Conteneur pagination sur fond dégradé (aligné filtres / tableaux). */
export const modulePaginationWrapperClass = moduleFilterPanelClass;

/** Select natif harmonisé. */
export const moduleSelectClass =
    'flex h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-900 shadow-sm dark:border-border dark:bg-input dark:text-foreground';

/** Input date harmonisé. */
export const moduleInputDateClass =
    'flex h-10 w-[180px] rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-900 shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#459cd1]/40 dark:border-border dark:bg-input dark:text-foreground';

/** Champ recherche pill (dans panneau filtres). */
export const moduleSearchInputClass =
    'h-10 w-full rounded-full border-0 bg-white pl-10 pr-4 text-sm text-slate-900 shadow-sm ring-1 ring-black/10 placeholder:text-slate-500 focus-visible:ring-2 focus-visible:ring-[#459cd1]/40 dark:bg-input dark:text-foreground dark:ring-white/10 dark:placeholder:text-muted-foreground';

/** Input formulaire module (hauteur 42px). */
export const moduleFormInputClass =
    'h-[42px] w-full rounded-[10px] border border-[#ccc5c5] bg-white px-3 py-2 text-sm text-gray-900 placeholder:italic placeholder:text-[rgba(92,89,89,0.4)] focus:border-[#459cd1] focus:outline-none focus:ring-1 focus:ring-[#459cd1] dark:border-border dark:bg-input dark:text-foreground dark:placeholder:text-muted-foreground';

/** Select formulaire module (hauteur 42px). */
export const moduleFormSelectClass =
    'h-[42px] w-full appearance-none rounded-[10px] border border-[#ccc5c5] bg-white px-3 py-2 pr-8 text-sm text-gray-900 focus:border-[#459cd1] focus:outline-none focus:ring-1 focus:ring-[#459cd1] dark:border-border dark:bg-input dark:text-foreground';

/** Carte commande espace pharmacie. */
export const pharmacyOrderCardClass =
    'overflow-hidden rounded-2xl bg-white shadow-sm dark:border dark:border-border dark:bg-card';

/** Surface dashboard admin (KPI, panneaux). */
export const dashboardSurfaceClass =
    'rounded-[23px] bg-white p-6 shadow-[0px_4px_10px_rgba(0,0,0,0.25)] dark:border dark:border-border dark:bg-card dark:shadow-md';

/** Hero dashboard admin. */
export const dashboardHeroSurfaceClass =
    'relative overflow-hidden rounded-[30px] bg-white p-8 shadow-[0px_4px_10px_rgba(0,0,0,0.25)] dark:border dark:border-border dark:bg-card';

/** Anneau de focus clavier commun (onglets, boutons module). */
export const moduleTabFocusClass =
    'outline-none focus-visible:ring-2 focus-visible:ring-[#459cd1]/40 focus-visible:ring-offset-2 focus-visible:ring-offset-transparent';

/** Onglet actif (navigation par lien). */
export const moduleTabActiveClass =
    'inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium bg-[#459cd1] text-white shadow-sm';

/** Onglet inactif (navigation par lien). */
export const moduleTabInactiveClass =
    'inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium transition-colors bg-white/80 text-muted-foreground hover:bg-white hover:text-foreground dark:bg-card/80 dark:hover:bg-card dark:hover:text-foreground';

/** Bouton primaire module. */
export const modulePrimaryButtonClass =
    'rounded-lg bg-[#459cd1] text-white hover:bg-[#3a87b8]';

/** Fond / texte / bordure primaires (composants atomiques). */
export const modulePrimaryBgClass = 'bg-[#459cd1]';
export const modulePrimaryHoverBgClass = 'hover:bg-[#3a87b8]';
export const modulePrimaryTextClass = 'text-[#459cd1]';
export const modulePrimaryBorderClass = 'border-[#459cd1]';

/** Bouton solide sans radius (shadcn Button). */
export const modulePrimaryButtonSolidClass =
    'bg-[#459cd1] text-white hover:bg-[#3a87b8]';

/** Carte sélectionnée (rôle, option). */
export const modulePrimarySelectedCardClass =
    'border-[#459cd1] bg-sky-50 dark:bg-sky-950/30';

/** Option sélectionnée (radio, checkbox). */
export const modulePrimarySelectedOptionClass =
    'border-[#459cd1] bg-[#459cd1]/10 dark:bg-[#459cd1]/20';

/** Bandeau info / sélection légère. */
export const modulePrimaryAlertBannerClass =
    'rounded-lg border border-[#459cd1]/30 bg-[#459cd1]/10 dark:border-[#459cd1]/40 dark:bg-[#459cd1]/15';

/** Badge compteur par défaut. */
export const moduleCounterDefaultClass = 'bg-[#459cd1]';

/** Pagination — lien actif. */
export const modulePaginationActiveClass =
    'border-[#459cd1] bg-[#459cd1] text-white';

/** Pagination — lien inactif. */
export const modulePaginationInactiveClass =
    'border-input bg-white text-foreground dark:bg-card dark:border-border';

/** Onglet inactif sur fond clair (réglages). */
export const moduleTabInactiveOnLightClass =
    'inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium transition-colors bg-transparent text-muted-foreground hover:bg-muted/80 hover:text-foreground';

/** Focus champs (tiroirs, formulaires). */
export const moduleInputFocusClass =
    'focus:border-[#459cd1] focus:outline-none focus:ring-1 focus:ring-[#459cd1]/40';

/** Rétrocompatibilité Clients. */
export const clientsSelectClass = moduleSelectClass;

/** Panneau carte détail (commandes, parapharma, crédits). */
export const moduleDetailPanelClass =
    'rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-border dark:bg-card';

/** Panneau détail large (p-6). */
export const moduleDetailPanelLgClass =
    'rounded-2xl border border-gray-100 bg-white p-6 shadow-sm dark:border-border dark:bg-card';

/** En-tête panneau détail latéral. */
export const moduleDetailHeaderClass =
    'flex flex-col gap-3 border-b border-gray-100 bg-white px-4 py-4 shadow-sm dark:border-border dark:bg-card sm:flex-row sm:items-start sm:justify-between sm:px-6';

/** Section formulaire avec bordure #ccc5c5. */
export const moduleFormSectionClass =
    'rounded-[10px] border border-[#ccc5c5] p-5 dark:border-border';

/** Ligne produit dans formulaire modale. */
export const moduleFormProductCardClass =
    'mb-4 flex flex-col gap-3 rounded-[10px] border border-[#ccc5c5] bg-white p-4 last:mb-0 dark:border-border dark:bg-muted/20';

/** Section page Paramètres. */
export const settingsSectionClass =
    'rounded-xl border border-gray-200 bg-white overflow-hidden dark:border-border dark:bg-card';

/** Input natif modale commande (sans w-full forcé). */
export const moduleNativeInputClass =
    'h-[42px] rounded-[10px] border border-[#ccc5c5] bg-white px-3 py-2 text-sm text-gray-900 placeholder:italic placeholder:text-[rgba(92,89,89,0.4)] focus:border-[#459cd1] focus:outline-none focus:ring-1 focus:ring-[#459cd1] dark:border-border dark:bg-input dark:text-foreground dark:placeholder:text-muted-foreground';

/** Select natif modale commande. */
export const moduleNativeSelectClass =
    'h-[42px] w-full appearance-none rounded-[10px] border border-[#ccc5c5] bg-white px-3 py-2 pr-10 text-sm text-gray-900 focus:border-[#459cd1] focus:outline-none focus:ring-1 focus:ring-[#459cd1] dark:border-border dark:bg-input dark:text-foreground';

/** Label formulaire standard. */
export const moduleLabelClass =
    'text-sm font-medium text-black dark:text-foreground';

/** Label formulaire léger (lignes produit). */
export const moduleLabelLightClass =
    'text-base font-light text-black dark:text-foreground';

/** Dialog shell modale (agent, dok-pharma…). */
export const moduleModalSurfaceClass =
    'overflow-hidden rounded-2xl bg-white shadow-2xl dark:border dark:border-border dark:bg-card';

/** Section formulaire agent (bordure gray-200). */
export const moduleAgentSectionClass =
    'rounded-xl border border-gray-200 p-4 dark:border-border';

/** Input rounded-lg générique. */
export const moduleRoundedInputClass =
    'rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-[#459cd1]/40 dark:border-border dark:bg-input dark:text-foreground';

/** Dialog shell modale commande (positionnement Uppy/Dialog). */
export const commandeModalShellClass =
    '!left-[50%] !top-[50%] !w-[650px] !max-w-[95vw] !-translate-x-1/2 !-translate-y-1/2 !max-h-[80vh] !overflow-hidden !rounded-[15px] !border !border-[#ccc5c5] !bg-white !p-0 !shadow-xl dark:!border-border dark:!bg-card';

/** En-tête sticky modale commande. */
export const commandeModalHeaderClass =
    'sticky top-0 z-10 flex items-center justify-between gap-2 rounded-t-[15px] border-b border-[#ccc5c5] bg-white px-6 py-4 shadow-[0px_2px_8px_rgba(0,0,0,0.06)] dark:border-border dark:bg-card';
