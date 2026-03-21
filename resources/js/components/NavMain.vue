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

// Couleur active style Figma (vert #5BB66E)
const activeColorClass = 'bg-[#5BB66E]';
</script>

<template>
    <SidebarGroup class="px-2 py-2">
        <SidebarMenu class="flex flex-col gap-1">
            <SidebarMenuItem v-for="item in items" :key="item.title" class="list-none">
                <Link
                    :href="item.href"
                    class="sidebar-menu-btn-react group flex w-full items-center gap-3 rounded-xl px-3 py-2.5 transition-all duration-150"
                    :class="isCurrentUrl(item.href)
                        ? `${activeColorClass} text-white shadow-sm font-bold`
                        : 'bg-transparent hover:bg-[rgba(92,89,89,0.08)]'"
                    :data-active="isCurrentUrl(item.href) ? 'true' : undefined"
                >
                    <!-- Icône style Figma : 36px, bg rgba(92,89,89,0.25) -->
                    <div
                        class="sidebar-menu-icon flex size-[36px] shrink-0 items-center justify-center rounded-full transition-colors"
                        :class="isCurrentUrl(item.href)
                            ? 'bg-white/25 text-white'
                            : 'bg-[rgba(92,89,89,0.25)] group-hover:bg-[rgba(92,89,89,0.35)]'"
                    >
                        <component :is="item.icon" class="sidebar-menu-icon-svg size-6" />
                    </div>

                    <!-- Label : 14px, une seule ligne -->
                    <span class="sidebar-menu-label text-[14px] font-bold leading-tight text-[#5c5959] transition-colors truncate"
                        :class="isCurrentUrl(item.href) ? '!text-white' : ''"
                    >
                        {{ item.title }}
                    </span>
                </Link>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
