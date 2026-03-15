<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const appName = usePage().props.name ?? 'Docintern';
</script>

<template>
    <div
        class="flex min-h-screen items-center justify-center bg-background p-6 md:p-10"
    >
        <div class="w-full max-w-md space-y-6">
            <Link
                :href="home()"
                class="mx-auto flex w-fit items-center gap-3 rounded-full border border-[var(--doc-border)] bg-card px-4 py-2"
            >
                <span
                    class="flex size-8 items-center justify-center rounded-lg bg-[var(--doc-seal)] text-white"
                >
                    <AppLogoIcon class="size-4 fill-current" />
                </span>
                <span
                    class="doc-title text-base font-semibold text-foreground"
                >
                    {{ appName }}
                </span>
            </Link>

            <section class="workspace-panel p-6 sm:p-8">
                <header class="space-y-2 text-center">
                    <h1 class="doc-title text-2xl font-semibold">
                        {{ title }}
                    </h1>
                    <p class="doc-subtle text-sm">
                        {{ description }}
                    </p>
                </header>
                <div class="mt-6">
                    <slot />
                </div>
            </section>
        </div>
    </div>
</template>
