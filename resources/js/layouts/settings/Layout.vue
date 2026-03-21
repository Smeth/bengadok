<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Key, Palette, RotateCcw, Shield, ShieldCheck, User } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { usePage } from '@inertiajs/vue3';
import { toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { show } from '@/routes/two-factor';
import { edit as editPassword } from '@/routes/user-password';

const page = usePage();
const isSuperAdmin = computed(() =>
    (page.props.auth as { user?: { roles?: string[] } })?.user?.roles?.includes('super_admin') ?? false
);

const sidebarNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        { title: 'Profil', href: editProfile(), icon: User },
        { title: 'Mot de passe', href: editPassword(), icon: Key },
        { title: 'Authentification 2FA', href: show(), icon: Shield },
        { title: 'Apparence', href: editAppearance(), icon: Palette },
    ];
    if (isSuperAdmin.value) {
        items.push({ title: 'Gestion des rôles', href: '/settings/roles', icon: ShieldCheck });
        items.push({ title: 'Réinitialiser l\'application', href: '/settings/reset', icon: RotateCcw });
    }
    return items;
});

const { isCurrentUrl } = useCurrentUrl();
</script>

<template>
    <div class="px-4 py-6">
        <Heading
            title="Mon profil"
            description="Gérez votre profil et les paramètres de votre compte"
        />

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <aside class="w-full max-w-xl lg:w-48">
                <nav
                    class="flex flex-col space-y-1 space-x-0"
                    aria-label="Mon profil"
                >
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        variant="ghost"
                        :class="[
                            'w-full justify-start',
                            { 'bg-muted': isCurrentUrl(item.href) },
                        ]"
                        as-child
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" class="h-4 w-4" />
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div class="min-w-0 flex-1">
                <section class="max-w-6xl space-y-12">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
