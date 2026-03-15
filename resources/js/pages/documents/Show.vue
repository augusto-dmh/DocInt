<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import DocumentExperienceFrame from '@/components/documents/DocumentExperienceFrame.vue';
import DocumentExperienceSurface from '@/components/documents/DocumentExperienceSurface.vue';
import DocumentStatusBadge from '@/components/documents/DocumentStatusBadge.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type {
    AuditActivity,
    BreadcrumbItem,
    Document,
    DocumentExperienceGuardrails,
    Matter,
} from '@/types';
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import MatterController from '@/actions/App/Http/Controllers/MatterController';

const props = defineProps<{
    document: Document & { matter: Matter };
    recentActivity: AuditActivity[];
    documentExperience: DocumentExperienceGuardrails;
}>();

const canEditDocument =
    usePage().props.auth.permissions.includes('edit documents');

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

    if (action === 'updated') {
        return 'Document updated';
    }

    if (action === 'deleted') {
        return 'Document deleted';
    }

    return action.replaceAll('_', ' ');
}
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
                    as-child
                    class="bg-[var(--doc-seal)] text-white hover:bg-primary/90"
                >
                    <a :href="DocumentController.download.url(document)">
                        Download
                    </a>
                </Button>

                <Button v-if="canEditDocument" as-child variant="outline">
                    <Link :href="DocumentController.edit(document)">
                        Edit metadata
                    </Link>
                </Button>
            </template>

            <div class="mt-6 grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
                <DocumentExperienceSurface
                    :document-experience="documentExperience"
                    :delay="1"
                    class="p-6 sm:p-8"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p
                                class="doc-seal text-xs font-semibold tracking-[0.12em] uppercase"
                            >
                                Archive card
                            </p>
                            <h2 class="doc-title mt-2 text-2xl font-semibold">
                                {{ document.file_name }}
                            </h2>
                        </div>

                        <DocumentStatusBadge :status="document.status" />
                    </div>

                    <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                        <div
                            class="rounded-2xl border border-[var(--doc-border)]/70 bg-[var(--doc-paper)]/72 p-4"
                        >
                            <dt
                                class="doc-subtle text-[11px] font-semibold tracking-[0.12em] uppercase"
                            >
                                Matter
                            </dt>
                            <dd class="mt-2">
                                <Link
                                    :href="
                                        MatterController.show(document.matter)
                                    "
                                    class="doc-title text-base font-semibold hover:underline"
                                >
                                    {{ document.matter.title }}
                                </Link>
                            </dd>
                        </div>

                        <div
                            class="rounded-2xl border border-[var(--doc-border)]/70 bg-[var(--doc-paper)]/72 p-4"
                        >
                            <dt
                                class="doc-subtle text-[11px] font-semibold tracking-[0.12em] uppercase"
                            >
                                Uploaded by
                            </dt>
                            <dd class="doc-title mt-2 text-base font-semibold">
                                {{ document.uploader?.name ?? 'System' }}
                            </dd>
                        </div>

                        <div
                            class="rounded-2xl border border-[var(--doc-border)]/70 bg-[var(--doc-paper)]/72 p-4"
                        >
                            <dt
                                class="doc-subtle text-[11px] font-semibold tracking-[0.12em] uppercase"
                            >
                                File size
                            </dt>
                            <dd class="doc-title mt-2 text-base font-semibold">
                                {{ formatFileSize(document.file_size) }}
                            </dd>
                        </div>

                        <div
                            class="rounded-2xl border border-[var(--doc-border)]/70 bg-[var(--doc-paper)]/72 p-4"
                        >
                            <dt
                                class="doc-subtle text-[11px] font-semibold tracking-[0.12em] uppercase"
                            >
                                Uploaded
                            </dt>
                            <dd class="doc-title mt-2 text-base font-semibold">
                                {{ formatDate(document.created_at) }}
                            </dd>
                        </div>
                    </dl>
                </DocumentExperienceSurface>

                <DocumentExperienceSurface
                    :document-experience="documentExperience"
                    :delay="2"
                    class="p-6"
                >
                    <p
                        class="doc-seal text-xs font-semibold tracking-[0.12em] uppercase"
                    >
                        Recent activity
                    </p>
                    <h2 class="doc-title mt-2 text-2xl font-semibold">
                        Audit timeline
                    </h2>

                    <div
                        v-if="recentActivity.length === 0"
                        class="doc-subtle mt-6 rounded-2xl border border-dashed border-[var(--doc-border)]/80 px-4 py-8 text-center text-sm"
                    >
                        No activity has been recorded for this document yet.
                    </div>

                    <ul v-else class="mt-6 space-y-4">
                        <li
                            v-for="activity in recentActivity"
                            :key="activity.id"
                            class="rounded-2xl border border-[var(--doc-border)]/70 bg-[var(--doc-paper)]/72 p-4"
                        >
                            <div
                                class="flex flex-wrap items-start justify-between gap-3"
                            >
                                <div>
                                    <p
                                        class="doc-title text-base font-semibold"
                                    >
                                        {{ activityLabel(activity.action) }}
                                    </p>
                                    <p class="doc-subtle mt-1 text-sm">
                                        {{ activity.user?.name ?? 'System' }}
                                    </p>
                                </div>

                                <div class="text-right">
                                    <p class="doc-subtle text-sm">
                                        {{
                                            formatDateTime(activity.created_at)
                                        }}
                                    </p>
                                    <p class="doc-subtle mt-1 text-xs">
                                        {{
                                            activity.ip_address ??
                                            'No IP recorded'
                                        }}
                                    </p>
                                </div>
                            </div>
                        </li>
                    </ul>
                </DocumentExperienceSurface>
            </div>
        </DocumentExperienceFrame>
    </AppLayout>
</template>
