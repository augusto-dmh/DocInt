<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import MatterController from '@/actions/App/Http/Controllers/MatterController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

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

        <section class="workspace-hero p-6 sm:p-8">
            <p
                class="doc-seal text-xs font-semibold tracking-[0.16em] uppercase"
            >
                Matter intake
            </p>
            <h1 class="doc-title mt-2 text-3xl font-semibold">Create matter</h1>
            <p class="doc-subtle mt-3 text-sm">
                Open a new case record and anchor future document activity.
            </p>
        </section>

        <section
            class="workspace-panel workspace-fade-up workspace-delay-1 mt-6 p-6 sm:p-8"
        >
            <Form
                v-bind="MatterController.store.form()"
                class="grid gap-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="client_id" class="workspace-label"
                        >Client</Label
                    >
                    <select
                        id="client_id"
                        name="client_id"
                        required
                        class="workspace-select"
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
                    <Label for="title" class="workspace-label">Title</Label>
                    <Input
                        id="title"
                        name="title"
                        required
                        class="workspace-input"
                        placeholder="Matter title"
                    />
                    <InputError :message="errors.title" />
                </div>

                <div class="grid gap-2 sm:grid-cols-2 sm:gap-4">
                    <div class="grid gap-2">
                        <Label for="reference_number" class="workspace-label">
                            Reference Number
                        </Label>
                        <Input
                            id="reference_number"
                            name="reference_number"
                            class="workspace-input"
                            placeholder="e.g. REF-001"
                        />
                        <InputError :message="errors.reference_number" />
                    </div>
                    <div class="grid gap-2">
                        <Label class="workspace-label">Initial Status</Label>
                        <Input value="open" disabled class="workspace-input" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="description" class="workspace-label"
                        >Description</Label
                    >
                    <textarea
                        id="description"
                        name="description"
                        rows="4"
                        class="workspace-textarea"
                        placeholder="Matter description"
                    />
                    <InputError :message="errors.description" />
                </div>

                <input type="hidden" name="status" value="open" />

                <div class="flex flex-wrap items-center gap-3">
                    <Button
                        :disabled="processing"
                        class="workspace-primary-button"
                    >
                        Create matter
                    </Button>
                    <Button as-child variant="outline">
                        <Link :href="MatterController.index()">Cancel</Link>
                    </Button>
                </div>
            </Form>
        </section>
    </AppLayout>
</template>
