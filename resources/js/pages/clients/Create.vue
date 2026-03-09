<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import ClientController from '@/actions/App/Http/Controllers/ClientController';

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

        <div
            class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 rounded-xl p-4"
        >
            <div class="space-y-2">
                <h1 class="text-2xl font-semibold tracking-tight">
                    New Client
                </h1>
                <p class="text-sm text-muted-foreground">
                    Create a tenant-scoped client profile before opening matters
                    or linking documents.
                </p>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 p-6">
                <Form
                    v-bind="ClientController.store.form()"
                    v-slot="{ errors, processing }"
                    class="space-y-6"
                >
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            required
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
                                placeholder="Email address"
                            />
                            <InputError :message="errors.email" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="phone">Phone</Label>
                            <Input
                                id="phone"
                                name="phone"
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
                            placeholder="Company name"
                        />
                        <InputError :message="errors.company" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="notes">Notes</Label>
                        <Textarea
                            id="notes"
                            name="notes"
                            rows="4"
                            placeholder="Important context for this client"
                        />
                        <InputError :message="errors.notes" />
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <Button :disabled="processing">Create Client</Button>
                        <Button as-child variant="outline">
                            <Link :href="ClientController.index()">Cancel</Link>
                        </Button>
                    </div>
                </Form>
            </div>
        </div>
    </AppLayout>
</template>
