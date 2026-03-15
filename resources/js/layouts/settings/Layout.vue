<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editProfile } from '@/routes/profile';
import { edit as editTenantContext } from '@/routes/tenant-context';
import { show } from '@/routes/two-factor';
import { edit as editPassword } from '@/routes/user-password';
import { type NavItem } from '@/types';

const page = usePage();
const sidebarNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'Profile',
            href: editProfile(),
        },
        {
            title: 'Password',
            href: editPassword(),
        },
        {
            title: 'Two-Factor Auth',
            href: show(),
        },
    ];

    if (page.props.auth.isSuperAdmin) {
        items.push({
            title: 'Tenant Context',
            href: editTenantContext(),
        });
    }

    return items;
});

const { isCurrentUrl } = useCurrentUrl();
</script>

<template>
    <div class="space-y-8">
        <header class="workspace-hero workspace-fade-up p-6 sm:p-8">
            <p
                class="doc-seal text-xs font-semibold tracking-[0.16em] uppercase"
            >
                Security controls
            </p>
            <h1 class="doc-title mt-2 text-3xl font-semibold">
                Account settings
            </h1>
            <p class="doc-subtle mt-3 max-w-3xl text-sm sm:text-base">
                Manage credentials, identity data, and tenant context from one
                secured workspace.
            </p>
        </header>

        <div class="grid gap-6 lg:grid-cols-[220px_minmax(0,1fr)]">
            <aside class="workspace-panel workspace-fade-up p-3">
                <nav class="flex flex-col gap-1.5" aria-label="Settings">
                    <Link
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        :href="item.href"
                        class="rounded-xl px-3 py-2.5 text-sm font-medium transition"
                        :class="
                            isCurrentUrl(item.href)
                                ? 'bg-[var(--doc-seal)]/12 text-[var(--doc-seal)]'
                                : 'text-sidebar-foreground hover:bg-muted'
                        "
                    >
                        {{ item.title }}
                    </Link>
                </nav>
            </aside>

            <section
                class="workspace-panel workspace-fade-up workspace-delay-1 p-6 sm:p-8"
            >
                <slot />
            </section>
        </div>
    </div>
</template>
