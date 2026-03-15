<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import MatterController from '@/actions/App/Http/Controllers/MatterController';
import DocumentEmptyState from '@/components/documents/DocumentEmptyState.vue';
import DocumentExperienceFrame from '@/components/documents/DocumentExperienceFrame.vue';
import DocumentExperienceSurface from '@/components/documents/DocumentExperienceSurface.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import UploadDropzone from '@/components/UploadDropzone.vue';
import UploadProgressTracker from '@/components/UploadProgressTracker.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    type BreadcrumbItem,
    type DocumentExperienceGuardrails,
    type Matter,
} from '@/types';

const props = defineProps<{
    matter: Matter;
    documentExperience: DocumentExperienceGuardrails;
}>();

const permissions = usePage().props.auth.permissions;
const canCreateDocuments = permissions.includes('create documents');

const form = useForm<{
    title: string;
    file: File | null;
}>({
    title: '',
    file: null,
});

const selectedFile = ref<File | null>(null);

const uploadItems = computed(() => {
    if (!selectedFile.value) {
        return [];
    }

    const hasFileError = Boolean(form.errors.file);
    const progress = form.processing ? (form.progress?.percentage ?? 12) : 100;
    const status = form.processing
        ? 'uploading'
        : hasFileError
          ? 'failed'
          : 'completed';

    return [
        {
            name: selectedFile.value.name,
            size: selectedFile.value.size,
            progress,
            status,
        },
    ];
});

const hasValidationErrors = computed(() => Object.keys(form.errors).length > 0);

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
        title: 'Upload Document',
    },
];

function onFileSelected(file: File): void {
    selectedFile.value = file;
    form.file = file;
}

function onFileCleared(): void {
    selectedFile.value = null;
    form.file = null;
}

function submit(): void {
    if (!canCreateDocuments || !form.file) {
        return;
    }

    form.submit(DocumentController.store(props.matter), {
        forceFormData: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Upload Document" />

        <DocumentExperienceFrame
            :document-experience="documentExperience"
            eyebrow="Matter archive"
            title="Upload legal document"
        >
            <template #description>
                Add supporting files to <strong>{{ matter.title }}</strong> with
                private S3 storage and audit tracking.
            </template>

            <DocumentEmptyState
                v-if="hasValidationErrors"
                :document-experience="documentExperience"
                title="Upload needs attention"
                description="Resolve the highlighted fields before submitting this file to the archive."
                class="doc-fade-up doc-delay-1 mt-6"
            />

            <DocumentExperienceSurface
                :document-experience="documentExperience"
                :delay="1"
                class="mt-6 p-6 sm:p-8"
            >
                <form
                    class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]"
                    @submit.prevent="submit"
                >
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
                            name="title"
                            required
                            autocomplete="off"
                            placeholder="e.g. Retainer agreement"
                            class="border-[var(--doc-border)] bg-card"
                        />
                        <InputError :message="form.errors.title" />
                    </div>

                    <div class="space-y-6">
                        <div class="grid gap-2">
                            <Label class="doc-title text-sm font-semibold"
                                >File</Label
                            >
                            <UploadDropzone
                                :document-experience="documentExperience"
                                :disabled="form.processing"
                                :server-error="form.errors.file"
                                @file-selected="onFileSelected"
                                @file-cleared="onFileCleared"
                            />
                            <UploadProgressTracker
                                :document-experience="documentExperience"
                                :items="uploadItems"
                            />
                        </div>

                        <div
                            class="rounded-xl border border-[var(--doc-border)] bg-card/80 p-4"
                        >
                            <p
                                class="doc-seal text-xs font-semibold tracking-[0.12em] uppercase"
                            >
                                Matter context
                            </p>
                            <p class="doc-title mt-2 text-base font-semibold">
                                {{ matter.title }}
                            </p>
                            <p class="doc-subtle mt-2 text-xs">
                                The file will be stored with tenant-scoped
                                encryption and linked to this matter.
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <Button
                                type="submit"
                                :disabled="
                                    form.processing ||
                                    !canCreateDocuments ||
                                    !form.file
                                "
                                class="bg-[var(--doc-seal)] text-white hover:bg-primary/90"
                            >
                                {{
                                    form.processing
                                        ? 'Uploading...'
                                        : 'Upload Document'
                                }}
                            </Button>

                            <Button as-child type="button" variant="outline">
                                <Link :href="DocumentController.index()"
                                    >Back to documents</Link
                                >
                            </Button>
                        </div>
                    </div>
                </form>
            </DocumentExperienceSurface>
        </DocumentExperienceFrame>
    </AppLayout>
</template>
