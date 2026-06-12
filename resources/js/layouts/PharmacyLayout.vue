<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Bell,
    ChevronDown,
    ClipboardList,
    LayoutGrid,
    LogOut,
    PanelLeftClose,
    PanelLeftOpen,
    Settings,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import RealtimeNotificationsListener from '@/components/RealtimeNotificationsListener.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';

/** Aligné sur la sidebar admin : 16rem + mode icône 5rem (app.css) */
const SIDEBAR_W = 256;
const SIDEBAR_W_ICON = 80;

const props = withDefaults(
    defineProps<{
        pageTitle?: string;
        variant?: 'gradient' | 'plain';
        pharmaciesDisponibles?: Array<{ id: number; designation: string }>;
        pharmacieId?: number;
        pharmacieDesignation?: string;
    }>(),
    {
        pageTitle: '',
        variant: 'gradient',
        pharmaciesDisponibles: () => [],
        pharmacieId: undefined,
        pharmacieDesignation: '',
    },
);

const page = usePage();
const { isCurrentUrl } = useCurrentUrl();

const pharmacieSelectorOpen = ref(false);

const activePharmacieLabel = computed(() => {
    if (props.pharmacieDesignation) {
        return props.pharmacieDesignation;
    }
    const match = props.pharmaciesDisponibles.find(
        (p) => p.id === props.pharmacieId,
    );
    return match?.designation ?? pharmacie.value?.designation ?? 'Pharmacie';
});

function selectPharmacie(id: number) {
    pharmacieSelectorOpen.value = false;
    if (id === props.pharmacieId) {
        return;
    }
    router.get('/dok-pharma', { pharmacie_id: id }, { preserveScroll: true });
}

const sidebarCollapsed = ref(false);
const asideWidthPx = computed(() =>
    sidebarCollapsed.value ? SIDEBAR_W_ICON : SIDEBAR_W,
);

const pharmacie = computed(() => page.props.auth?.user?.pharmacie);
const user = computed(() => page.props.auth?.user);
/** Vendeur sans rôle gérant : pas d’accès au tableau de bord pharmacie. */
const userRoles = computed(
    () =>
        (page.props.auth?.user as { roles?: string[] } | undefined)?.roles ??
        [],
);
const isVendeurSeul = computed(
    () =>
        userRoles.value.includes('vendeur') &&
        !userRoles.value.includes('gerant'),
);
const pharmaHomeHref = computed(() =>
    isVendeurSeul.value ? '/dok-pharma/commandes' : '/dok-pharma',
);
const roleLabel = computed(() => {
    const roles = (
        page.props.auth?.user as { roles?: string[] } | undefined
    )?.roles;
    const r = roles?.[0];
    if (r === 'gerant') return 'Gestionnaire';
    if (r === 'vendeur') return 'Vendeur';
    return r ? r.charAt(0).toUpperCase() + r.slice(1) : '';
});

/** Même fond que l’admin sur /settings (AppSidebarLayout sans dégradé dashboard). */
const isSettingsRoute = computed(() => page.url.startsWith('/settings'));

interface NotificationItem {
    id: number;
    numero: string;
    status_label: string;
    client?: { nom: string; prenom?: string } | null;
    beneficiaire?: string | null;
    url: string;
    created_at: string;
}

function formatOrdererName(item: NotificationItem): string {
    const c = item.client;
    if (c) {
        const full = [c.prenom, c.nom].filter(Boolean).join(' ').trim();
        if (full) return full;
    }
    const b = item.beneficiaire?.trim();
    if (b && b !== 'Soi-même') return b;
    return '—';
}

const notifications = computed(() => {
    const n = (
        page.props as {
            notifications?: { count: number; items: NotificationItem[] };
        }
    ).notifications;
    return n ?? { count: 0, items: [] };
});

const formatDate = (iso?: string) => {
    if (!iso) return '';
    try {
        const d = new Date(iso);
        return d.toLocaleDateString('fr-FR', {
            day: '2-digit',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return '';
    }
};

function logout() {
    router.post('/logout');
}
</script>

<template>
    <div class="flex min-h-svh bg-white">
        <RealtimeNotificationsListener />
        <aside
            class="fixed left-0 top-0 z-40 flex h-svh flex-col overflow-x-hidden overflow-y-auto bg-white shadow-[5px_0px_10px_0px_rgba(0,0,0,0.25)] transition-[width] duration-200 ease-out"
            :style="{ width: `${asideWidthPx}px` }"
        >
            <!-- En-tête : même logique que AppSidebar (grille 3 col + padding SidebarHeader) -->
            <div
                class="shrink-0 border-b-0"
                :class="
                    sidebarCollapsed ? 'px-2 py-3' : 'px-3 pb-4 pt-2'
                "
            >
                <!-- Replié : pile verticale centrée comme mode icône admin -->
                <div
                    v-if="sidebarCollapsed"
                    class="flex flex-col items-center justify-center gap-2"
                >
                    <Link
                        :href="pharmaHomeHref"
                        class="flex items-center justify-center"
                        :aria-label="
                            isVendeurSeul ? 'Commandes' : 'Tableau de bord'
                        "
                    >
                        <span class="inline-flex origin-center scale-[0.72]">
                            <AppLogo icon-only />
                        </span>
                    </Link>
                    <button
                        type="button"
                        class="z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-[#f1f5f9] text-[#6b7280] transition-colors hover:bg-[#e2e8f0] hover:text-[#374151]"
                        :aria-expanded="!sidebarCollapsed"
                        aria-label="Replier ou déplier la barre latérale"
                        @click="sidebarCollapsed = !sidebarCollapsed"
                    >
                        <PanelLeftOpen class="size-[15px] shrink-0" />
                    </button>
                </div>
                <!-- Déplié : grille [spacer | logo | trigger] comme AppSidebar -->
                <div
                    v-else
                    class="relative grid w-full grid-cols-[2rem_minmax(0,1fr)_2rem] items-end gap-x-1"
                >
                    <div class="min-w-0" aria-hidden="true" />
                    <Link
                        :href="pharmaHomeHref"
                        class="flex min-w-0 items-end justify-center overflow-visible"
                    >
                        <AppLogo />
                    </Link>
                    <div class="flex justify-end self-end">
                        <button
                            type="button"
                            class="z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-[#f1f5f9] text-[#6b7280] transition-colors hover:bg-[#e2e8f0] hover:text-[#374151]"
                            :aria-expanded="!sidebarCollapsed"
                            aria-label="Replier ou déplier la barre latérale"
                            @click="sidebarCollapsed = !sidebarCollapsed"
                        >
                            <PanelLeftClose class="size-[18px] shrink-0" />
                        </button>
                    </div>
                </div>
                <p
                    v-if="!sidebarCollapsed"
                    class="mt-3 max-w-full truncate px-2 text-center text-[11px] font-bold leading-tight text-[#5c5959]"
                >
                    BengaDok Pharmacie
                </p>
            </div>

            <div
                class="flex min-h-0 flex-1 flex-col overflow-x-hidden overflow-y-auto px-2 py-4"
            >
                <!-- Navigation : mêmes classes que NavMain (admin) -->
                <nav
                    class="flex flex-col gap-1 px-2 py-2"
                    :class="sidebarCollapsed ? 'items-center px-0' : ''"
                >
                    <Link
                        v-if="!isVendeurSeul"
                        href="/dok-pharma"
                        class="sidebar-menu-btn-react group flex w-full items-center gap-3 rounded-xl px-3 py-2.5 transition-all duration-150"
                        :class="
                            (sidebarCollapsed
                                ? 'w-auto justify-center px-2'
                                : '',
                            isCurrentUrl('/dok-pharma')
                                ? 'bg-[#5BB66E] font-bold text-white shadow-sm'
                                : 'bg-transparent hover:bg-[rgba(92,89,89,0.08)]')
                        "
                        :data-active="
                            isCurrentUrl('/dok-pharma') ? 'true' : undefined
                        "
                    >
                        <div
                            class="sidebar-menu-icon flex size-[36px] shrink-0 items-center justify-center rounded-full transition-colors"
                            :class="
                                isCurrentUrl('/dok-pharma')
                                    ? 'bg-white/25 text-white'
                                    : 'bg-[rgba(92,89,89,0.25)] group-hover:bg-[rgba(92,89,89,0.35)]'
                            "
                        >
                            <LayoutGrid
                                class="sidebar-menu-icon-svg size-6"
                                :class="
                                    isCurrentUrl('/dok-pharma')
                                        ? 'text-white'
                                        : 'text-[#5c5959]'
                                "
                            />
                        </div>
                        <span
                            v-if="!sidebarCollapsed"
                            class="sidebar-menu-label truncate text-[14px] font-bold leading-tight text-[#5c5959] transition-colors"
                            :class="
                                isCurrentUrl('/dok-pharma') ? '!text-white' : ''
                            "
                        >
                            Tableau de bord
                        </span>
                    </Link>

                    <Link
                        href="/dok-pharma/commandes"
                        class="sidebar-menu-btn-react group flex w-full items-center gap-3 rounded-xl px-3 py-2.5 transition-all duration-150"
                        :class="
                            (sidebarCollapsed
                                ? 'w-auto justify-center px-2'
                                : '',
                            isCurrentUrl('/dok-pharma/commandes')
                                ? 'bg-[#5BB66E] font-bold text-white shadow-sm'
                                : 'bg-transparent hover:bg-[rgba(92,89,89,0.08)]')
                        "
                        :data-active="
                            isCurrentUrl('/dok-pharma/commandes')
                                ? 'true'
                                : undefined
                        "
                    >
                        <div
                            class="sidebar-menu-icon flex size-[36px] shrink-0 items-center justify-center rounded-full transition-colors"
                            :class="
                                isCurrentUrl('/dok-pharma/commandes')
                                    ? 'bg-white/25 text-white'
                                    : 'bg-[rgba(92,89,89,0.25)] group-hover:bg-[rgba(92,89,89,0.35)]'
                            "
                        >
                            <ClipboardList
                                class="sidebar-menu-icon-svg size-6"
                                :class="
                                    isCurrentUrl('/dok-pharma/commandes')
                                        ? 'text-white'
                                        : 'text-[#5c5959]'
                                "
                            />
                        </div>
                        <span
                            v-if="!sidebarCollapsed"
                            class="sidebar-menu-label truncate text-[14px] font-bold leading-tight text-[#5c5959] transition-colors"
                            :class="
                                isCurrentUrl('/dok-pharma/commandes')
                                    ? '!text-white'
                                    : ''
                            "
                        >
                            Commandes
                        </span>
                    </Link>
                </nav>

                <div class="min-h-[80px] flex-1" />

                <div
                    class="mt-12 px-2 pb-1"
                    :class="sidebarCollapsed ? 'flex justify-center px-0' : ''"
                >
                    <Link
                        href="/settings/profile"
                        class="sidebar-menu-btn-react group flex w-full items-center gap-3 rounded-xl px-3 py-2.5 transition-all"
                        :class="
                            (sidebarCollapsed
                                ? 'w-auto justify-center px-2'
                                : '',
                            isCurrentUrl('/settings/profile')
                                ? 'bg-[#5BB66E] font-bold text-white shadow-sm'
                                : 'bg-transparent hover:bg-[rgba(92,89,89,0.08)]')
                        "
                        :data-active="
                            isCurrentUrl('/settings/profile')
                                ? 'true'
                                : undefined
                        "
                    >
                        <div
                            class="sidebar-menu-icon flex size-[36px] shrink-0 items-center justify-center rounded-full transition-colors"
                            :class="
                                isCurrentUrl('/settings/profile')
                                    ? 'bg-white/25 text-white'
                                    : 'bg-[rgba(92,89,89,0.25)] group-hover:bg-[rgba(92,89,89,0.35)]'
                            "
                        >
                            <Settings
                                class="sidebar-menu-icon-svg size-6"
                                :class="
                                    isCurrentUrl('/settings/profile')
                                        ? 'text-white'
                                        : 'text-[#5c5959]'
                                "
                            />
                        </div>
                        <span
                            v-if="!sidebarCollapsed"
                            class="sidebar-menu-label truncate text-[14px] font-bold leading-tight text-[#5c5959]"
                            :class="
                                isCurrentUrl('/settings/profile')
                                    ? '!text-white'
                                    : ''
                            "
                        >
                            Réglages
                        </span>
                    </Link>
                </div>

                <!-- Déconnexion : pied de page admin (AppSidebar) -->
                <div
                    class="mt-4 w-full px-2 pb-2"
                    :class="sidebarCollapsed ? 'flex justify-center px-0' : ''"
                >
                    <button
                        v-if="!sidebarCollapsed"
                        type="button"
                        class="flex w-full items-center justify-center gap-3 rounded-full px-4 py-2.5 text-[14px] font-bold text-[#5c5959] transition-colors hover:bg-[rgba(92,89,89,0.08)] hover:text-[#5c5959]"
                        @click="logout"
                    >
                        <div
                            class="flex size-[36px] shrink-0 items-center justify-center rounded-full bg-[#5c5959] text-white"
                        >
                            <LogOut class="size-5 shrink-0" />
                        </div>
                        <span>Déconnexion</span>
                    </button>
                    <button
                        v-else
                        type="button"
                        class="flex items-center justify-center rounded-full p-2 transition-colors hover:bg-[rgba(92,89,89,0.08)]"
                        aria-label="Déconnexion"
                        @click="logout"
                    >
                        <div
                            class="flex size-[36px] items-center justify-center rounded-full bg-[#5c5959] text-white"
                        >
                            <LogOut class="size-5 shrink-0" />
                        </div>
                    </button>
                </div>
            </div>
        </aside>

        <!-- Dégradé sur toute la colonne (header + contenu), comme Figma — pas seulement sous le slot -->
        <main
            :class="[
                'flex min-h-0 flex-1 flex-col overflow-x-clip overflow-y-auto transition-[margin] duration-200 ease-out sm:overflow-x-visible',
                isSettingsRoute || variant === 'plain'
                    ? 'relative isolate bg-[#f8fafc]'
                    : 'pharmacy-main-gradient',
            ]"
            :style="{ marginLeft: `${asideWidthPx}px` }"
        >
            <header
                class="sticky top-0 z-30 flex min-h-[68px] shrink-0 items-center justify-between gap-4 border-b border-gray-100 bg-white/95 px-5 py-3 backdrop-blur-sm sm:px-8"
            >
                <div class="flex min-w-0 items-center gap-4">
                    <h1
                        v-if="pageTitle"
                        class="truncate text-lg font-bold text-gray-900 sm:text-xl"
                    >
                        {{ pageTitle }}
                    </h1>
                    <div
                        v-if="pharmaciesDisponibles.length"
                        class="relative"
                    >
                        <button
                            type="button"
                            class="flex max-w-[220px] items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50"
                            @click="
                                pharmacieSelectorOpen = !pharmacieSelectorOpen
                            "
                        >
                            <span class="truncate">{{
                                activePharmacieLabel
                            }}</span>
                            <ChevronDown class="size-4 shrink-0" />
                        </button>
                        <div
                            v-show="pharmacieSelectorOpen"
                            class="absolute left-0 top-full z-40 mt-1 min-w-[220px] rounded-lg border bg-white py-1 shadow-lg"
                        >
                            <button
                                v-for="p in pharmaciesDisponibles"
                                :key="p.id"
                                type="button"
                                class="w-full px-4 py-2 text-left text-sm hover:bg-gray-100"
                                :class="
                                    p.id === pharmacieId
                                        ? 'bg-[#F0FDF4] font-bold text-[#198754]'
                                        : ''
                                "
                                @click="selectPharmacie(p.id)"
                            >
                                {{ p.designation }}
                            </button>
                        </div>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-4">
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button
                            type="button"
                            class="relative flex size-[57px] shrink-0 items-center justify-center rounded-full border border-black/[0.08] bg-white shadow-[0_2px_8px_rgba(0,0,0,0.08)] transition-colors hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-[#3995d2]/30"
                            aria-label="Notifications"
                        >
                            <Bell class="size-[22px] text-[#3995d2]" />
                            <span
                                v-if="notifications.count > 0"
                                class="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-[#3995d2] px-1 text-[10px] font-semibold text-white ring-2 ring-white"
                            >
                                {{
                                    notifications.count > 99
                                        ? '99+'
                                        : notifications.count
                                }}
                            </span>
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-80">
                        <DropdownMenuLabel
                            class="flex items-center justify-between"
                        >
                            <span>Notifications</span>
                            <span
                                v-if="notifications.count > 0"
                                class="text-xs font-normal text-muted-foreground"
                            >
                                {{ notifications.count }} commande(s)
                            </span>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <div
                            v-if="notifications.items.length"
                            class="max-h-72 overflow-y-auto"
                        >
                            <button
                                v-for="item in notifications.items"
                                :key="item.id"
                                type="button"
                                class="block w-full cursor-pointer px-2 py-2 text-left text-sm hover:bg-accent"
                                @click="router.visit(item.url)"
                            >
                                <div class="font-medium">
                                    Commande {{ formatOrdererName(item) }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ item.numero }} · {{ item.status_label }}
                                </div>
                                <div
                                    class="mt-0.5 text-xs text-muted-foreground"
                                >
                                    {{ formatDate(item.created_at) }}
                                </div>
                            </button>
                        </div>
                        <div
                            v-else
                            class="px-2 py-6 text-center text-sm text-muted-foreground"
                        >
                            Aucune nouvelle commande
                        </div>
                        <DropdownMenuSeparator />
                        <Link
                            href="/dok-pharma/commandes"
                            class="block px-2 py-2 text-center text-sm font-medium text-primary hover:bg-accent"
                        >
                            Voir toutes les commandes
                        </Link>
                    </DropdownMenuContent>
                </DropdownMenu>
                <DropdownMenu v-if="user">
                    <DropdownMenuTrigger as-child>
                        <button
                            type="button"
                            class="flex h-[57px] min-w-0 max-w-[min(100vw-10rem,312px)] cursor-pointer items-center gap-3 rounded-[50px] border border-black/[0.12] bg-white/80 px-4 shadow-sm backdrop-blur-sm transition-colors hover:bg-white/90 focus:outline-none"
                        >
                            <div
                                class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-[#5bb66e]/18"
                            >
                                <svg
                                    class="size-[18px] text-[#5bb66e]"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path
                                        d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"
                                    />
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1 text-left">
                                <p
                                    class="truncate text-[15px] font-black text-black"
                                >
                                    {{ user?.name ?? 'Utilisateur' }}
                                </p>
                                <p
                                    v-if="roleLabel"
                                    class="truncate text-[12px] font-semibold text-[#3995d2]"
                                >
                                    {{ roleLabel }}
                                </p>
                                <p
                                    class="truncate text-[12px] font-medium text-black/65"
                                >
                                    {{ pharmacie?.designation ?? 'Pharmacie' }}
                                </p>
                            </div>
                            <ChevronDown
                                class="size-5 shrink-0 text-black/40"
                            />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-56">
                        <UserMenuContent :user="user" />
                    </DropdownMenuContent>
                </DropdownMenu>
                </div>
            </header>

            <div class="pharmacy-main-body flex min-h-0 flex-1 flex-col">
                <slot />
            </div>
        </main>
    </div>
</template>
