<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/appearance';
import { type BreadcrumbItem } from '@/types';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Appearance settings',
        href: edit().url,
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Appearance settings" />

        <SettingsLayout>
            <div class="space-y-4">
                <h2 class="doc-title text-2xl font-semibold">
                    Appearance policy
                </h2>
                <p class="doc-subtle text-sm">
                    The interface theme is fixed for consistency across the
                    legal workspace. User-controlled light/dark switching is
                    disabled in this application.
                </p>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
