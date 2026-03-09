<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Matter } from '@/types';
import MatterController from '@/actions/App/Http/Controllers/MatterController';

const isDeleteDialogOpen = ref(false);

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
                        <Textarea
                            id="description"
                            name="description"
                            rows="4"
                            :default-value="matter.description ?? ''"
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

                <Dialog v-model:open="isDeleteDialogOpen">
                    <DialogTrigger as-child>
                        <Button variant="destructive" class="mt-4"
                            >Delete Matter</Button
                        >
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle
                                >Delete {{ matter.title }}?</DialogTitle
                            >
                            <DialogDescription>
                                This action cannot be undone. The matter and all
                                associated data will be permanently removed.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                @click="isDeleteDialogOpen = false"
                                >Cancel</Button
                            >
                            <Form
                                v-bind="MatterController.destroy.form(matter)"
                                v-slot="{ processing }"
                            >
                                <Button
                                    variant="destructive"
                                    :disabled="processing"
                                    >Delete Matter</Button
                                >
                            </Form>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </div>
    </AppLayout>
</template>
