<script setup lang="ts">
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import {
    Building2,
    ClipboardList,
    LayoutGrid,
    LogOut,
    Package,
    PhoneCall,
    Settings,
    Users,
    UserCog,
} from 'lucide-vue-next';
import NavMain from '@/components/NavMain.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { usePage } from '@inertiajs/vue3';
import type { NavItem } from '@/types';
import AppLogo from './AppLogo.vue';
import { dashboard } from '@/routes';
import { useCurrentUrl } from '@/composables/useCurrentUrl';

const page = usePage();
const { isCurrentUrl } = useCurrentUrl();
const roles = computed(() => (page.props.auth as { user?: { roles?: string[] } })?.user?.roles ?? []);
const isGerant = computed(() => roles.value.includes('gerant'));
const isVendeur = computed(() => roles.value.includes('vendeur'));
const isPharma = computed(() => isGerant.value || isVendeur.value);

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [];
    const isAdmin = roles.value.includes('admin') || roles.value.includes('super_admin');
    const isGerant = roles.value.includes('gerant');
    const isAgent = roles.value.includes('agent_call_center');
    const isVendeur = roles.value.includes('vendeur');

    items.push({ title: 'Tableau de bord', href: dashboard(), icon: LayoutGrid });

    if (isAdmin) {
        items.push({ title: 'Commandes', href: '/commandes', icon: ClipboardList });
        items.push({ title: 'Pharmacies', href: '/pharmacies', icon: Building2 });
        items.push({ title: 'Médicaments', href: '/medicaments', icon: Package });
        items.push({ title: 'Clients', href: '/clients', icon: Users });
        items.push({ title: 'Utilisateurs Backoffice', href: '/utilisateurs', icon: UserCog });
    }

    if (isAgent && !isAdmin) {
        items.push({ title: 'Mes réceptions', href: '/agent', icon: PhoneCall });
        items.push({ title: 'Clients', href: '/clients', icon: Users });
    }

    if (isGerant || isVendeur) {
        items.push({ title: 'Commandes', href: '/dok-pharma', icon: ClipboardList });
    }
    if (isGerant) {
        items.push({ title: 'Vendeurs', href: '/pharmacie/vendeurs', icon: UserCog });
    }

    if (!isAdmin && !isGerant && !isAgent && !isVendeur) {
        items.push(
            { title: 'Commandes', href: '/commandes', icon: ClipboardList },
            { title: 'Pharmacies', href: '/pharmacies', icon: Building2 },
            { title: 'Médicaments', href: '/medicaments', icon: Package },
            { title: 'Clients', href: '/clients', icon: Users },
            { title: 'Utilisateurs Backoffice', href: '/utilisateurs', icon: UserCog },
        );
    }

    return items;
});

// Mon profil = compte utilisateur (profile, mot de passe, apparence)
// Configuration = paramètres métier (zones, paiements, livraison, etc.)
const settingsItem   = { title: 'Mon profil', href: '/settings/profile',   icon: Settings };
const parametresItem = { title: 'Configuration', href: '/settings/parametres', icon: Settings };
const isAdmin = computed(() => roles.value.includes('admin') || roles.value.includes('super_admin'));

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
        <SidebarHeader class="border-b-0 border-sidebar-border p-4 group-data-[collapsible=icon]:flex group-data-[collapsible=icon]:items-center group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:p-2">
            <SidebarMenu class="group-data-[collapsible=icon]:w-full group-data-[collapsible=icon]:items-center">
                <SidebarMenuItem class="flex items-center gap-1 group-data-[collapsible=icon]:flex-col group-data-[collapsible=icon]:w-full group-data-[collapsible=icon]:items-center group-data-[collapsible=icon]:gap-2">
                    <SidebarMenuButton size="lg" as-child class="min-w-0 flex-1 group-data-[collapsible=icon]:flex-none group-data-[collapsible=icon]:min-w-0">
                        <Link :href="dashboard()" class="flex w-full items-center justify-center group-data-[collapsible=icon]:w-auto">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                    <SidebarTrigger
                        class="shrink-0 size-9 rounded-lg bg-[rgba(92,89,89,0.08)] text-[#5c5959] hover:bg-[rgba(92,89,89,0.15)] hover:text-[#5c5959]"
                        aria-label="Réduire la barre latérale"
                    />
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="flex-1 py-4 px-2 group-data-[collapsible=icon]:px-0">
            <NavMain :items="mainNavItems" />

            <!-- Réglages : séparé par un grand espace vertical (design client) -->
            <div class="mt-12 px-2 pb-1 group-data-[collapsible=icon]:px-0 group-data-[collapsible=icon]:flex group-data-[collapsible=icon]:justify-center">
                <Link
                    :href="settingsItem.href"
                    class="sidebar-menu-btn-react group flex w-full items-center gap-3 rounded-xl px-3 py-2.5 transition-all"
                    :class="isCurrentUrl(settingsItem.href)
                        ? 'bg-[#5BB66E] text-white shadow-sm font-bold'
                        : 'bg-transparent hover:bg-[rgba(92,89,89,0.08)]'"
                    :data-active="isCurrentUrl(settingsItem.href) ? 'true' : undefined"
                >
                    <div class="sidebar-menu-icon flex size-[36px] shrink-0 items-center justify-center rounded-full transition-colors"
                        :class="isCurrentUrl(settingsItem.href) ? 'bg-white/25 text-white' : 'bg-[rgba(92,89,89,0.25)] group-hover:bg-[rgba(92,89,89,0.35)]'">
                        <component :is="settingsItem.icon" class="sidebar-menu-icon-svg size-6" :class="isCurrentUrl(settingsItem.href) ? 'text-white' : 'text-[#5c5959]'" />
                    </div>
                    <span class="sidebar-menu-label text-[14px] font-bold leading-tight text-[#5c5959] truncate group-data-[collapsible=icon]:hidden"
                        :class="isCurrentUrl(settingsItem.href) ? '!text-white' : ''">
                        {{ settingsItem.title }}
                    </span>
                </Link>
            </div>

            <!-- Paramètres métier (admin seulement) -->
            <div v-if="isAdmin" class="px-2 pb-2 group-data-[collapsible=icon]:px-0 group-data-[collapsible=icon]:flex group-data-[collapsible=icon]:justify-center">
                <Link
                    :href="parametresItem.href"
                    class="sidebar-menu-btn-react group flex w-full items-center gap-3 rounded-xl px-3 py-2.5 transition-all"
                    :class="isCurrentUrl(parametresItem.href)
                        ? 'bg-[#5BB66E] text-white shadow-sm font-bold'
                        : 'bg-transparent hover:bg-[rgba(92,89,89,0.08)]'"
                    :data-active="isCurrentUrl(parametresItem.href) ? 'true' : undefined"
                >
                    <div class="sidebar-menu-icon flex size-[36px] shrink-0 items-center justify-center rounded-full transition-colors"
                        :class="isCurrentUrl(parametresItem.href) ? 'bg-white/25 text-white' : 'bg-[rgba(92,89,89,0.25)] group-hover:bg-[rgba(92,89,89,0.35)]'">
                        <component :is="parametresItem.icon" class="sidebar-menu-icon-svg size-6" :class="isCurrentUrl(parametresItem.href) ? 'text-white' : 'text-[#5c5959]'" />
                    </div>
                    <span class="sidebar-menu-label text-[14px] font-bold leading-tight text-[#5c5959] truncate group-data-[collapsible=icon]:hidden"
                        :class="isCurrentUrl(parametresItem.href) ? '!text-white' : ''">
                        {{ parametresItem.title }}
                    </span>
                </Link>
            </div>

            <!-- Illustration sidebar Figma : docteur + blob bleu + 4 icônes flottantes (pointer-events-none : évite de capter les clics sur le contenu principal) -->
            <div
                class="pointer-events-none group-data-[collapsible=icon]:hidden mt-auto flex min-h-[200px] items-end justify-center pt-4"
            >
                <div class="relative mx-auto h-[200px] w-full max-w-[220px]">
                    <!-- Blob bleu arrière-plan (2 formes superposées) -->
                    <div class="absolute flex items-center justify-center left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-[140px] h-[120px]">
                        <div class="absolute w-[110px] h-[95px] rounded-[20px] bg-[#3995d2] shadow-[0_4px_4px_rgba(0,0,0,0.25)] rotate-[25deg]" />
                        <div class="absolute w-[100px] h-[85px] rounded-[20px] bg-[#3995d2] shadow-[0_4px_4px_rgba(0,0,0,0.25)] -rotate-[13deg]" />
                    </div>
                    <!-- Docteur principal -->
                    <img
                        src="/images/figma-assets/sidebar-doctor-main.png"
                        alt=""
                        class="absolute left-1/2 bottom-0 -translate-x-1/2 w-[140px] h-auto object-contain object-bottom pointer-events-none"
                        style="filter: drop-shadow(0 4px 4px rgba(0,0,0,0.25));"
                    />
                    <!-- Icône Caducée (gauche) -->
                    <img
                        src="/images/figma-assets/sidebar-icon-caduceus.svg"
                        alt=""
                        class="absolute left-0 top-[18%] w-[50px] h-auto opacity-95"
                        style="filter: drop-shadow(0 4px 4px rgba(0,0,0,0.25)); transform: rotate(-17deg);"
                    />
                    <!-- Icône Pilules (haut centre-droite) -->
                    <img
                        src="/images/figma-assets/sidebar-icon-pills.png"
                        alt=""
                        class="absolute right-[5%] top-[12%] w-[38px] h-auto opacity-95"
                        style="filter: drop-shadow(0 4px 4px rgba(0,0,0,0.25)); transform: rotate(10deg);"
                    />
                    <!-- Icône Flacon (centre-droite) -->
                    <img
                        src="/images/figma-assets/sidebar-icon-bottle.png"
                        alt=""
                        class="absolute left-[38%] top-[15%] w-[42px] h-auto opacity-95"
                        style="filter: drop-shadow(0 4px 4px rgba(0,0,0,0.25)); transform: rotate(27deg);"
                    />
                    <!-- Icône Bar chart (droite) -->
                    <img
                        src="/images/figma-assets/sidebar-icon-barchart.png"
                        alt=""
                        class="absolute right-[2%] top-[35%] w-[44px] h-auto opacity-95"
                        style="filter: drop-shadow(0 4px 4px rgba(0,0,0,0.25)); transform: rotate(13deg);"
                    />
                </div>
            </div>
        </SidebarContent>

        <SidebarFooter class="border-t-0 p-3 pb-6 group-data-[collapsible=icon]:flex group-data-[collapsible=icon]:items-center group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:p-2">
            <div class="flex flex-col items-center gap-3 w-full group-data-[collapsible=icon]:items-center group-data-[collapsible=icon]:w-auto">
                <button
                    type="button"
                    class="flex w-full items-center justify-center gap-3 rounded-full px-4 py-2.5 font-bold text-[14px] text-[#5c5959] transition-colors hover:bg-[rgba(92,89,89,0.08)] hover:text-[#5c5959] group-data-[collapsible=icon]:w-auto group-data-[collapsible=icon]:px-2"
                    @click="logout"
                >
                    <div class="flex size-[36px] shrink-0 items-center justify-center rounded-full bg-[#5c5959] text-white">
                        <LogOut class="size-5 shrink-0" />
                    </div>
                    <span class="sidebar-footer-label group-data-[collapsible=icon]:hidden">Déconnexion</span>
                </button>
            </div>
        </SidebarFooter>
    </Sidebar>
</template>
