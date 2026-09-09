<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

const props = defineProps<{
    items: NavItem[];
}>();

const { currentUrl } = useCurrentUrl();

const activeColorClass = 'bg-[#22c55e]';

function navPath(href: NavItem['href']): string {
    const urlString = toUrl(href);

    if (urlString.startsWith('http')) {
        try {
            return new URL(urlString).pathname;
        } catch {
            return urlString.split('?')[0] ?? urlString;
        }
    }

    return urlString.split('?')[0] ?? urlString;
}

/** Sous-pages (ex. /medicaments/doublons) : item parent le plus spécifique actif. */
const activeNavPath = computed(() => {
    const path = currentUrl.value;
    let best: string | null = null;
    let bestLen = -1;

    for (const item of props.items) {
        const itemPath = navPath(item.href);
        const exact = itemPath === path;
        const prefix = path.startsWith(`${itemPath}/`);

        if ((exact || prefix) && itemPath.length > bestLen) {
            bestLen = itemPath.length;
            best = itemPath;
        }
    }

    return best;
});

function isItemActive(item: NavItem): boolean {
    return navPath(item.href) === activeNavPath.value;
}
</script>

<template>
    <SidebarGroup class="shrink-0 px-0 py-0">
        <SidebarMenu class="flex flex-col gap-1">
            <SidebarMenuItem
                v-for="item in items"
                :key="item.title"
                class="list-none"
            >
                <Link
                    :href="item.href"
                    class="sidebar-menu-btn-react group flex h-11 w-full cursor-pointer items-center gap-3 rounded-[10px] px-3 transition-all"
                    :class="
                        isItemActive(item)
                            ? `${activeColorClass} text-white`
                            : 'bg-transparent'
                    "
                    :data-active="isItemActive(item) ? 'true' : undefined"
                >
                    <div
                        class="sidebar-menu-icon flex shrink-0 items-center justify-center rounded-full transition-colors"
                        :class="
                            isItemActive(item)
                                ? 'bg-white/25 text-white'
                                : ''
                        "
                    >
                        <component
                            :is="item.icon"
                            class="sidebar-menu-icon-svg size-5"
                            stroke-width="1.5"
                        />
                    </div>
                    <span class="sidebar-menu-label leading-snug">
                        {{ item.title }}
                    </span>
                </Link>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
