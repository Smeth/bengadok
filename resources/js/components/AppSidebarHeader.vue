<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import { Bell, ChevronDown } from 'lucide-vue-next';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { useInitials } from '@/composables/useInitials';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import UserMenuContent from './UserMenuContent.vue';

const props = withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
        variant?: 'default' | 'gradient';
        /** Classes additionnelles sur le header (z-index, etc.) */
        headerClass?: string;
    }>(),
    {
        breadcrumbs: () => [],
        variant: 'default',
        headerClass: '',
    },
);

interface NotificationItem {
    id: number;
    numero: string;
    status_label: string;
    client?: { nom: string; prenom?: string };
    pharmacie?: { designation: string };
    url: string;
    created_at: string;
}

const page = usePage();
const user = computed(() => (page.props.auth as { user?: { name: string; roles?: string[] } })?.user);
const notifications = computed(() => {
    const n = (page.props as { notifications?: { count: number; items: NotificationItem[] } }).notifications;
    return n ?? { count: 0, items: [] };
});

const { getInitials } = useInitials();
const roleLabel = computed(() => {
    const roles = user.value?.roles ?? [];
    if (roles.includes('super_admin')) return 'Super Admin';
    if (roles.includes('admin')) return 'Admin';
    if (roles.includes('gerant')) return 'Gérant pharmacie';
    if (roles.includes('vendeur')) return 'Vendeur';
    if (roles.includes('agent_call_center')) return 'Agent';
    return roles[0] ?? '-';
});

const formatClientName = (client?: { nom: string; prenom?: string } | null) => {
    if (!client) return '-';
    return [client.nom, client.prenom].filter(Boolean).join(' ') || '-';
};

const formatDate = (iso?: string) => {
    if (!iso) return '';
    try {
        const d = new Date(iso);
        return d.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
    } catch {
        return '';
    }
};
</script>

<template>
    <header
        :class="
            cn(
                'flex h-16 shrink-0 items-center justify-between gap-4 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4',
                props.variant === 'gradient'
                    ? 'border-b-0 bg-transparent'
                    : 'border-b border-sidebar-border/70 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60',
                props.headerClass,
            )
        "
    >
        <div class="flex items-center gap-2" :class="props.variant === 'gradient' ? 'text-white' : ''">
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <div class="flex items-center gap-3" :class="props.variant === 'gradient' ? 'text-white [&_button]:text-white [&_button]:hover:bg-white/20' : ''">
            <DropdownMenu v-if="user">
                <DropdownMenuTrigger as-child>
                    <Button variant="ghost" size="icon" class="relative">
                        <Bell class="size-5" />
                        <span
                            v-if="notifications.count > 0"
                            class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-medium text-primary-foreground"
                        >
                            {{ notifications.count > 99 ? '99+' : notifications.count }}
                        </span>
                    </Button>
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
                                {{ formatClientName(item.client) }} · {{ item.status_label }}
                            </div>
                            <div v-if="item.pharmacie" class="mt-0.5 text-xs text-muted-foreground">
                                {{ item.pharmacie.designation }}
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
                        href="/commandes"
                        class="block px-2 py-2 text-center text-sm font-medium text-primary hover:bg-accent"
                    >
                        Voir toutes les commandes
                    </Link>
                </DropdownMenuContent>
            </DropdownMenu>
            <DropdownMenu v-if="user">
                <DropdownMenuTrigger as-child>
                    <Button variant="ghost" class="flex items-center gap-2 px-2 py-1.5">
                        <Avatar class="size-8 shrink-0">
                            <AvatarImage v-if="user.avatar" :src="user.avatar" :alt="user.name" />
                            <AvatarFallback class="text-xs">
                                {{ getInitials(user.name) }}
                            </AvatarFallback>
                        </Avatar>
                        <div class="hidden flex-col items-start text-left sm:block">
                            <span class="block text-sm font-medium leading-tight">{{ user.name }}</span>
                            <span class="block text-xs text-muted-foreground leading-tight">{{ roleLabel }}</span>
                        </div>
                        <ChevronDown class="size-4 shrink-0 opacity-50" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-56">
                    <UserMenuContent :user="user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </header>
</template>
