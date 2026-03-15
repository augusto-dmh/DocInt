<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import MatterController from '@/actions/App/Http/Controllers/MatterController';
import DocumentEmptyState from '@/components/documents/DocumentEmptyState.vue';
import DocumentExperienceFrame from '@/components/documents/DocumentExperienceFrame.vue';
import DocumentExperienceSurface from '@/components/documents/DocumentExperienceSurface.vue';
import DocumentStatusBadge from '@/components/documents/DocumentStatusBadge.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type {
    BreadcrumbItem,
    Document,
    DocumentExperienceGuardrails,
    PaginatedData,
} from '@/types';

defineProps<{
    documents: PaginatedData<Document>;
    documentExperience: DocumentExperienceGuardrails;
}>();

const permissions = usePage().props.auth.permissions;
const canEditDocuments = permissions.includes('edit documents');

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Documents',
        href: DocumentController.index.url(),
    },
];

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
                v-if="documents.data.length === 0"
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
                <div class="grid gap-3 p-4 sm:p-5 md:hidden">
                    <article
                        v-for="document in documents.data"
                        :key="`mobile-${document.id}`"
                        class="doc-grid-line rounded-xl border p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <Link
                                :href="DocumentController.show(document)"
                                class="doc-title text-base font-semibold hover:underline"
                            >
                                {{ document.title }}
                            </Link>
                            <DocumentStatusBadge :status="document.status" />
                        </div>

                        <p class="doc-subtle mt-2 text-xs">
                            {{ document.file_name }} •
                            {{ formatFileSize(document.file_size) }}
                        </p>

                        <p class="doc-subtle mt-1 text-xs">
                            Matter:
                            <Link
                                v-if="document.matter"
                                :href="MatterController.show(document.matter)"
                                class="hover:underline"
                            >
                                {{ document.matter.title }}
                            </Link>
                            <span v-else>—</span>
                        </p>

                        <p class="doc-subtle mt-1 text-xs">
                            Created {{ formatDate(document.created_at) }}
                        </p>

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
                                v-for="document in documents.data"
                                :key="document.id"
                                class="doc-grid-line border-b last:border-0"
                            >
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
                v-if="documents.last_page > 1"
                class="doc-fade-up doc-delay-2 mt-6 flex flex-wrap items-center justify-center gap-2"
                aria-label="Documents pagination"
            >
                <template v-for="link in documents.links" :key="link.label">
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
