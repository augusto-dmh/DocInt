<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const appName = usePage().props.name;
const tenant = usePage().props.tenant;
</script>

<template>
    <div
        class="flex aspect-square size-9 items-center justify-center rounded-xl border border-[var(--doc-border)] bg-[var(--doc-paper)] text-[var(--doc-seal)] shadow-sm"
    >
        <img
            v-if="tenant?.logo_url"
            :src="tenant.logo_url"
            :alt="tenant.name"
            class="size-6 rounded-md object-cover"
        />
        <AppLogoIcon
            v-else
            class="size-5 fill-current text-[var(--doc-seal)]"
        />
    </div>
    <div class="ml-2 grid flex-1 text-left text-sm leading-tight">
        <span
            class="doc-title mb-0.5 truncate text-sm font-semibold text-foreground"
        >
            {{ tenant?.name ?? appName ?? 'Docintern' }}
        </span>
        <span
            class="doc-subtle truncate text-[11px] tracking-[0.12em] uppercase"
        >
            {{ tenant?.slug ?? 'Workspace' }}
        </span>
    </div>
</template>
