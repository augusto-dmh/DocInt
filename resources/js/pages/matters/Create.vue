<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import MatterController from '@/actions/App/Http/Controllers/MatterController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

const selectClass =
    'border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50';

defineProps<{
    clients: { id: number; name: string }[];
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Matters',
        href: MatterController.index.url(),
    },
    {
        title: 'New Matter',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="New Matter" />

        <div
            class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 rounded-xl p-4"
        >
            <div class="space-y-2">
                <h1 class="text-2xl font-semibold tracking-tight">
                    New Matter
                </h1>
                <p class="text-sm text-muted-foreground">
                    Open a new matter and connect it to an existing tenant
                    client.
                </p>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 p-6">
                <Form
                    v-bind="MatterController.store.form()"
                    v-slot="{ errors, processing }"
                    class="space-y-6"
                >
                    <div class="grid gap-2">
                        <Label for="client_id">Client</Label>
                        <select
                            id="client_id"
                            name="client_id"
                            required
                            :class="selectClass"
                        >
                            <option value="">Select a client</option>
                            <option
                                v-for="client in clients"
                                :key="client.id"
                                :value="client.id"
                            >
                                {{ client.name }}
                            </option>
                        </select>
                        <InputError :message="errors.client_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="title">Title</Label>
                        <Input
                            id="title"
                            name="title"
                            required
                            placeholder="Matter title"
                        />
                        <InputError :message="errors.title" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="reference_number"
                                >Reference Number</Label
                            >
                            <Input
                                id="reference_number"
                                name="reference_number"
                                placeholder="Internal reference number"
                            />
                            <InputError :message="errors.reference_number" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="status">Status</Label>
                            <select
                                id="status"
                                name="status"
                                required
                                :class="selectClass"
                            >
                                <option value="open">Open</option>
                                <option value="closed">Closed</option>
                                <option value="on_hold">On Hold</option>
                            </select>
                            <InputError :message="errors.status" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="description">Description</Label>
                        <Textarea
                            id="description"
                            name="description"
                            rows="4"
                            placeholder="Matter summary and context"
                        />
                        <InputError :message="errors.description" />
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <Button :disabled="processing">Create Matter</Button>
                        <Button as-child variant="outline">
                            <Link :href="MatterController.index()">Cancel</Link>
                        </Button>
                    </div>
                </Form>
            </div>
        </div>
    </AppLayout>
</template>
