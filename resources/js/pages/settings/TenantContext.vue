<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import TenantContextController from '@/actions/App/Http/Controllers/Settings/TenantContextController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/tenant-context';
import { type BreadcrumbItem } from '@/types';

type TenantOption = {
    id: string;
    name: string;
    slug: string;
};

defineProps<{
    tenants: TenantOption[];
    activeTenantId: string | null;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Tenant context',
        href: edit().url,
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Tenant context" />

        <SettingsLayout>
            <div class="space-y-6">
                <header>
                    <h2 class="doc-title text-2xl font-semibold">
                        Tenant context
                    </h2>
                    <p class="doc-subtle mt-2 text-sm">
                        Super-admin sessions must select an active tenant before
                        opening tenant-scoped pages.
                    </p>
                </header>

                <Form
                    v-bind="TenantContextController.update.form()"
                    class="grid gap-6"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <div class="grid gap-2">
                        <Label for="tenant_id" class="workspace-label"
                            >Active tenant</Label
                        >
                        <select
                            id="tenant_id"
                            name="tenant_id"
                            required
                            :value="activeTenantId ?? ''"
                            class="workspace-select"
                        >
                            <option value="" disabled>Select a tenant</option>
                            <option
                                v-for="tenant in tenants"
                                :key="tenant.id"
                                :value="tenant.id"
                            >
                                {{ tenant.name }} ({{ tenant.slug }})
                            </option>
                        </select>
                        <InputError :message="errors.tenant_id" />
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <Button
                            :disabled="processing"
                            class="workspace-primary-button"
                        >
                            Save context
                        </Button>
                        <p v-if="recentlySuccessful" class="doc-subtle text-sm">
                            Saved.
                        </p>
                    </div>
                </Form>

                <section
                    class="workspace-panel border-[var(--doc-border)]/70 p-5"
                >
                    <h3 class="doc-title text-lg font-semibold">
                        Clear context
                    </h3>
                    <p class="doc-subtle mt-2 text-sm">
                        Remove the selected tenant to return to neutral
                        super-admin mode.
                    </p>
                    <Form
                        v-bind="TenantContextController.destroy.form()"
                        v-slot="{ processing }"
                        class="mt-4"
                    >
                        <Button
                            variant="outline"
                            :disabled="processing || !activeTenantId"
                        >
                            Clear context
                        </Button>
                    </Form>
                </section>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
