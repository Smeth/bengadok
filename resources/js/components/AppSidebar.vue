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
    Store,
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

const page = usePage();
const roles = computed(() => (page.props.auth as { user?: { roles?: string[] } })?.user?.roles ?? []);

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [];
    const isAdmin = roles.value.includes('admin') || roles.value.includes('super_admin');
    const isGerant = roles.value.includes('gerant');
    const isAgent = roles.value.includes('agent_call_center');
    const isVendeur = roles.value.includes('vendeur');

    items.push({ title: 'Tableau de bord', href: dashboard(), icon: LayoutGrid });

    if (isAgent || isAdmin) {
        items.push({ title: 'Nouvelle commande', href: '/agent/nouvelle-commande', icon: Package });
        items.push({ title: 'Mes réceptions', href: '/agent', icon: PhoneCall });
        items.push({ title: 'Clients', href: '/clients', icon: Users });
    }

    if (isAdmin) {
        items.push({ title: 'Commandes', href: '/commandes', icon: ClipboardList });
        items.push({ title: 'Pharmacies', href: '/pharmacies', icon: Building2 });
        items.push({ title: 'Médicaments', href: '/medicaments', icon: Package });
        items.push({ title: 'Utilisateurs Backoffice', href: '/utilisateurs', icon: UserCog });
    }

    if (isGerant || isVendeur) {
        items.push({ title: 'Dok Pharma', href: '/dok-pharma', icon: Store });
    }
    if (isGerant) {
        items.push({ title: 'Historique pharmacie', href: '/commandes', icon: ClipboardList });
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

    items.push({ title: 'Réglages', href: '/settings/profile', icon: Settings });
    return items;
});

function logout() {
    router.post('/logout');
}
</script>

<template>
    <Sidebar collapsible="icon" variant="inset" class="border-r border-sidebar-border bg-white">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem class="flex items-center gap-1">
                    <SidebarMenuButton size="lg" as-child class="flex-1 min-w-0">
                        <Link :href="dashboard()" class="flex w-full items-center">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                    <SidebarTrigger
                        class="shrink-0 text-slate-400 hover:bg-transparent hover:text-slate-600"
                        aria-label="Réduire la barre latérale"
                    />
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <div class="mb-4 flex justify-center px-1">
                <img
                    src="/images/sidebar-illustration.png"
                    alt="Professionnel de santé au travail"
                    class="max-h-32 w-full object-contain object-bottom"
                />
            </div>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        class="text-slate-600 hover:bg-transparent hover:text-slate-800"
                        @click="logout"
                    >
                        <LogOut class="size-4" />
                        <span>Déconnexion</span>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
