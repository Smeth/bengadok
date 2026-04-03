<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

defineProps<{
    items: NavItem[];
}>();

const { isCurrentUrl } = useCurrentUrl();

const activeColorClass = 'bg-[#22c55e]';
</script>

<template>
    <SidebarGroup class="shrink-0 px-0 py-0">
        <SidebarMenu class="flex flex-col gap-1">
            <SidebarMenuItem v-for="item in items" :key="item.title" class="list-none">
                <Link
                    :href="item.href"
                    class="sidebar-menu-btn-react group flex h-11 w-full cursor-pointer items-center gap-3 rounded-[10px] px-3 transition-all"
                    :class="isCurrentUrl(item.href)
                        ? `${activeColorClass} text-white`
                        : 'bg-transparent'"
                    :data-active="isCurrentUrl(item.href) ? 'true' : undefined"
                >
                    <div
                        class="sidebar-menu-icon flex shrink-0 items-center justify-center rounded-full transition-colors"
                        :class="isCurrentUrl(item.href) ? 'bg-white/25 text-white' : ''"
                    >
                        <component :is="item.icon" class="sidebar-menu-icon-svg size-5" stroke-width="1.5" />
                    </div>
                    <span class="sidebar-menu-label leading-snug">
                        {{ item.title }}
                    </span>
                </Link>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
