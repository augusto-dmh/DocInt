<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import MatterController from '@/actions/App/Http/Controllers/MatterController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Matter } from '@/types';

type Props = {
    matter: Matter;
    clients: { id: number; name: string }[];
};

const props = defineProps<Props>();

const canDeleteMatters =
    usePage().props.auth.permissions.includes('delete matters');

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Matters',
        href: MatterController.index.url(),
    },
    {
        title: props.matter.title,
        href: MatterController.show.url(props.matter),
    },
    {
        title: 'Edit',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Edit ${matter.title}`" />

        <section class="workspace-hero p-6 sm:p-8">
            <p
                class="doc-seal text-xs font-semibold tracking-[0.16em] uppercase"
            >
                Matter maintenance
            </p>
            <h1 class="doc-title mt-2 text-3xl font-semibold">Edit matter</h1>
            <p class="doc-subtle mt-3 text-sm">
                Keep status, ownership context, and reference data accurate.
            </p>
        </section>

        <section
            class="workspace-panel workspace-fade-up workspace-delay-1 mt-6 p-6 sm:p-8"
        >
            <Form
                v-bind="MatterController.update.form(matter)"
                class="grid gap-6"
                v-slot="{ errors, processing, recentlySuccessful }"
            >
                <div class="grid gap-2">
                    <Label for="client_id" class="workspace-label"
                        >Client</Label
                    >
                    <select
                        id="client_id"
                        name="client_id"
                        required
                        :value="matter.client_id"
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
                        :default-value="matter.title"
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
                            :default-value="matter.reference_number ?? ''"
                            class="workspace-input"
                            placeholder="e.g. REF-001"
                        />
                        <InputError :message="errors.reference_number" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="status" class="workspace-label"
                            >Status</Label
                        >
                        <select
                            id="status"
                            name="status"
                            required
                            :value="matter.status"
                            class="workspace-select"
                        >
                            <option value="open">Open</option>
                            <option value="closed">Closed</option>
                            <option value="on_hold">On Hold</option>
                        </select>
                        <InputError :message="errors.status" />
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
                        :value="matter.description ?? ''"
                    />
                    <InputError :message="errors.description" />
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <Button
                        :disabled="processing"
                        class="workspace-primary-button"
                    >
                        Save changes
                    </Button>
                    <Button as-child variant="outline">
                        <Link :href="MatterController.show(matter)"
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
            v-if="canDeleteMatters"
            class="workspace-panel workspace-fade-up workspace-delay-2 mt-6 border-[hsl(3_68%_50%/0.35)] p-6"
        >
            <h2 class="doc-title text-xl font-semibold text-destructive">
                Remove matter
            </h2>
            <p class="doc-subtle mt-2 text-sm">
                Removing a matter deletes its linked records.
            </p>
            <Form
                v-bind="MatterController.destroy.form(matter)"
                v-slot="{ processing }"
                class="mt-4"
            >
                <Button variant="destructive" :disabled="processing">
                    Delete matter
                </Button>
            </Form>
        </section>
    </AppLayout>
</template>
