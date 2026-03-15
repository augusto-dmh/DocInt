<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import DocumentExperienceFrame from '@/components/documents/DocumentExperienceFrame.vue';
import DocumentExperienceSurface from '@/components/documents/DocumentExperienceSurface.vue';
import DocumentStatusBadge from '@/components/documents/DocumentStatusBadge.vue';
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
import AppLayout from '@/layouts/AppLayout.vue';
import type {
    BreadcrumbItem,
    Document,
    DocumentExperienceGuardrails,
    Matter,
} from '@/types';
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import MatterController from '@/actions/App/Http/Controllers/MatterController';

const isDeleteDialogOpen = ref(false);

const props = defineProps<{
    document: Document & { matter: Matter };
    documentExperience: DocumentExperienceGuardrails;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Documents',
        href: DocumentController.index.url(),
    },
    {
        title: props.document.title,
        href: DocumentController.show.url(props.document),
    },
    {
        title: 'Edit',
    },
];

const canDeleteDocument =
    usePage().props.auth.permissions.includes('delete documents');
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Edit ${document.title}`" />

        <DocumentExperienceFrame
            :document-experience="documentExperience"
            eyebrow="Document controls"
            :title="`Edit ${document.title}`"
        >
            <template #description>
                <span class="inline-flex items-center gap-2">
                    <span class="doc-subtle text-sm">Current status</span>
                    <DocumentStatusBadge :status="document.status" />
                </span>
            </template>

            <div class="mt-6 grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
                <DocumentExperienceSurface
                    :document-experience="documentExperience"
                    :delay="1"
                    class="p-6 sm:p-8"
                >
                    <div class="mb-6">
                        <p
                            class="doc-seal text-xs font-semibold tracking-[0.12em] uppercase"
                        >
                            Metadata
                        </p>
                        <h2 class="doc-title mt-2 text-2xl font-semibold">
                            Update document details
                        </h2>
                    </div>

                    <div
                        class="mb-6 rounded-2xl border border-[var(--doc-border)]/70 bg-[var(--doc-paper)]/72 p-4"
                    >
                        <p
                            class="doc-subtle text-[11px] font-semibold tracking-[0.12em] uppercase"
                        >
                            Linked matter
                        </p>
                        <Link
                            :href="MatterController.show(document.matter)"
                            class="doc-title mt-2 block text-base font-semibold hover:underline"
                        >
                            {{ document.matter.title }}
                        </Link>
                    </div>

                    <Form
                        v-bind="DocumentController.update.form(document)"
                        v-slot="{ errors, processing, recentlySuccessful }"
                        class="space-y-6"
                    >
                        <div class="grid gap-2">
                            <Label for="title">Title</Label>
                            <Input
                                id="title"
                                name="title"
                                required
                                :default-value="document.title"
                                placeholder="Document title"
                            />
                            <InputError :message="errors.title" />
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <Button
                                :disabled="processing"
                                class="bg-[var(--doc-seal)] text-white hover:bg-primary/90"
                            >
                                Save Changes
                            </Button>
                            <Button as-child variant="outline">
                                <Link :href="DocumentController.show(document)"
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
                </DocumentExperienceSurface>

                <DocumentExperienceSurface
                    :document-experience="documentExperience"
                    :delay="2"
                    class="p-6"
                >
                    <p
                        class="doc-seal text-xs font-semibold tracking-[0.12em] uppercase"
                    >
                        Stored file
                    </p>
                    <h2 class="doc-title mt-2 text-2xl font-semibold">
                        File record
                    </h2>

                    <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                        <div
                            class="rounded-2xl border border-[var(--doc-border)]/70 bg-[var(--doc-paper)]/72 p-4"
                        >
                            <dt
                                class="doc-subtle text-[11px] font-semibold tracking-[0.12em] uppercase"
                            >
                                File name
                            </dt>
                            <dd class="doc-title mt-2 text-base font-semibold">
                                {{ document.file_name }}
                            </dd>
                        </div>

                        <div
                            class="rounded-2xl border border-[var(--doc-border)]/70 bg-[var(--doc-paper)]/72 p-4"
                        >
                            <dt
                                class="doc-subtle text-[11px] font-semibold tracking-[0.12em] uppercase"
                            >
                                Status
                            </dt>
                            <dd class="mt-2">
                                <DocumentStatusBadge
                                    :status="document.status"
                                />
                            </dd>
                        </div>
                    </dl>

                    <div
                        v-if="canDeleteDocument"
                        class="mt-6 rounded-2xl border border-destructive/30 bg-destructive/5 p-5"
                    >
                        <h3 class="text-lg font-semibold text-destructive">
                            Delete document
                        </h3>
                        <p class="mt-2 text-sm text-muted-foreground">
                            This removes the document record and deletes the
                            stored file for the active tenant.
                        </p>

                        <Dialog v-model:open="isDeleteDialogOpen">
                            <DialogTrigger as-child>
                                <Button variant="destructive" class="mt-4">
                                    Delete Document
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>
                                        Delete {{ document.title }}?
                                    </DialogTitle>
                                    <DialogDescription>
                                        This action cannot be undone.
                                    </DialogDescription>
                                </DialogHeader>
                                <DialogFooter>
                                    <Button
                                        variant="outline"
                                        @click="isDeleteDialogOpen = false"
                                    >
                                        Cancel
                                    </Button>
                                    <Form
                                        v-bind="
                                            DocumentController.destroy.form(
                                                document,
                                            )
                                        "
                                        v-slot="{ processing }"
                                    >
                                        <Button
                                            variant="destructive"
                                            :disabled="processing"
                                        >
                                            Delete Document
                                        </Button>
                                    </Form>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </div>
                </DocumentExperienceSurface>
            </div>
        </DocumentExperienceFrame>
    </AppLayout>
</template>
