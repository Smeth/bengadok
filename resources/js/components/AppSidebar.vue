<script setup lang="ts">
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import {
    BarChart3,
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
import { usePage } from '@inertiajs/vue3';
import type { NavItem } from '@/types';
import AppLogo from './AppLogo.vue';
import { dashboard } from '@/routes';

const page = usePage();
const roles = computed(() => (page.props.auth as { user?: { roles?: string[] } })?.user?.roles ?? []);

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        { title: 'Tableau de bord', href: dashboard(), icon: LayoutGrid },
        { title: 'Commandes', href: '/commandes', icon: ClipboardList },
        { title: 'Pharmacies', href: '/pharmacies', icon: Building2 },
        { title: 'Médicaments', href: '/medicaments', icon: Package },
        { title: 'Clients', href: '/clients', icon: Users },
        { title: 'Utilisateurs Backoffice', href: '/utilisateurs', icon: UserCog },
        { title: 'Réglages', href: '/settings/profile', icon: Settings },
    ];
    if (roles.value.includes('vendeur')) {
        const idx = items.findIndex((i) => i.title === 'Commandes');
        items.splice(idx + 1, 0, { title: 'Dok Pharma', href: '/dok-pharma', icon: Store });
    }
    if (roles.value.includes('agent_call_center')) {
        items.splice(2, 0,
            { title: 'Nouvelle commande', href: '/agent/nouvelle-commande', icon: Package },
            { title: 'Mes réceptions', href: '/agent', icon: PhoneCall }
        );
    }
    return items;
});

function logout() {
    router.post('/logout');
}
</script>

<template>
    <Sidebar collapsible="icon" variant="inset" class="border-r border-sidebar-border">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <div class="mb-4 flex justify-center px-2">
                <div class="flex size-24 items-center justify-center rounded-2xl bg-sky-100 dark:bg-sky-900/40">
                    <BarChart3 class="size-10 text-sky-600 dark:text-sky-400" />
                </div>
            </div>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        class="text-muted-foreground hover:text-foreground"
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
