<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import ClientController from '@/actions/App/Http/Controllers/ClientController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Clients',
        href: ClientController.index.url(),
    },
    {
        title: 'New Client',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="New Client" />

        <section class="workspace-hero p-6 sm:p-8">
            <p
                class="doc-seal text-xs font-semibold tracking-[0.16em] uppercase"
            >
                Relationship intake
            </p>
            <h1 class="doc-title mt-2 text-3xl font-semibold">Create client</h1>
            <p class="doc-subtle mt-3 text-sm">
                Start with reliable contact data so matter and document routing
                stays consistent.
            </p>
        </section>

        <section
            class="workspace-panel workspace-fade-up workspace-delay-1 mt-6 p-6 sm:p-8"
        >
            <Form
                v-bind="ClientController.store.form()"
                class="grid gap-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="name" class="workspace-label">Name</Label>
                    <Input
                        id="name"
                        name="name"
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
                    />
                    <InputError :message="errors.notes" />
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <Button
                        :disabled="processing"
                        class="workspace-primary-button"
                    >
                        Create client
                    </Button>
                    <Button as-child variant="outline">
                        <Link :href="ClientController.index()">Cancel</Link>
                    </Button>
                </div>
            </Form>
        </section>
    </AppLayout>
</template>
