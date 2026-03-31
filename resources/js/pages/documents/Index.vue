<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import MatterController from '@/actions/App/Http/Controllers/MatterController';
import DocumentEmptyState from '@/components/documents/DocumentEmptyState.vue';
import DocumentExperienceFrame from '@/components/documents/DocumentExperienceFrame.vue';
import DocumentExperienceSurface from '@/components/documents/DocumentExperienceSurface.vue';
import DocumentStatusBadge from '@/components/documents/DocumentStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { useDocumentChannel } from '@/composables/useDocumentChannel';
import AppLayout from '@/layouts/AppLayout.vue';
import { HttpError, requestJson } from '@/lib/http';
import type {
    BreadcrumbItem,
    Document,
    DocumentBulkActionResult,
    DocumentBulkReview,
    DocumentExperienceGuardrails,
    PaginatedData,
} from '@/types';

type BulkAction = 'approve' | 'reject' | 'reassign';

const props = defineProps<{
    documents: PaginatedData<Document>;
    bulkReview: DocumentBulkReview;
    documentExperience: DocumentExperienceGuardrails;
}>();

const page = usePage();
const permissions = page.props.auth.permissions as string[];
const canEditDocuments = permissions.includes('edit documents');
const isReloadingDocuments = ref(false);
const hasPendingDocumentsReload = ref(false);
const selectedDocumentIds = ref<number[]>([]);
const selectedReviewerId = ref('');
const bulkActionInFlight = ref<BulkAction | null>(null);
const bulkActionErrorMessage = ref<string | null>(null);
const bulkActionResult = ref<DocumentBulkActionResult | null>(null);

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Documents',
        href: DocumentController.index.url(),
    },
];

const currentPageDocumentIds = computed(() =>
    props.documents.data.map((document) => document.id),
);
const selectedCount = computed(() => selectedDocumentIds.value.length);
const hasSelectedDocuments = computed(() => selectedCount.value > 0);
const allDocumentsSelected = computed(
    () =>
        currentPageDocumentIds.value.length > 0 &&
        currentPageDocumentIds.value.every((documentId) =>
            selectedDocumentIds.value.includes(documentId),
        ),
);
const canBulkApprove = computed(
    () => props.bulkReview.permissions.canBulkApprove,
);
const canBulkReject = computed(
    () => props.bulkReview.permissions.canBulkReject,
);
const canBulkReassign = computed(
    () => props.bulkReview.permissions.canBulkReassign,
);
const skippedDocumentsPreview = computed(
    () => bulkActionResult.value?.skipped.slice(0, 3) ?? [],
);
const hiddenSkippedCount = computed(() =>
    Math.max(0, (bulkActionResult.value?.skipped_count ?? 0) - 3),
);

watch(
    currentPageDocumentIds,
    (documentIds) => {
        const visibleDocumentIds = new Set(documentIds);

        selectedDocumentIds.value = selectedDocumentIds.value.filter(
            (documentId) => visibleDocumentIds.has(documentId),
        );
    },
    { immediate: true },
);

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(value));
}

function formatFileSize(bytes: number): string {
    const sizeInMb = bytes / (1024 * 1024);

    if (sizeInMb >= 1) {
        return `${sizeInMb.toFixed(2)} MB`;
    }

    return `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

function isDocumentSelected(documentId: number): boolean {
    return selectedDocumentIds.value.includes(documentId);
}

function updateDocumentSelection(documentId: number, checked: boolean): void {
    if (checked) {
        if (!selectedDocumentIds.value.includes(documentId)) {
            selectedDocumentIds.value = [
                ...selectedDocumentIds.value,
                documentId,
            ];
        }

        return;
    }

    selectedDocumentIds.value = selectedDocumentIds.value.filter(
        (selectedDocumentId) => selectedDocumentId !== documentId,
    );
}

function clearSelectedDocuments(): void {
    selectedDocumentIds.value = [];
}

function onSelectAllChange(event: Event): void {
    const target = event.target;

    if (!(target instanceof HTMLInputElement)) {
        return;
    }

    selectedDocumentIds.value = target.checked
        ? [...currentPageDocumentIds.value]
        : [];
}

function onDocumentSelectionChange(documentId: number, event: Event): void {
    const target = event.target;

    if (!(target instanceof HTMLInputElement)) {
        return;
    }

    updateDocumentSelection(documentId, target.checked);
}

function resetBulkFeedback(): void {
    bulkActionErrorMessage.value = null;
    bulkActionResult.value = null;
}

function reloadDocuments(): void {
    if (isReloadingDocuments.value) {
        hasPendingDocumentsReload.value = true;

        return;
    }

    isReloadingDocuments.value = true;

    router.reload({
        only: ['documents'],
        onFinish: () => {
            isReloadingDocuments.value = false;

            if (!hasPendingDocumentsReload.value) {
                return;
            }

            hasPendingDocumentsReload.value = false;
            reloadDocuments();
        },
    });
}

function resolveBulkActionError(
    error: unknown,
    fallbackMessage: string,
): string {
    if (!(error instanceof HttpError)) {
        return fallbackMessage;
    }

    if (error.errors !== null) {
        const validationMessage = Object.values(error.errors)
            .flat()
            .find((message) => typeof message === 'string');

        if (validationMessage) {
            return validationMessage;
        }
    }

    return error.message;
}

async function submitBulkAction(action: BulkAction): Promise<void> {
    if (!hasSelectedDocuments.value || bulkActionInFlight.value !== null) {
        return;
    }

    bulkActionInFlight.value = action;
    resetBulkFeedback();

    try {
        let response: DocumentBulkActionResult;

        if (action === 'approve') {
            response = await requestJson<DocumentBulkActionResult>(
                DocumentController.bulkApprove.url(),
                {
                    body: {
                        document_ids: selectedDocumentIds.value,
                    },
                },
            );
        } else if (action === 'reject') {
            response = await requestJson<DocumentBulkActionResult>(
                DocumentController.bulkReject.url(),
                {
                    body: {
                        document_ids: selectedDocumentIds.value,
                    },
                },
            );
        } else {
            response = await requestJson<DocumentBulkActionResult>(
                DocumentController.bulkAssignReviewer.url(),
                {
                    method: 'PATCH',
                    body: {
                        document_ids: selectedDocumentIds.value,
                        assigned_to:
                            selectedReviewerId.value === ''
                                ? null
                                : Number(selectedReviewerId.value),
                    },
                },
            );
        }

        bulkActionResult.value = response;
        clearSelectedDocuments();

        if (response.processed_count > 0) {
            reloadDocuments();
        }
    } catch (error) {
        bulkActionErrorMessage.value = resolveBulkActionError(
            error,
            'The bulk action could not be completed.',
        );
    } finally {
        bulkActionInFlight.value = null;
    }
}

useDocumentChannel({
    tenantId: page.props.tenant?.id ?? null,
    onStatusUpdated: () => {
        reloadDocuments();
    },
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Documents" />

        <DocumentExperienceFrame
            :document-experience="documentExperience"
            eyebrow="Private repository"
            title="Document ledger"
            description="Searchable matter documents with immutable storage and traceable activity."
        >
            <DocumentEmptyState
                v-if="props.documents.data.length === 0"
                :document-experience="documentExperience"
                title="No documents archived yet"
                description="Upload the first file from a matter workspace to start building this tenant's ledger."
                class="doc-fade-up doc-delay-1 mt-6"
            >
                <template #actions>
                    <Button as-child variant="outline">
                        <Link :href="MatterController.index()">
                            Open matters
                        </Link>
                    </Button>
                </template>
            </DocumentEmptyState>

            <DocumentExperienceSurface
                v-else
                :document-experience="documentExperience"
                :delay="1"
                class="mt-6 overflow-hidden"
            >
                <div
                    class="doc-grid-line border-b bg-[rgba(255,255,255,0.72)] p-4 sm:p-5"
                >
                    <div
                        class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between"
                    >
                        <div class="space-y-1">
                            <p
                                class="doc-seal text-xs font-semibold tracking-[0.12em] uppercase"
                            >
                                Bulk review tools
                            </p>
                            <p class="doc-title text-lg font-semibold">
                                {{
                                    hasSelectedDocuments
                                        ? `${selectedCount} selected on this page`
                                        : 'Select documents to review or reassign'
                                }}
                            </p>
                            <p class="doc-subtle text-sm leading-6">
                                Approve, reject, or reassign the current page
                                without leaving the ledger.
                            </p>
                        </div>

                        <div class="flex flex-col gap-3 lg:items-end">
                            <div
                                class="flex flex-wrap items-center gap-2 lg:justify-end"
                            >
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="
                                        !canBulkApprove ||
                                        !hasSelectedDocuments ||
                                        bulkActionInFlight !== null
                                    "
                                    @click="submitBulkAction('approve')"
                                >
                                    {{
                                        bulkActionInFlight === 'approve'
                                            ? 'Approving...'
                                            : 'Approve selected'
                                    }}
                                </Button>

                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="
                                        !canBulkReject ||
                                        !hasSelectedDocuments ||
                                        bulkActionInFlight !== null
                                    "
                                    @click="submitBulkAction('reject')"
                                >
                                    {{
                                        bulkActionInFlight === 'reject'
                                            ? 'Rejecting...'
                                            : 'Reject selected'
                                    }}
                                </Button>
                            </div>

                            <div
                                v-if="canBulkReassign"
                                class="flex flex-col gap-2 sm:flex-row sm:items-center"
                            >
                                <select
                                    v-model="selectedReviewerId"
                                    class="doc-title min-w-56 rounded-2xl border border-[color:var(--doc-grid-line)] bg-white/80 px-4 py-2.5 text-sm"
                                    :disabled="bulkActionInFlight !== null"
                                >
                                    <option value="">Unassigned</option>
                                    <option
                                        v-for="reviewer in props.bulkReview
                                            .availableReviewers"
                                        :key="reviewer.id"
                                        :value="String(reviewer.id)"
                                    >
                                        {{ reviewer.name }}
                                    </option>
                                </select>

                                <Button
                                    size="sm"
                                    :disabled="
                                        !hasSelectedDocuments ||
                                        bulkActionInFlight !== null
                                    "
                                    @click="submitBulkAction('reassign')"
                                >
                                    {{
                                        bulkActionInFlight === 'reassign'
                                            ? 'Saving...'
                                            : 'Save reviewer'
                                    }}
                                </Button>
                            </div>

                            <p
                                v-if="
                                    canBulkReassign &&
                                    props.bulkReview.availableReviewers
                                        .length === 0
                                "
                                class="doc-subtle text-xs"
                            >
                                No associates are available in this tenant yet.
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="bulkActionErrorMessage"
                        class="mt-4 rounded-2xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
                    >
                        {{ bulkActionErrorMessage }}
                    </div>

                    <div
                        v-else-if="bulkActionResult"
                        class="mt-4 rounded-2xl border border-[var(--doc-grid-line)] bg-[rgba(247,244,237,0.82)] px-4 py-3"
                    >
                        <p class="doc-title text-sm font-semibold">
                            {{ bulkActionResult.message }}
                        </p>

                        <ul
                            v-if="bulkActionResult.skipped_count > 0"
                            class="doc-subtle mt-2 space-y-1 text-xs leading-5"
                        >
                            <li
                                v-for="skippedDocument in skippedDocumentsPreview"
                                :key="`${bulkActionResult.action}-${skippedDocument.document_id}`"
                            >
                                <span class="font-semibold text-foreground">
                                    {{
                                        skippedDocument.title ??
                                        `Document #${skippedDocument.document_id}`
                                    }}
                                </span>
                                {{ skippedDocument.reason }}
                            </li>
                        </ul>

                        <p
                            v-if="hiddenSkippedCount > 0"
                            class="doc-subtle mt-2 text-xs"
                        >
                            And {{ hiddenSkippedCount }} more skipped
                            {{
                                hiddenSkippedCount === 1
                                    ? 'document'
                                    : 'documents'
                            }}.
                        </p>
                    </div>
                </div>

                <div class="grid gap-3 p-4 sm:p-5 md:hidden">
                    <article
                        v-for="document in props.documents.data"
                        :key="`mobile-${document.id}`"
                        class="doc-grid-line rounded-xl border p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <label class="flex items-center gap-2">
                                <input
                                    :checked="isDocumentSelected(document.id)"
                                    type="checkbox"
                                    class="size-4 rounded border-[var(--doc-grid-line)] text-primary focus:ring-primary"
                                    @change="
                                        onDocumentSelectionChange(
                                            document.id,
                                            $event,
                                        )
                                    "
                                />
                                <span
                                    class="doc-subtle text-xs font-semibold tracking-[0.12em] uppercase"
                                >
                                    Select
                                </span>
                            </label>

                            <DocumentStatusBadge :status="document.status" />
                        </div>

                        <div class="mt-4">
                            <Link
                                :href="DocumentController.show(document)"
                                class="doc-title text-base font-semibold hover:underline"
                            >
                                {{ document.title }}
                            </Link>

                            <p class="doc-subtle mt-2 text-xs">
                                {{ document.file_name }} •
                                {{ formatFileSize(document.file_size) }}
                            </p>

                            <p class="doc-subtle mt-1 text-xs">
                                Matter:
                                <Link
                                    v-if="document.matter"
                                    :href="
                                        MatterController.show(document.matter)
                                    "
                                    class="hover:underline"
                                >
                                    {{ document.matter.title }}
                                </Link>
                                <span v-else>—</span>
                            </p>

                            <p class="doc-subtle mt-1 text-xs">
                                Created {{ formatDate(document.created_at) }}
                            </p>
                        </div>

                        <div class="mt-4 flex items-center gap-3">
                            <a
                                :href="
                                    DocumentController.download.url(document)
                                "
                                class="doc-seal text-xs font-medium tracking-[0.12em] uppercase hover:underline"
                            >
                                Download
                            </a>
                            <Link
                                v-if="canEditDocuments"
                                :href="DocumentController.edit(document)"
                                class="doc-subtle text-xs font-medium tracking-[0.12em] uppercase hover:underline"
                            >
                                Edit
                            </Link>
                        </div>
                    </article>
                </div>

                <div class="hidden overflow-x-auto md:block">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="doc-grid-line border-b bg-muted/75">
                                <th
                                    class="w-14 px-4 py-3 text-left text-xs font-semibold tracking-[0.12em] uppercase"
                                >
                                    <span class="sr-only">Select all</span>
                                    <input
                                        :checked="allDocumentsSelected"
                                        type="checkbox"
                                        class="size-4 rounded border-[var(--doc-grid-line)] text-primary focus:ring-primary"
                                        @change="onSelectAllChange"
                                    />
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold tracking-[0.12em] uppercase"
                                >
                                    Title
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold tracking-[0.12em] uppercase"
                                >
                                    File
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold tracking-[0.12em] uppercase"
                                >
                                    Matter
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold tracking-[0.12em] uppercase"
                                >
                                    Status
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold tracking-[0.12em] uppercase"
                                >
                                    Uploader
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold tracking-[0.12em] uppercase"
                                >
                                    Created
                                </th>
                                <th
                                    class="px-4 py-3 text-right text-xs font-semibold tracking-[0.12em] uppercase"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="document in props.documents.data"
                                :key="document.id"
                                class="doc-grid-line border-b last:border-0"
                            >
                                <td class="px-4 py-3 align-top">
                                    <input
                                        :checked="
                                            isDocumentSelected(document.id)
                                        "
                                        type="checkbox"
                                        class="mt-1 size-4 rounded border-[var(--doc-grid-line)] text-primary focus:ring-primary"
                                        @change="
                                            onDocumentSelectionChange(
                                                document.id,
                                                $event,
                                            )
                                        "
                                    />
                                </td>
                                <td class="px-4 py-3">
                                    <Link
                                        :href="
                                            DocumentController.show(document)
                                        "
                                        class="doc-title text-base font-semibold hover:underline"
                                    >
                                        {{ document.title }}
                                    </Link>
                                </td>
                                <td class="doc-subtle px-4 py-3">
                                    <p>{{ document.file_name }}</p>
                                    <p class="text-xs">
                                        {{ formatFileSize(document.file_size) }}
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    <Link
                                        v-if="document.matter"
                                        :href="
                                            MatterController.show(
                                                document.matter,
                                            )
                                        "
                                        class="doc-subtle hover:underline"
                                    >
                                        {{ document.matter.title }}
                                    </Link>
                                    <span v-else class="doc-subtle">—</span>
                                </td>
                                <td class="px-4 py-3">
                                    <DocumentStatusBadge
                                        :status="document.status"
                                    />
                                </td>
                                <td class="doc-subtle px-4 py-3">
                                    {{ document.uploader?.name ?? 'System' }}
                                </td>
                                <td class="doc-subtle px-4 py-3">
                                    {{ formatDate(document.created_at) }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-3">
                                        <a
                                            :href="
                                                DocumentController.download.url(
                                                    document,
                                                )
                                            "
                                            class="doc-seal text-xs font-medium tracking-[0.12em] uppercase hover:underline"
                                        >
                                            Download
                                        </a>
                                        <Link
                                            v-if="canEditDocuments"
                                            :href="
                                                DocumentController.edit(
                                                    document,
                                                )
                                            "
                                            class="doc-subtle text-xs font-medium tracking-[0.12em] uppercase hover:underline"
                                        >
                                            Edit
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </DocumentExperienceSurface>

            <nav
                v-if="props.documents.last_page > 1"
                class="doc-fade-up doc-delay-2 mt-6 flex flex-wrap items-center justify-center gap-2"
                aria-label="Documents pagination"
            >
                <template
                    v-for="link in props.documents.links"
                    :key="link.label"
                >
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded-md border border-[var(--doc-border)] px-3 py-1.5 text-sm transition"
                        :class="
                            link.active
                                ? 'border-[var(--doc-seal)] bg-[var(--doc-seal)] text-white'
                                : 'bg-[var(--doc-paper)] hover:bg-muted'
                        "
                    >
                        <span v-html="link.label" />
                    </Link>
                    <span
                        v-else
                        class="rounded-md border border-[var(--doc-border)]/60 px-3 py-1.5 text-sm text-[var(--doc-muted)]/60"
                        v-html="link.label"
                    />
                </template>
            </nav>
        </DocumentExperienceFrame>
    </AppLayout>
</template>
