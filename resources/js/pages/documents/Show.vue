<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import MatterController from '@/actions/App/Http/Controllers/MatterController';
import DocumentExperienceFrame from '@/components/documents/DocumentExperienceFrame.vue';
import DocumentExperienceSurface from '@/components/documents/DocumentExperienceSurface.vue';
import DocumentStatusBadge from '@/components/documents/DocumentStatusBadge.vue';
import EvidenceKeyValueList from '@/components/documents/EvidenceKeyValueList.vue';
import PdfViewer from '@/components/documents/PdfViewer.vue';
import { Button } from '@/components/ui/button';
import { useDocumentChannel } from '@/composables/useDocumentChannel';
import AppLayout from '@/layouts/AppLayout.vue';
import type {
    BreadcrumbItem,
    Document,
    DocumentActivity,
    DocumentClassificationSummary,
    DocumentExtractedData,
    DocumentExperienceGuardrails,
    DocumentProcessingActivity,
    DocumentReviewWorkspace,
} from '@/types';

const props = defineProps<{
    document: Document;
    recentActivity: DocumentActivity[];
    processingActivity: DocumentProcessingActivity[];
    reviewWorkspace: DocumentReviewWorkspace;
    extractedData: DocumentExtractedData | null;
    classification: DocumentClassificationSummary | null;
    documentExperience: DocumentExperienceGuardrails;
}>();

const permissions = usePage().props.auth.permissions;
const canEditDocuments = permissions.includes('edit documents');
const canReviewDocuments = permissions.includes('review documents');
const canApproveDocuments = permissions.includes('approve documents');
const isReloadingDocument = ref(false);
const hasPendingDocumentReload = ref(false);
const reviewForm = useForm({});
const approveForm = useForm({});
const rejectForm = useForm({});
const downloadUrl = computed(() =>
    DocumentController.download.url(props.document),
);
const preview = computed(() => props.reviewWorkspace.preview);
const currentStatus = computed(() => props.document.status);
const extractedDataPayloadEntries = computed(() =>
    objectEntries(props.extractedData?.payload),
);
const extractedDataMetadataEntries = computed(() =>
    objectEntries(props.extractedData?.metadata),
);
const classificationMetadataEntries = computed(() =>
    objectEntries(props.classification?.metadata),
);

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Documents',
        href: DocumentController.index.url(),
    },
    {
        title: props.document.title,
    },
];

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(value));
}

function formatDateTime(value: string): string {
    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));
}

function formatFileSize(bytes: number): string {
    const sizeInMb = bytes / (1024 * 1024);

    if (sizeInMb >= 1) {
        return `${sizeInMb.toFixed(2)} MB`;
    }

    return `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

function titleCase(value: string): string {
    return value
        .replaceAll('_', ' ')
        .split(' ')
        .map((segment) =>
            segment === ''
                ? segment
                : segment.charAt(0).toUpperCase() + segment.slice(1),
        )
        .join(' ');
}

function activityLabel(action: string): string {
    if (action === 'uploaded') {
        return 'Document uploaded';
    }

    if (action === 'viewed') {
        return 'Document viewed';
    }

    if (action === 'downloaded') {
        return 'Document downloaded';
    }

    if (action === 'reviewed') {
        return 'Document reviewed';
    }

    if (action === 'approved') {
        return 'Document approved';
    }

    if (action === 'rejected') {
        return 'Document rejected';
    }

    if (action === 'deleted') {
        return 'Document deleted';
    }

    return action.replaceAll('_', ' ');
}

function formatProcessingConsumer(consumerName: string): string {
    return titleCase(consumerName.replaceAll('-', ' '));
}

function formatProcessingTransition(
    statusFrom: string | null,
    statusTo: string | null,
    event: string,
): string {
    if (statusFrom && statusTo) {
        return `${statusFrom.replaceAll('_', ' ')} -> ${statusTo.replaceAll('_', ' ')}`;
    }

    if (statusTo) {
        return statusTo.replaceAll('_', ' ');
    }

    return event.replaceAll('.', ' ');
}

function objectEntries(
    value: Record<string, unknown> | null | undefined,
): Array<[string, unknown]> {
    if (!value || Array.isArray(value)) {
        return [];
    }

    return Object.entries(value);
}

function formatClassificationType(value: string): string {
    return titleCase(value);
}

function formatConfidence(value: number | null): string | null {
    if (value === null) {
        return null;
    }

    return `${(value * 100).toFixed(1)}% confidence`;
}

function formStatusError(form: typeof reviewForm): string | null {
    const errors = form.errors as Record<string, string | undefined>;

    return errors.status ?? null;
}

function canMarkReviewed(): boolean {
    return canReviewDocuments && currentStatus.value === 'ready_for_review';
}

function canRejectDocument(): boolean {
    return (
        canReviewDocuments &&
        ['ready_for_review', 'reviewed'].includes(currentStatus.value)
    );
}

function canApproveDocument(): boolean {
    return canApproveDocuments && currentStatus.value === 'reviewed';
}

function markReviewed(): void {
    reviewForm.submit(DocumentController.review(props.document), {
        preserveScroll: true,
    });
}

function approveDocument(): void {
    approveForm.submit(DocumentController.approve(props.document), {
        preserveScroll: true,
    });
}

function rejectDocument(): void {
    rejectForm.submit(DocumentController.reject(props.document), {
        preserveScroll: true,
    });
}

function reloadDocument(): void {
    if (isReloadingDocument.value) {
        hasPendingDocumentReload.value = true;

        return;
    }

    isReloadingDocument.value = true;

    router.reload({
        only: [
            'document',
            'recentActivity',
            'processingActivity',
            'reviewWorkspace',
            'extractedData',
            'classification',
        ],
        onFinish: () => {
            isReloadingDocument.value = false;

            if (!hasPendingDocumentReload.value) {
                return;
            }

            hasPendingDocumentReload.value = false;
            reloadDocument();
        },
    });
}

useDocumentChannel({
    documentId: props.document.id,
    onStatusUpdated: (payload) => {
        if (payload.document_id !== props.document.id) {
            return;
        }

        reloadDocument();
    },
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="document.title" />

        <DocumentExperienceFrame
            :document-experience="documentExperience"
            eyebrow="Case document"
            :title="document.title"
        >
            <template #description>
                <span class="inline-flex items-center gap-2">
                    <span class="doc-subtle text-sm">Current status</span>
                    <DocumentStatusBadge :status="document.status" />
                </span>
            </template>

            <template #actions>
                <Button
                    v-if="canMarkReviewed()"
                    variant="outline"
                    :disabled="reviewForm.processing"
                    @click="markReviewed"
                >
                    {{ reviewForm.processing ? 'Marking...' : 'Mark reviewed' }}
                </Button>

                <Button
                    v-if="canApproveDocument()"
                    class="bg-[var(--doc-seal)] text-white hover:bg-primary/90"
                    :disabled="approveForm.processing"
                    @click="approveDocument"
                >
                    {{ approveForm.processing ? 'Approving...' : 'Approve' }}
                </Button>

                <Button
                    v-if="canRejectDocument()"
                    variant="destructive"
                    :disabled="rejectForm.processing"
                    @click="rejectDocument"
                >
                    {{ rejectForm.processing ? 'Rejecting...' : 'Reject' }}
                </Button>

                <Button
                    as-child
                    class="bg-[var(--doc-seal)] text-white hover:bg-primary/90"
                >
                    <a :href="DocumentController.download.url(document)">
                        Download
                    </a>
                </Button>

                <Button v-if="canEditDocuments" as-child variant="outline">
                    <Link :href="DocumentController.edit(document)">
                        Edit metadata
                    </Link>
                </Button>
            </template>

            <p
                v-if="
                    formStatusError(reviewForm) ||
                    formStatusError(approveForm) ||
                    formStatusError(rejectForm)
                "
                class="mt-4 text-sm text-destructive"
            >
                {{
                    formStatusError(reviewForm) ??
                    formStatusError(approveForm) ??
                    formStatusError(rejectForm)
                }}
            </p>

            <DocumentExperienceSurface
                :document-experience="documentExperience"
                :delay="1"
                class="mt-6 p-5 sm:p-6"
            >
                <div
                    class="grid gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(22rem,0.85fr)]"
                >
                    <div class="space-y-6">
                        <section class="space-y-4">
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <div>
                                    <h2 class="doc-title text-xl font-semibold">
                                        Review workspace
                                    </h2>
                                    <p
                                        class="doc-subtle mt-1 text-sm leading-6"
                                    >
                                        Keep the source file visible while you
                                        verify extracted evidence and current
                                        processing context.
                                    </p>
                                </div>
                                <span
                                    class="rounded-full border border-[color:var(--doc-grid-line)] px-3 py-1 text-xs font-semibold tracking-[0.12em] text-[var(--doc-seal)] uppercase"
                                >
                                    {{
                                        preview.supported
                                            ? 'PDF preview'
                                            : 'File details'
                                    }}
                                </span>
                            </div>

                            <PdfViewer
                                v-if="preview.supported && preview.url"
                                :document-experience="documentExperience"
                                :src="preview.url"
                                :title="preview.fileName"
                                :delay="1"
                            />

                            <div
                                v-else
                                class="overflow-hidden rounded-[1.5rem] border border-dashed border-[color:var(--doc-grid-line)] bg-[linear-gradient(135deg,rgba(245,240,231,0.95),rgba(232,224,210,0.88))] p-6 sm:p-8"
                            >
                                <div class="max-w-2xl space-y-5">
                                    <div>
                                        <p
                                            class="doc-subtle text-xs font-semibold tracking-[0.16em] uppercase"
                                        >
                                            Inline preview unavailable
                                        </p>
                                        <h3
                                            class="doc-title mt-3 text-2xl font-semibold"
                                        >
                                            This file stays reviewable through
                                            its evidence and metadata.
                                        </h3>
                                        <p
                                            class="doc-subtle mt-3 text-sm leading-6"
                                        >
                                            Only PDF documents render inside the
                                            workspace preview. Use the original
                                            file details below or download the
                                            source file directly.
                                        </p>
                                    </div>

                                    <dl class="grid gap-4 sm:grid-cols-2">
                                        <div
                                            class="rounded-2xl border border-[color:var(--doc-grid-line)] bg-white/70 p-4"
                                        >
                                            <dt
                                                class="doc-subtle text-xs font-semibold tracking-[0.12em] uppercase"
                                            >
                                                File name
                                            </dt>
                                            <dd
                                                class="doc-title mt-2 text-sm font-semibold"
                                            >
                                                {{ preview.fileName }}
                                            </dd>
                                        </div>
                                        <div
                                            class="rounded-2xl border border-[color:var(--doc-grid-line)] bg-white/70 p-4"
                                        >
                                            <dt
                                                class="doc-subtle text-xs font-semibold tracking-[0.12em] uppercase"
                                            >
                                                Detected type
                                            </dt>
                                            <dd class="mt-2 text-sm">
                                                {{
                                                    preview.mimeType ??
                                                    'Unknown MIME type'
                                                }}
                                            </dd>
                                        </div>
                                    </dl>

                                    <Button
                                        as-child
                                        class="bg-[var(--doc-seal)] text-white hover:bg-primary/90"
                                    >
                                        <a :href="downloadUrl">
                                            Download original file
                                        </a>
                                    </Button>
                                </div>
                            </div>
                        </section>

                        <section class="grid gap-6 lg:grid-cols-2">
                            <div
                                class="doc-grid-line rounded-[1.5rem] border p-5"
                            >
                                <div
                                    class="mb-4 flex flex-wrap items-center justify-between gap-2"
                                >
                                    <h2 class="doc-title text-lg font-semibold">
                                        Document metadata
                                    </h2>
                                    <DocumentStatusBadge
                                        :status="document.status"
                                    />
                                </div>

                                <dl class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <dt
                                            class="doc-subtle text-xs font-semibold tracking-[0.12em] uppercase"
                                        >
                                            File name
                                        </dt>
                                        <dd
                                            class="doc-title mt-1 text-sm font-semibold"
                                        >
                                            {{ document.file_name }}
                                        </dd>
                                    </div>

                                    <div>
                                        <dt
                                            class="doc-subtle text-xs font-semibold tracking-[0.12em] uppercase"
                                        >
                                            File size
                                        </dt>
                                        <dd class="mt-1 text-sm">
                                            {{
                                                formatFileSize(
                                                    document.file_size,
                                                )
                                            }}
                                        </dd>
                                    </div>

                                    <div>
                                        <dt
                                            class="doc-subtle text-xs font-semibold tracking-[0.12em] uppercase"
                                        >
                                            MIME type
                                        </dt>
                                        <dd class="mt-1 text-sm">
                                            {{
                                                document.mime_type ?? 'Unknown'
                                            }}
                                        </dd>
                                    </div>

                                    <div>
                                        <dt
                                            class="doc-subtle text-xs font-semibold tracking-[0.12em] uppercase"
                                        >
                                            Uploaded by
                                        </dt>
                                        <dd class="mt-1 text-sm">
                                            {{
                                                document.uploader?.name ??
                                                'System'
                                            }}
                                        </dd>
                                    </div>

                                    <div>
                                        <dt
                                            class="doc-subtle text-xs font-semibold tracking-[0.12em] uppercase"
                                        >
                                            Matter
                                        </dt>
                                        <dd class="mt-1 text-sm">
                                            <Link
                                                v-if="document.matter"
                                                :href="
                                                    MatterController.show(
                                                        document.matter,
                                                    )
                                                "
                                                class="doc-seal hover:underline"
                                            >
                                                {{ document.matter.title }}
                                            </Link>
                                            <span v-else>—</span>
                                        </dd>
                                    </div>

                                    <div>
                                        <dt
                                            class="doc-subtle text-xs font-semibold tracking-[0.12em] uppercase"
                                        >
                                            Recorded on
                                        </dt>
                                        <dd class="mt-1 text-sm">
                                            {{
                                                formatDate(document.created_at)
                                            }}
                                        </dd>
                                    </div>

                                    <div>
                                        <dt
                                            class="doc-subtle text-xs font-semibold tracking-[0.12em] uppercase"
                                        >
                                            Last updated
                                        </dt>
                                        <dd class="mt-1 text-sm">
                                            {{
                                                formatDate(document.updated_at)
                                            }}
                                        </dd>
                                    </div>

                                    <div class="sm:col-span-2">
                                        <dt
                                            class="doc-subtle text-xs font-semibold tracking-[0.12em] uppercase"
                                        >
                                            Storage key
                                        </dt>
                                        <dd class="mt-1 text-xs break-all">
                                            {{ document.file_path }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>

                            <div
                                class="doc-grid-line rounded-[1.5rem] border p-5"
                            >
                                <div
                                    class="mb-4 flex flex-wrap items-center justify-between gap-2"
                                >
                                    <h2 class="doc-title text-lg font-semibold">
                                        Processing activity
                                    </h2>
                                    <span
                                        class="doc-subtle text-xs font-semibold tracking-[0.12em] uppercase"
                                    >
                                        {{ props.processingActivity.length }}
                                        events
                                    </span>
                                </div>

                                <div
                                    v-if="props.processingActivity.length === 0"
                                    class="rounded-2xl border border-dashed border-[color:var(--doc-grid-line)] p-4"
                                >
                                    <p class="doc-title text-sm font-semibold">
                                        No processing events yet
                                    </p>
                                    <p
                                        class="doc-subtle mt-2 text-xs leading-5"
                                    >
                                        Runtime workers will append scan,
                                        extraction, and classification activity
                                        here as the document moves through the
                                        pipeline.
                                    </p>
                                </div>

                                <ol v-else class="space-y-3">
                                    <li
                                        v-for="event in props.processingActivity"
                                        :key="event.id"
                                        class="rounded-2xl border border-[color:var(--doc-grid-line)] p-4"
                                    >
                                        <div
                                            class="flex flex-wrap items-center justify-between gap-2"
                                        >
                                            <div>
                                                <p
                                                    class="doc-title text-sm font-semibold"
                                                >
                                                    {{
                                                        formatProcessingConsumer(
                                                            event.consumer_name,
                                                        )
                                                    }}
                                                </p>
                                                <p
                                                    class="doc-subtle mt-1 text-xs"
                                                >
                                                    {{
                                                        formatProcessingTransition(
                                                            event.status_from,
                                                            event.status_to,
                                                            event.event,
                                                        )
                                                    }}
                                                </p>
                                            </div>
                                            <p class="doc-subtle text-xs">
                                                {{
                                                    formatDateTime(
                                                        event.created_at,
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </li>
                                </ol>
                            </div>
                        </section>
                    </div>

                    <div class="space-y-6">
                        <section
                            class="doc-grid-line rounded-[1.5rem] border p-5"
                        >
                            <div
                                class="mb-4 flex flex-wrap items-center justify-between gap-2"
                            >
                                <h2 class="doc-title text-lg font-semibold">
                                    Classification
                                </h2>
                                <span
                                    class="doc-subtle text-xs font-semibold tracking-[0.12em] uppercase"
                                >
                                    Evidence signal
                                </span>
                            </div>

                            <div v-if="classification" class="space-y-4">
                                <div
                                    class="rounded-[1.25rem] border border-[color:var(--doc-grid-line)] bg-[rgba(255,255,255,0.66)] p-4"
                                >
                                    <p
                                        class="doc-subtle text-xs font-semibold tracking-[0.12em] uppercase"
                                    >
                                        Type
                                    </p>
                                    <p
                                        class="doc-title mt-2 text-2xl font-semibold"
                                    >
                                        {{
                                            formatClassificationType(
                                                classification.type,
                                            )
                                        }}
                                    </p>
                                    <p
                                        v-if="
                                            formatConfidence(
                                                classification.confidence,
                                            )
                                        "
                                        class="doc-subtle mt-2 text-sm"
                                    >
                                        {{
                                            formatConfidence(
                                                classification.confidence,
                                            )
                                        }}
                                    </p>
                                </div>

                                <dl class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <dt
                                            class="doc-subtle text-xs font-semibold tracking-[0.12em] uppercase"
                                        >
                                            Provider
                                        </dt>
                                        <dd class="mt-1 text-sm">
                                            {{ classification.provider }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt
                                            class="doc-subtle text-xs font-semibold tracking-[0.12em] uppercase"
                                        >
                                            Updated
                                        </dt>
                                        <dd class="mt-1 text-sm">
                                            {{
                                                formatDateTime(
                                                    classification.updated_at,
                                                )
                                            }}
                                        </dd>
                                    </div>
                                </dl>

                                <div
                                    v-if="
                                        classificationMetadataEntries.length > 0
                                    "
                                    class="space-y-3"
                                >
                                    <p
                                        class="doc-subtle text-xs font-semibold tracking-[0.12em] uppercase"
                                    >
                                        Provider metadata
                                    </p>
                                    <EvidenceKeyValueList
                                        :entries="classificationMetadataEntries"
                                    />
                                </div>
                            </div>

                            <div
                                v-else
                                class="rounded-2xl border border-dashed border-[color:var(--doc-grid-line)] p-4"
                            >
                                <p class="doc-title text-sm font-semibold">
                                    Classification pending
                                </p>
                                <p class="doc-subtle mt-2 text-sm leading-6">
                                    This document has not produced a persisted
                                    classification result yet. The review
                                    workspace will surface provider details here
                                    once classification completes.
                                </p>
                            </div>
                        </section>

                        <section
                            class="doc-grid-line rounded-[1.5rem] border p-5"
                        >
                            <div
                                class="mb-4 flex flex-wrap items-center justify-between gap-2"
                            >
                                <h2 class="doc-title text-lg font-semibold">
                                    Extracted data
                                </h2>
                                <span
                                    class="doc-subtle text-xs font-semibold tracking-[0.12em] uppercase"
                                >
                                    Review evidence
                                </span>
                            </div>

                            <div v-if="extractedData" class="space-y-5">
                                <dl class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <dt
                                            class="doc-subtle text-xs font-semibold tracking-[0.12em] uppercase"
                                        >
                                            Provider
                                        </dt>
                                        <dd class="mt-1 text-sm">
                                            {{ extractedData.provider }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt
                                            class="doc-subtle text-xs font-semibold tracking-[0.12em] uppercase"
                                        >
                                            Updated
                                        </dt>
                                        <dd class="mt-1 text-sm">
                                            {{
                                                formatDateTime(
                                                    extractedData.updated_at,
                                                )
                                            }}
                                        </dd>
                                    </div>
                                </dl>

                                <div
                                    class="rounded-[1.25rem] border border-[color:var(--doc-grid-line)] bg-[rgba(255,255,255,0.72)] p-4"
                                >
                                    <div
                                        class="mb-3 flex items-center justify-between gap-2"
                                    >
                                        <h3
                                            class="doc-title text-sm font-semibold"
                                        >
                                            Extracted text
                                        </h3>
                                        <span
                                            class="doc-subtle text-xs font-semibold tracking-[0.12em] uppercase"
                                        >
                                            Source transcript
                                        </span>
                                    </div>

                                    <div
                                        v-if="extractedData.extracted_text"
                                        class="max-h-72 overflow-y-auto rounded-2xl bg-[rgba(244,238,228,0.85)] p-4"
                                    >
                                        <p
                                            class="text-sm leading-6 whitespace-pre-wrap"
                                        >
                                            {{ extractedData.extracted_text }}
                                        </p>
                                    </div>

                                    <p
                                        v-else
                                        class="doc-subtle text-sm leading-6"
                                    >
                                        No extracted text is available for this
                                        document yet.
                                    </p>
                                </div>

                                <div
                                    v-if="
                                        extractedDataPayloadEntries.length > 0
                                    "
                                    class="space-y-3"
                                >
                                    <h3 class="doc-title text-sm font-semibold">
                                        Structured payload
                                    </h3>
                                    <EvidenceKeyValueList
                                        :entries="extractedDataPayloadEntries"
                                    />
                                </div>

                                <div
                                    v-if="
                                        extractedDataMetadataEntries.length > 0
                                    "
                                    class="space-y-3"
                                >
                                    <h3 class="doc-title text-sm font-semibold">
                                        Provider metadata
                                    </h3>
                                    <EvidenceKeyValueList
                                        :entries="extractedDataMetadataEntries"
                                    />
                                </div>
                            </div>

                            <div
                                v-else
                                class="rounded-2xl border border-dashed border-[color:var(--doc-grid-line)] p-4"
                            >
                                <p class="doc-title text-sm font-semibold">
                                    No extracted evidence yet
                                </p>
                                <p class="doc-subtle mt-2 text-sm leading-6">
                                    When OCR extraction persists text and
                                    structured payload data, it will appear here
                                    alongside the original document preview.
                                </p>
                            </div>
                        </section>

                        <section
                            class="doc-grid-line rounded-[1.5rem] border p-5"
                        >
                            <div
                                class="mb-4 flex flex-wrap items-center justify-between gap-2"
                            >
                                <h2 class="doc-title text-lg font-semibold">
                                    Activity timeline
                                </h2>
                                <span
                                    class="doc-subtle text-xs font-semibold tracking-[0.12em] uppercase"
                                >
                                    {{ props.recentActivity.length }} events
                                </span>
                            </div>

                            <ol class="space-y-3">
                                <li
                                    v-for="activity in props.recentActivity"
                                    :key="activity.id"
                                    class="rounded-2xl border border-[color:var(--doc-grid-line)] p-4"
                                >
                                    <div
                                        class="flex flex-wrap items-center justify-between gap-2"
                                    >
                                        <p
                                            class="doc-title text-sm font-semibold"
                                        >
                                            {{ activityLabel(activity.action) }}
                                        </p>
                                        <p class="doc-subtle text-xs">
                                            {{
                                                formatDateTime(
                                                    activity.created_at,
                                                )
                                            }}
                                        </p>
                                    </div>

                                    <p class="doc-subtle mt-1 text-xs">
                                        {{ activity.user?.name ?? 'System' }}
                                        <span v-if="activity.ip_address">
                                            • {{ activity.ip_address }}
                                        </span>
                                    </p>
                                </li>
                            </ol>
                        </section>
                    </div>
                </div>
            </DocumentExperienceSurface>
        </DocumentExperienceFrame>
    </AppLayout>
</template>
