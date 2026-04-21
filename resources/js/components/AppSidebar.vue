<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import {
    Building2,
    ClipboardList,
    LayoutGrid,
    LogOut,
    Package,
    Settings,
    Users,
    UserCog,
} from 'lucide-vue-next';
import { computed } from 'vue';
import NavMain from '@/components/NavMain.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarTrigger,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';
import AppLogo from './AppLogo.vue';

const page = usePage();
const { isCurrentUrl, currentUrl } = useCurrentUrl();
const roles = computed(
    () =>
        (page.props.auth as { user?: { roles?: string[] } })?.user?.roles ?? [],
);
const isAdminRole = computed(
    () =>
        roles.value.includes('admin') || roles.value.includes('super_admin'),
);
const isGerant = computed(() => roles.value.includes('gerant'));
const isVendeur = computed(() => roles.value.includes('vendeur'));
const isPharma = computed(() => isGerant.value || isVendeur.value);
/** Agent call center sans rôle admin : accès limité (commandes + médicaments). */
const isAgentCallCenterOnly = computed(
    () =>
        roles.value.includes('agent_call_center') && !isAdminRole.value,
);
const sidebarLogoHref = computed(() => {
    if (isVendeur.value && !isGerant.value && !isAdminRole.value) {
        return '/dok-pharma/commandes';
    }
    if (isPharma.value) return '/dok-pharma';
    if (isAgentCallCenterOnly.value) return '/commandes';
    return dashboard();
});

// Nav items exact order: Tableau de bord, Commandes, Pharmacies, Médicaments, Clients, Utilisateurs Backoffice
const mainNavItems = computed<NavItem[]>(() => {
    const isAdmin = isAdminRole.value;
    const isGerant = roles.value.includes('gerant');
    const isAgent = roles.value.includes('agent_call_center');
    const isVendeur = roles.value.includes('vendeur');

    const items: NavItem[] = [
        { title: 'Tableau de bord', href: dashboard(), icon: LayoutGrid },
        {
            title: 'Commandes',
            href: isPharma.value ? '/dok-pharma' : '/commandes',
            icon: ClipboardList,
        },
        { title: 'Pharmacies', href: '/pharmacies', icon: Building2 },
        { title: 'Médicaments', href: '/medicaments', icon: Package },
        { title: 'Clients', href: '/clients', icon: Users },
        {
            title: 'Utilisateurs Backoffice',
            href: '/utilisateurs',
            icon: UserCog,
        },
    ];

    if (isAgent && !isAdmin) {
        return [
            { title: 'Commandes', href: '/commandes', icon: ClipboardList },
            { title: 'Médicaments', href: '/medicaments', icon: Package },
        ];
    }
    if (isGerant && !isAdmin) {
        return [
            { title: 'Tableau de bord', href: dashboard(), icon: LayoutGrid },
            { title: 'Commandes', href: '/dok-pharma', icon: ClipboardList },
            { title: 'Vendeurs', href: '/pharmacie/vendeurs', icon: UserCog },
        ];
    }
    if (isVendeur && !isAdmin) {
        return [
            { title: 'Commandes', href: '/dok-pharma/commandes', icon: ClipboardList },
        ];
    }
    return items;
});

const reglagesItem = { title: 'Réglages', href: '/reglages', icon: Settings };

const isReglagesSectionActive = computed(
    () =>
        currentUrl.value.startsWith('/settings') ||
        isCurrentUrl(reglagesItem.href),
);

function logout() {
    router.post('/logout');
}
</script>

<template>
    <Sidebar
        collapsible="icon"
        variant="inset"
        class="border-r-0 bg-white shadow-[5px_0px_10px_0px_rgba(0,0,0,0.25)]"
    >
        <SidebarHeader
            class="relative shrink-0 border-b-0 border-sidebar-border px-3 pb-4 pt-2 group-data-[collapsible=icon]:px-2 group-data-[collapsible=icon]:py-3"
        >
            <SidebarMenu
                class="w-full group-data-[collapsible=icon]:flex group-data-[collapsible=icon]:justify-center"
            >
                <SidebarMenuItem
                    class="relative grid w-full grid-cols-[2rem_minmax(0,1fr)_2rem] items-end gap-x-1 group-data-[collapsible=icon]:flex group-data-[collapsible=icon]:w-auto group-data-[collapsible=icon]:flex-col group-data-[collapsible=icon]:items-center group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:gap-2"
                >
                    <!-- Équilibre la colonne du bouton repli (logo centré dans la zone utile) -->
                    <div
                        class="min-w-0 group-data-[collapsible=icon]:hidden"
                        aria-hidden="true"
                    />
                    <SidebarMenuButton
                        size="lg"
                        as-child
                        class="min-w-0 justify-self-center !h-auto !min-h-0 !overflow-visible !p-0 group-data-[collapsible=icon]:mx-0 group-data-[collapsible=icon]:flex-none group-data-[collapsible=icon]:!size-8 group-data-[collapsible=icon]:!overflow-hidden group-data-[collapsible=icon]:!p-2"
                    >
                        <Link
                            :href="sidebarLogoHref"
                            class="flex w-full min-w-0 items-end justify-center group-data-[collapsible=icon]:w-auto group-data-[collapsible=icon]:items-center group-data-[collapsible=icon]:justify-center"
                        >
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                    <div
                        class="flex justify-end self-end group-data-[collapsible=icon]:contents"
                    >
                        <SidebarTrigger
                            class="z-10 h-8 w-8 shrink-0 rounded-md bg-[#f1f5f9] text-[#6b7280] hover:bg-[#e2e8f0] hover:text-[#374151] group-data-[collapsible=icon]:self-center"
                            aria-label="Réduire la barre latérale"
                        />
                    </div>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent
            class="flex min-h-0 flex-1 flex-col gap-0 overflow-x-hidden overflow-y-auto px-3 py-2 group-data-[collapsible=icon]:px-0"
        >
            <NavMain :items="mainNavItems" />

            <!-- Réglages : espace net sous le bloc principal -->
            <div
                v-if="!isAgentCallCenterOnly"
                class="mt-12 shrink-0 px-0 pb-1 group-data-[collapsible=icon]:flex group-data-[collapsible=icon]:justify-center"
            >
                <Link
                    :href="reglagesItem.href"
                    class="sidebar-menu-btn-react group flex h-11 w-full items-center gap-3 rounded-[10px] px-3 transition-all"
                    :class="
                        isReglagesSectionActive
                            ? 'bg-[#22c55e] text-white'
                            : 'bg-transparent'
                    "
                    :data-active="
                        isReglagesSectionActive ? 'true' : undefined
                    "
                >
                    <div
                        class="sidebar-menu-icon flex shrink-0 items-center justify-center rounded-full transition-colors"
                        :class="
                            isReglagesSectionActive
                                ? 'bg-white/25 text-white'
                                : ''
                        "
                    >
                        <component
                            :is="reglagesItem.icon"
                            class="sidebar-menu-icon-svg size-5"
                            stroke-width="1.5"
                        />
                    </div>
                    <span
                        class="sidebar-menu-label group-data-[collapsible=icon]:hidden"
                    >
                        {{ reglagesItem.title }}
                    </span>
                </Link>
            </div>

            <!-- Illustration : hauteur fixe sous Réglages -->
            <div
                v-if="!isAgentCallCenterOnly"
                class="pointer-events-none group-data-[collapsible=icon]:hidden relative mt-3 flex shrink-0 justify-center overflow-visible pt-1"
            >
                <div
                    class="relative mx-auto h-[min(240px,32vh)] min-h-[200px] w-full max-w-[220px] shrink-0"
                >
                    <!-- Blob bleu organique -->
                    <div
                        class="absolute left-1/2 top-1/2 flex h-[100px] w-[130px] -translate-x-1/2 -translate-y-1/2 items-center justify-center"
                    >
                        <div
                            class="absolute h-[85px] w-[110px] rounded-[22px] bg-[#3995d2] shadow-[0_2px_4px_rgba(0,0,0,0.2)] -rotate-[13deg]"
                        />
                        <div
                            class="absolute h-[92px] w-[118px] rounded-[22px] bg-[#3995d2] shadow-[0_2px_4px_rgba(0,0,0,0.2)] rotate-[25deg]"
                        />
                    </div>
                    <!-- 3D Doctor -->
                    <img
                        src="/images/figma-assets/sidebar-doctor-main.png"
                        alt=""
                        class="absolute left-1/2 bottom-0 h-[80%] w-auto max-w-[130px] -translate-x-1/2 object-contain object-bottom pointer-events-none"
                        style="
                            filter: drop-shadow(0 4px 4px rgba(0, 0, 0, 0.25));
                        "
                    />
                </div>
            </div>
        </SidebarContent>

        <SidebarFooter
            class="shrink-0 border-t-0 px-3 pb-5 pt-2 group-data-[collapsible=icon]:flex group-data-[collapsible=icon]:items-center group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:p-2"
        >
            <div
                class="flex w-full items-center gap-3 group-data-[collapsible=icon]:w-auto group-data-[collapsible=icon]:justify-center"
            >
                <button
                    type="button"
                    class="sidebar-logout-btn group flex h-11 w-full items-center gap-3 rounded-[10px] px-3 transition-colors group-data-[collapsible=icon]:w-auto group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-2"
                    @click="logout"
                >
                    <div
                        class="sidebar-logout-icon flex size-8 shrink-0 items-center justify-center rounded-full bg-[#f1f5f9] text-[#64748b] transition-colors group-hover:bg-[#e2e8f0]"
                    >
                        <LogOut
                            class="sidebar-menu-icon-svg size-5 shrink-0"
                            stroke-width="1.5"
                        />
                    </div>
                    <span
                        class="sidebar-footer-label group-data-[collapsible=icon]:hidden"
                        >Déconnexion</span
                    >
                </button>
            </div>
        </SidebarFooter>
    </Sidebar>
</template>
