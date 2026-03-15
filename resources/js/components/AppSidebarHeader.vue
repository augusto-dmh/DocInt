<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

const props = withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();

const currentTitle = computed(() => {
    if (!props.breadcrumbs.length) {
        return 'Workspace';
    }

    return props.breadcrumbs[props.breadcrumbs.length - 1].title;
});
</script>

<template>
    <header
        class="workspace-topbar flex min-h-16 shrink-0 items-center gap-3 border-b border-[var(--doc-border)]/65 px-4 py-3 transition-[width,height] ease-linear md:px-6"
    >
        <SidebarTrigger class="-ml-1 rounded-lg hover:bg-accent" />

        <div class="min-w-0 flex-1">
            <p class="doc-title truncate text-lg font-semibold">
                {{ currentTitle }}
            </p>
            <template v-if="breadcrumbs && breadcrumbs.length > 1">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <p
            v-if="page.props.tenant"
            class="doc-subtle hidden rounded-full border border-[var(--doc-border)] px-3 py-1 text-[11px] tracking-[0.12em] uppercase sm:inline-flex"
        >
            {{ page.props.tenant.slug }}
        </p>
    </header>
</template>
