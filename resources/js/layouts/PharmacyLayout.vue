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

const page = usePage();
const { isCurrentUrl } = useCurrentUrl();

const sidebarCollapsed = ref(false);
const asideWidthPx = computed(() => (sidebarCollapsed.value ? SIDEBAR_W_ICON : SIDEBAR_W));

const pharmacie = computed(() => page.props.auth?.user?.pharmacie);
const user = computed(() => page.props.auth?.user);
const userEmail = computed(() => page.props.auth?.user?.email ?? '');

/** Même fond que l’admin sur /settings (AppSidebarLayout sans dégradé dashboard). */
const isSettingsRoute = computed(() => page.url.startsWith('/settings'));

interface NotificationItem {
    id: number;
    numero: string;
    status_label: string;
    url: string;
    created_at: string;
}

const notifications = computed(() => {
    const n = (page.props as { notifications?: { count: number; items: NotificationItem[] } }).notifications;
    return n ?? { count: 0, items: [] };
});

const formatDate = (iso?: string) => {
    if (!iso) return '';
    try {
        const d = new Date(iso);
        return d.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
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
        <aside
            class="fixed left-0 top-0 z-40 flex h-svh flex-col overflow-x-hidden overflow-y-auto bg-white shadow-[5px_0px_10px_0px_rgba(0,0,0,0.25)] transition-[width] duration-200 ease-out"
            :style="{ width: `${asideWidthPx}px` }"
        >
            <!-- En-tête : logo + icône repli (PanelLeft comme l’admin) -->
            <div class="shrink-0 border-b-0" :class="sidebarCollapsed ? 'py-3 pl-2 pr-1.5' : 'p-4'">
                <!-- Replié : 80px utiles → logo centré dans l’espace restant + bouton fixe à droite (plus de décalage qui coupe à gauche) -->
                <div
                    v-if="sidebarCollapsed"
                    class="relative flex min-h-10 items-center"
                >
                    <Link
                        href="/dok-pharma"
                        class="flex min-w-0 flex-1 items-center justify-center pr-[34px]"
                        aria-label="Tableau de bord"
                    >
                        <span class="inline-flex origin-center scale-[0.72]">
                            <AppLogo icon-only />
                        </span>
                    </Link>
                    <button
                        type="button"
                        class="absolute right-0 top-1/2 flex size-8 shrink-0 -translate-y-1/2 items-center justify-center rounded-lg bg-[rgba(92,89,89,0.08)] text-[#5c5959] transition-colors hover:bg-[rgba(92,89,89,0.15)] hover:text-[#5c5959]"
                        :aria-expanded="!sidebarCollapsed"
                        aria-label="Replier ou déplier la barre latérale"
                        @click="sidebarCollapsed = !sidebarCollapsed"
                    >
                        <PanelLeftOpen class="size-[15px] shrink-0" />
                    </button>
                </div>
                <div v-else class="flex items-center gap-1">
                    <Link
                        href="/dok-pharma"
                        class="flex min-w-0 flex-1 items-center justify-center overflow-hidden sm:justify-start"
                    >
                        <AppLogo />
                    </Link>
                    <button
                        type="button"
                        class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-[rgba(92,89,89,0.08)] text-[#5c5959] transition-colors hover:bg-[rgba(92,89,89,0.15)] hover:text-[#5c5959]"
                        :aria-expanded="!sidebarCollapsed"
                        aria-label="Replier ou déplier la barre latérale"
                        @click="sidebarCollapsed = !sidebarCollapsed"
                    >
                        <PanelLeftClose class="size-[18px] shrink-0" />
                    </button>
                </div>
            </div>

            <div class="flex min-h-0 flex-1 flex-col overflow-x-hidden overflow-y-auto px-2 py-4">
                <!-- Navigation : mêmes classes que NavMain (admin) -->
                <nav class="flex flex-col gap-1 px-2 py-2" :class="sidebarCollapsed ? 'items-center px-0' : ''">
                    <Link
                        href="/dok-pharma"
                        class="sidebar-menu-btn-react group flex w-full items-center gap-3 rounded-xl px-3 py-2.5 transition-all duration-150"
                        :class="
                            sidebarCollapsed
                                ? 'w-auto justify-center px-2'
                                : '',
                            isCurrentUrl('/dok-pharma')
                                ? 'bg-[#5BB66E] font-bold text-white shadow-sm'
                                : 'bg-transparent hover:bg-[rgba(92,89,89,0.08)]'
                        "
                        :data-active="isCurrentUrl('/dok-pharma') ? 'true' : undefined"
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
                                :class="isCurrentUrl('/dok-pharma') ? 'text-white' : 'text-[#5c5959]'"
                            />
                        </div>
                        <span
                            v-if="!sidebarCollapsed"
                            class="sidebar-menu-label truncate text-[14px] font-bold leading-tight text-[#5c5959] transition-colors"
                            :class="isCurrentUrl('/dok-pharma') ? '!text-white' : ''"
                        >
                            Tableau de bord
                        </span>
                    </Link>

                    <Link
                        href="/dok-pharma/commandes"
                        class="sidebar-menu-btn-react group flex w-full items-center gap-3 rounded-xl px-3 py-2.5 transition-all duration-150"
                        :class="
                            sidebarCollapsed ? 'w-auto justify-center px-2' : '',
                            isCurrentUrl('/dok-pharma/commandes')
                                ? 'bg-[#5BB66E] font-bold text-white shadow-sm'
                                : 'bg-transparent hover:bg-[rgba(92,89,89,0.08)]'
                        "
                        :data-active="isCurrentUrl('/dok-pharma/commandes') ? 'true' : undefined"
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
                                :class="isCurrentUrl('/dok-pharma/commandes') ? 'text-white' : 'text-[#5c5959]'"
                            />
                        </div>
                        <span
                            v-if="!sidebarCollapsed"
                            class="sidebar-menu-label truncate text-[14px] font-bold leading-tight text-[#5c5959] transition-colors"
                            :class="isCurrentUrl('/dok-pharma/commandes') ? '!text-white' : ''"
                        >
                            Commandes
                        </span>
                    </Link>
                </nav>

                <div class="min-h-[80px] flex-1" />

                <div class="mt-12 px-2 pb-1" :class="sidebarCollapsed ? 'flex justify-center px-0' : ''">
                    <Link
                        href="/settings/profile"
                        class="sidebar-menu-btn-react group flex w-full items-center gap-3 rounded-xl px-3 py-2.5 transition-all"
                        :class="
                            sidebarCollapsed ? 'w-auto justify-center px-2' : '',
                            isCurrentUrl('/settings/profile')
                                ? 'bg-[#5BB66E] font-bold text-white shadow-sm'
                                : 'bg-transparent hover:bg-[rgba(92,89,89,0.08)]'
                        "
                        :data-active="isCurrentUrl('/settings/profile') ? 'true' : undefined"
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
                                :class="isCurrentUrl('/settings/profile') ? 'text-white' : 'text-[#5c5959]'"
                            />
                        </div>
                        <span
                            v-if="!sidebarCollapsed"
                            class="sidebar-menu-label truncate text-[14px] font-bold leading-tight text-[#5c5959]"
                            :class="isCurrentUrl('/settings/profile') ? '!text-white' : ''"
                        >
                            Réglages
                        </span>
                    </Link>
                </div>

                <!-- Illustration : proportions réduites (largeur ~ admin 16rem) -->
                <div v-if="!sidebarCollapsed" class="mt-6 hidden shrink-0 sm:block">
                    <div class="relative mx-auto h-[372px] w-[208px] shrink-0">
                        <div
                            class="absolute inset-x-0 bottom-0 z-0 h-[182px] rounded-[24px] bg-gradient-to-b from-[#4aa8df] to-[#3995d2] shadow-[0_4px_4px_rgba(0,0,0,0.25)]"
                        />
                        <img
                            src="/images/pharmacy/sidebar-pharmacienne.png"
                            alt=""
                            class="pointer-events-none absolute bottom-[32px] left-1/2 z-10 h-[284px] w-auto max-w-[248px] -translate-x-1/2 object-contain object-bottom drop-shadow-[0_4px_4px_rgba(0,0,0,0.25)]"
                        />
                    </div>
                </div>

                <!-- Déconnexion : pied de page admin (AppSidebar) -->
                <div class="mt-4 w-full px-2 pb-2" :class="sidebarCollapsed ? 'flex justify-center px-0' : ''">
                    <button
                        v-if="!sidebarCollapsed"
                        type="button"
                        class="flex w-full items-center justify-center gap-3 rounded-full px-4 py-2.5 text-[14px] font-bold text-[#5c5959] transition-colors hover:bg-[rgba(92,89,89,0.08)] hover:text-[#5c5959]"
                        @click="logout"
                    >
                        <div class="flex size-[36px] shrink-0 items-center justify-center rounded-full bg-[#5c5959] text-white">
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
                        <div class="flex size-[36px] items-center justify-center rounded-full bg-[#5c5959] text-white">
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
                isSettingsRoute ? 'relative isolate bg-background' : 'pharmacy-main-gradient',
            ]"
            :style="{ marginLeft: `${asideWidthPx}px` }"
        >
            <header
                class="sticky top-0 z-30 flex min-h-[68px] shrink-0 items-center justify-end gap-4 border-0 bg-transparent px-5 py-3 sm:px-8"
            >
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
                                {{ notifications.count > 99 ? '99+' : notifications.count }}
                            </span>
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-80">
                        <DropdownMenuLabel class="flex items-center justify-between">
                            <span>Notifications</span>
                            <span v-if="notifications.count > 0" class="text-xs font-normal text-muted-foreground">
                                {{ notifications.count }} commande(s)
                            </span>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <div v-if="notifications.items.length" class="max-h-72 overflow-y-auto">
                            <button
                                v-for="item in notifications.items"
                                :key="item.id"
                                type="button"
                                class="block w-full cursor-pointer px-2 py-2 text-left text-sm hover:bg-accent"
                                @click="router.visit(item.url)"
                            >
                                <div class="font-medium">Commande {{ item.numero }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{ item.status_label }}
                                </div>
                                <div class="mt-0.5 text-xs text-muted-foreground">
                                    {{ formatDate(item.created_at) }}
                                </div>
                            </button>
                        </div>
                        <div v-else class="px-2 py-6 text-center text-sm text-muted-foreground">
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
                            <div class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-[#5bb66e]/18">
                                <svg class="size-[18px] text-[#5bb66e]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1 text-left">
                                <p class="truncate text-[15px] font-black text-black">
                                    {{ pharmacie?.designation ?? 'Ma pharmacie' }}
                                </p>
                                <p class="truncate text-[13px] font-medium text-black/70">{{ userEmail }}</p>
                            </div>
                            <ChevronDown class="size-5 shrink-0 text-black/40" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-56">
                        <UserMenuContent :user="user" />
                    </DropdownMenuContent>
                </DropdownMenu>
            </header>

            <div class="pharmacy-main-body flex min-h-0 flex-1 flex-col">
                <slot />
            </div>
        </main>
    </div>
</template>
