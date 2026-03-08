<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import MatterController from '@/actions/App/Http/Controllers/MatterController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Matter } from '@/types';

const textareaClass =
    'border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex min-h-28 w-full rounded-md border px-3 py-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50';

const selectClass =
    'border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50';

const props = defineProps<{
    matter: Matter;
    clients: { id: number; name: string }[];
}>();

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

        <div
            class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 rounded-xl p-4"
        >
            <div class="space-y-2">
                <h1 class="text-2xl font-semibold tracking-tight">
                    Edit Matter
                </h1>
                <p class="text-sm text-muted-foreground">
                    Update the client association, status, and context for this
                    matter.
                </p>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 p-6">
                <Form
                    v-bind="MatterController.update.form(matter)"
                    v-slot="{ errors, processing, recentlySuccessful }"
                    class="space-y-6"
                >
                    <div class="grid gap-2">
                        <Label for="client_id">Client</Label>
                        <select
                            id="client_id"
                            name="client_id"
                            required
                            :class="selectClass"
                            :value="matter.client_id"
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
                            :default-value="matter.title"
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
                                :default-value="matter.reference_number ?? ''"
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
                                :value="matter.status"
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
                        <textarea
                            id="description"
                            name="description"
                            rows="4"
                            :class="textareaClass"
                            :value="matter.description ?? ''"
                            placeholder="Matter summary and context"
                        />
                        <InputError :message="errors.description" />
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <Button :disabled="processing">Save Changes</Button>
                        <Button as-child variant="outline">
                            <Link :href="MatterController.show(matter)"
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
                    Delete Matter
                </h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    This permanently removes the matter from the active tenant.
                </p>

                <Form
                    v-bind="MatterController.destroy.form(matter)"
                    v-slot="{ processing }"
                    class="mt-4"
                >
                    <Button variant="destructive" :disabled="processing"
                        >Delete Matter</Button
                    >
                </Form>
            </div>
        </div>
    </AppLayout>
</template>
