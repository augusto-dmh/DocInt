<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import ClientController from '@/actions/App/Http/Controllers/ClientController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Client } from '@/types';

type Props = {
    client: Client;
};

const props = defineProps<Props>();

const canDeleteClients =
    usePage().props.auth.permissions.includes('delete clients');

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

        <section class="workspace-hero p-6 sm:p-8">
            <p
                class="doc-seal text-xs font-semibold tracking-[0.16em] uppercase"
            >
                Relationship maintenance
            </p>
            <h1 class="doc-title mt-2 text-3xl font-semibold">Edit client</h1>
            <p class="doc-subtle mt-3 text-sm">
                Keep records current to reduce routing errors in matter and
                document flows.
            </p>
        </section>

        <section
            class="workspace-panel workspace-fade-up workspace-delay-1 mt-6 p-6 sm:p-8"
        >
            <Form
                v-bind="ClientController.update.form(client)"
                class="grid gap-6"
                v-slot="{ errors, processing, recentlySuccessful }"
            >
                <div class="grid gap-2">
                    <Label for="name" class="workspace-label">Name</Label>
                    <Input
                        id="name"
                        name="name"
                        :default-value="client.name"
                        required
                        class="workspace-input"
                        placeholder="Client name"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2 sm:grid-cols-2 sm:gap-4">
                    <div class="grid gap-2">
                        <Label for="email" class="workspace-label">Email</Label>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            :default-value="client.email ?? ''"
                            class="workspace-input"
                            placeholder="Email address"
                        />
                        <InputError :message="errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="phone" class="workspace-label">Phone</Label>
                        <Input
                            id="phone"
                            name="phone"
                            :default-value="client.phone ?? ''"
                            class="workspace-input"
                            placeholder="Phone number"
                        />
                        <InputError :message="errors.phone" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="company" class="workspace-label">Company</Label>
                    <Input
                        id="company"
                        name="company"
                        :default-value="client.company ?? ''"
                        class="workspace-input"
                        placeholder="Company name"
                    />
                    <InputError :message="errors.company" />
                </div>

                <div class="grid gap-2">
                    <Label for="notes" class="workspace-label">Notes</Label>
                    <textarea
                        id="notes"
                        name="notes"
                        rows="4"
                        class="workspace-textarea"
                        placeholder="Additional notes"
                        :value="client.notes ?? ''"
                    />
                    <InputError :message="errors.notes" />
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <Button
                        :disabled="processing"
                        class="workspace-primary-button"
                    >
                        Save changes
                    </Button>
                    <Button as-child variant="outline">
                        <Link :href="ClientController.show(client)"
                            >Cancel</Link
                        >
                    </Button>
                    <p v-if="recentlySuccessful" class="doc-subtle text-sm">
                        Saved.
                    </p>
                </div>
            </Form>
        </section>

        <section
            v-if="canDeleteClients"
            class="workspace-panel workspace-fade-up workspace-delay-2 mt-6 border-[hsl(3_68%_50%/0.35)] p-6"
        >
            <h2 class="doc-title text-xl font-semibold text-destructive">
                Remove client
            </h2>
            <p class="doc-subtle mt-2 text-sm">
                This action permanently deletes the client record.
            </p>
            <Form
                v-bind="ClientController.destroy.form(client)"
                v-slot="{ processing }"
                class="mt-4"
            >
                <Button variant="destructive" :disabled="processing">
                    Delete client
                </Button>
            </Form>
        </section>
    </AppLayout>
</template>
