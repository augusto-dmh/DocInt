<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import MatterController from '@/actions/App/Http/Controllers/MatterController';
import DocumentExperienceFrame from '@/components/documents/DocumentExperienceFrame.vue';
import DocumentExperienceSurface from '@/components/documents/DocumentExperienceSurface.vue';
import DocumentStatusBadge from '@/components/documents/DocumentStatusBadge.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    type BreadcrumbItem,
    type Document,
    type DocumentExperienceGuardrails,
} from '@/types';

const props = defineProps<{
    document: Document;
    documentExperience: DocumentExperienceGuardrails;
}>();

const permissions = usePage().props.auth.permissions;
const canDeleteDocuments = permissions.includes('delete documents');

const form = useForm<{
    title: string;
}>({
    title: props.document.title,
});

const deleteForm = useForm({});
const deleteError = ref('');

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

function updateDocument(): void {
    form.submit(DocumentController.update(props.document), {
        preserveScroll: true,
    });
}

function deleteDocument(): void {
    deleteError.value = '';

    if (!canDeleteDocuments) {
        deleteError.value = 'You do not have permission to delete documents.';
        return;
    }

    if (!window.confirm('Delete this document permanently?')) {
        return;
    }

    deleteForm.submit(DocumentController.destroy(props.document), {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Edit ${document.title}`" />

        <DocumentExperienceFrame
            :document-experience="documentExperience"
            eyebrow="Metadata revision"
            title="Edit document record"
            description="Update document title without changing the archived file."
        >
            <DocumentExperienceSurface
                :document-experience="documentExperience"
                :delay="1"
                class="mt-6 p-4 sm:p-5"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="space-y-1">
                        <p class="doc-subtle text-xs">Archived file</p>
                        <p class="doc-title text-sm font-semibold">
                            {{ document.file_name }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <DocumentStatusBadge :status="document.status" />
                        <Button
                            v-if="document.matter"
                            as-child
                            variant="outline"
                            size="sm"
                        >
                            <Link
                                :href="MatterController.show(document.matter)"
                            >
                                Open matter
                            </Link>
                        </Button>
                    </div>
                </div>
            </DocumentExperienceSurface>

            <DocumentExperienceSurface
                :document-experience="documentExperience"
                :delay="2"
                class="mt-6 p-6 sm:p-8"
            >
                <form class="space-y-6" @submit.prevent="updateDocument">
                    <div class="grid gap-2">
                        <Label
                            for="title"
                            class="doc-title text-sm font-semibold"
                        >
                            Document title
                        </Label>
                        <Input
                            id="title"
                            v-model="form.title"
                            required
                            placeholder="Document title"
                            class="border-[var(--doc-border)] bg-card"
                        />
                        <InputError :message="form.errors.title" />
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <Button
                            type="submit"
                            :disabled="form.processing"
                            class="bg-[var(--doc-seal)] text-white hover:bg-primary/90"
                        >
                            {{ form.processing ? 'Saving...' : 'Save Changes' }}
                        </Button>

                        <Button as-child variant="outline" type="button">
                            <Link :href="DocumentController.show(document)"
                                >Cancel</Link
                            >
                        </Button>
                    </div>
                </form>
            </DocumentExperienceSurface>

            <DocumentExperienceSurface
                v-if="canDeleteDocuments"
                :document-experience="documentExperience"
                :delay="2"
                class="mt-6 border-destructive/30 p-6 sm:p-8"
            >
                <h2 class="doc-title text-xl font-semibold">
                    Destructive action
                </h2>
                <p class="doc-subtle mt-2 text-sm">
                    Deleting removes the file from storage and cannot be undone.
                </p>

                <Button
                    type="button"
                    variant="destructive"
                    class="mt-4"
                    :disabled="deleteForm.processing"
                    @click="deleteDocument"
                >
                    {{
                        deleteForm.processing
                            ? 'Deleting...'
                            : 'Delete Document'
                    }}
                </Button>

                <p v-if="deleteError" class="mt-2 text-sm text-destructive">
                    {{ deleteError }}
                </p>
            </DocumentExperienceSurface>
        </DocumentExperienceFrame>
    </AppLayout>
</template>
