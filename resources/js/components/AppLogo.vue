<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const page = usePage();
const displayName = computed(
    () => page.props.tenant?.name ?? page.props.name ?? 'Docintern',
);
</script>

<template>
    <div class="flex items-center gap-2">
        <div
            class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
        >
            <AppLogoIcon
                class="size-5 fill-current text-white dark:text-black"
            />
        </div>
        <div class="grid flex-1 text-left text-sm">
            <span class="mb-0.5 truncate leading-tight font-semibold">
                {{ displayName }}
            </span>
        </div>
    </div>
</template>
