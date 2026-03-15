<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import TenantContextController from '@/actions/App/Http/Controllers/Settings/TenantContextController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/tenant-context';
import type { BreadcrumbItem } from '@/types';

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
        href: edit(),
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Tenant context" />

        <h1 class="sr-only">Tenant context</h1>

        <SettingsLayout>
            <div class="space-y-8">
                <Heading
                    variant="small"
                    title="Tenant context"
                    description="Select the active tenant used for tenant-scoped pages in your super-admin session."
                />

                <Form
                    v-bind="TenantContextController.update.form()"
                    class="space-y-6"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <div class="grid gap-2">
                        <Label for="tenant_id">Active tenant</Label>
                        <select
                            id="tenant_id"
                            name="tenant_id"
                            required
                            :value="activeTenantId ?? ''"
                            class="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
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
                        <InputError class="mt-2" :message="errors.tenant_id" />
                    </div>

                    <div class="flex items-center gap-4">
                        <Button :disabled="processing">Save context</Button>

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-show="recentlySuccessful"
                                class="text-sm text-neutral-600"
                            >
                                Saved.
                            </p>
                        </Transition>
                    </div>
                </Form>

                <section class="rounded-xl border border-border/70 p-6">
                    <h2 class="text-lg font-semibold">Clear context</h2>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Remove the active tenant and return to neutral
                        super-admin mode.
                    </p>

                    <Form
                        v-bind="TenantContextController.destroy.form()"
                        class="mt-4"
                        v-slot="{ processing }"
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
