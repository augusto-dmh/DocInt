<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import ClientController from '@/actions/App/Http/Controllers/ClientController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Client } from '@/types';

const textareaClass =
    'border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-28 w-full rounded-md border px-3 py-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50';

const props = defineProps<{
    client: Client;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Clients',
        href: ClientController.index.url(),
    },
    {
        title: props.client.name,
        href: ClientController.show.url(props.client),
    },
    {
        title: 'Edit',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Edit ${client.name}`" />

        <div
            class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 rounded-xl p-4"
        >
            <div class="space-y-2">
                <h1 class="text-2xl font-semibold tracking-tight">
                    Edit Client
                </h1>
                <p class="text-sm text-muted-foreground">
                    Update contact details and tenant-specific notes for this
                    client.
                </p>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 p-6">
                <Form
                    v-bind="ClientController.update.form(client)"
                    v-slot="{ errors, processing, recentlySuccessful }"
                    class="space-y-6"
                >
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            required
                            :default-value="client.name"
                            placeholder="Client name"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="email">Email</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                :default-value="client.email ?? ''"
                                placeholder="Email address"
                            />
                            <InputError :message="errors.email" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="phone">Phone</Label>
                            <Input
                                id="phone"
                                name="phone"
                                :default-value="client.phone ?? ''"
                                placeholder="Phone number"
                            />
                            <InputError :message="errors.phone" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="company">Company</Label>
                        <Input
                            id="company"
                            name="company"
                            :default-value="client.company ?? ''"
                            placeholder="Company name"
                        />
                        <InputError :message="errors.company" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="notes">Notes</Label>
                        <textarea
                            id="notes"
                            name="notes"
                            rows="4"
                            :class="textareaClass"
                            :value="client.notes ?? ''"
                            placeholder="Important context for this client"
                        />
                        <InputError :message="errors.notes" />
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <Button :disabled="processing">Save Changes</Button>
                        <Button as-child variant="outline">
                            <Link :href="ClientController.show(client)"
                                >Cancel</Link
                            >
                        </Button>
                        <p
                            v-if="recentlySuccessful"
                            class="text-sm text-muted-foreground"
                        >
                            Saved.
                        </p>
                    </div>
                </Form>
            </div>

            <div class="rounded-xl border border-destructive/30 p-6">
                <h2 class="text-lg font-semibold text-destructive">
                    Delete Client
                </h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    This permanently removes the client record from the active
                    tenant.
                </p>

                <Form
                    v-bind="ClientController.destroy.form(client)"
                    v-slot="{ processing }"
                    class="mt-4"
                >
                    <Button variant="destructive" :disabled="processing"
                        >Delete Client</Button
                    >
                </Form>
            </div>
        </div>
    </AppLayout>
</template>
