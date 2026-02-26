<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Bell, ChevronDown } from 'lucide-vue-next';
import { usePage } from '@inertiajs/vue3';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import AppLogo from './AppLogo.vue';
import UserMenuContent from './UserMenuContent.vue';
import { dashboard } from '@/routes';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();
const user = computed(() => (page.props.auth as { user?: { name: string; roles?: string[] } })?.user);

const { getInitials } = useInitials();
const roleLabel = computed(() => {
    const roles = user.value?.roles ?? [];
    if (roles.includes('gerant')) return 'Admin';
    if (roles.includes('vendeur')) return 'Vendeur';
    if (roles.includes('agent_call_center')) return 'Agent';
    return roles[0] ?? '-';
});
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center justify-between gap-4 border-b border-sidebar-border/70 bg-background/95 px-6 backdrop-blur supports-[backdrop-filter]:bg-background/60 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1 md:hidden" />
            <Link
                :href="dashboard()"
                class="hidden items-center gap-2 md:flex"
            >
                <div class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" />
                    </svg>
                </div>
                <span class="font-semibold text-sidebar-foreground">BengaDok</span>
            </Link>
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <div class="flex items-center gap-3">
            <Button variant="ghost" size="icon" class="relative">
                <Bell class="size-5" />
            </Button>
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
