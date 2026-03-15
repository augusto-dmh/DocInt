<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import ClientController from '@/actions/App/Http/Controllers/ClientController';
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import MatterController from '@/actions/App/Http/Controllers/MatterController';
import DocumentEmptyState from '@/components/documents/DocumentEmptyState.vue';
import DocumentExperienceFrame from '@/components/documents/DocumentExperienceFrame.vue';
import DocumentExperienceSurface from '@/components/documents/DocumentExperienceSurface.vue';
import DocumentStatusBadge from '@/components/documents/DocumentStatusBadge.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { matterStatusToneClass } from '@/lib/document-experience';
import type {
    BreadcrumbItem,
    Client,
    Document,
    DocumentExperienceGuardrails,
    Matter,
} from '@/types';

const props = defineProps<{
    matter: Matter & { client: Client; documents: Document[] };
    documentExperience: DocumentExperienceGuardrails;
}>();

const canEditMatter = usePage().props.auth.permissions.includes('edit matters');
const canCreateDocuments =
    usePage().props.auth.permissions.includes('create documents');

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Matters',
        href: MatterController.index.url(),
    },
    {
        title: props.matter.title,
    },
];

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(value));
}

function matterStatusClass(status: Matter['status']): string {
    return matterStatusToneClass(status);
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="matter.title" />

        <DocumentExperienceFrame
            :document-experience="documentExperience"
            eyebrow="Matter workspace"
            :title="matter.title"
        >
            <template #description>
                <span class="inline-flex items-center gap-2">
                    <span class="doc-subtle text-sm">Matter status</span>
                    <span
                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="matterStatusClass(matter.status)"
                    >
                        {{ matter.status.replace('_', ' ') }}
                    </span>
                </span>
            </template>

            <template #actions>
                <Button v-if="canEditMatter" as-child variant="outline">
                    <Link :href="MatterController.edit(matter)">Edit</Link>
                </Button>
                <Button v-if="canCreateDocuments" as-child>
                    <Link :href="DocumentController.create(matter)">
                        Upload Document
                    </Link>
                </Button>
            </template>

            <DocumentExperienceSurface
                :document-experience="documentExperience"
                :delay="1"
                class="mt-6 p-6"
            >
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt
                            class="doc-subtle text-sm font-medium tracking-[0.08em] uppercase"
                        >
                            Client
                        </dt>
                        <dd class="mt-1">
                            <Link
                                :href="ClientController.show(matter.client)"
                                class="doc-title text-base font-semibold hover:underline"
                            >
                                {{ matter.client.name }}
                            </Link>
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="doc-subtle text-sm font-medium tracking-[0.08em] uppercase"
                        >
                            Reference number
                        </dt>
                        <dd class="mt-1">
                            {{ matter.reference_number ?? '—' }}
                        </dd>
                    </div>

                    <div v-if="matter.description" class="sm:col-span-2">
                        <dt
                            class="doc-subtle text-sm font-medium tracking-[0.08em] uppercase"
                        >
                            Description
                        </dt>
                        <dd class="doc-subtle mt-1 whitespace-pre-line">
                            {{ matter.description }}
                        </dd>
                    </div>
                </dl>
            </DocumentExperienceSurface>

            <DocumentExperienceSurface
                :document-experience="documentExperience"
                :delay="2"
                class="mt-6 p-4 sm:p-5"
            >
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="doc-title text-xl font-semibold">
                            Documents
                        </h2>
                        <p class="doc-subtle mt-1 text-sm">
                            Archived files linked to this matter.
                        </p>
                    </div>

                    <span
                        class="doc-subtle text-xs tracking-[0.12em] uppercase"
                    >
                        {{ matter.documents.length }} linked
                    </span>
                </div>

                <DocumentEmptyState
                    v-if="matter.documents.length === 0"
                    :document-experience="documentExperience"
                    title="No documents linked"
                    description="Use upload to add supporting files and keep this matter archive complete."
                >
                    <template #actions>
                        <Button v-if="canCreateDocuments" as-child>
                            <Link :href="DocumentController.create(matter)">
                                Upload first document
                            </Link>
                        </Button>
                    </template>
                </DocumentEmptyState>

                <div v-else class="space-y-4">
                    <div class="grid gap-3 md:hidden">
                        <article
                            v-for="document in matter.documents"
                            :key="`matter-mobile-${document.id}`"
                            class="rounded-xl border border-[var(--doc-border)]/75 bg-[var(--doc-paper)]/65 p-4"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <Link
                                    :href="DocumentController.show(document)"
                                    class="doc-title text-base font-semibold hover:underline"
                                >
                                    {{ document.title }}
                                </Link>
                                <DocumentStatusBadge
                                    :status="document.status"
                                />
                            </div>

                            <p class="doc-subtle mt-2 text-xs">
                                {{ document.file_name }}
                            </p>
                            <p class="doc-subtle mt-1 text-xs">
                                Uploaded {{ formatDate(document.created_at) }}
                            </p>
                            <p class="doc-subtle mt-1 text-xs">
                                {{ document.uploader?.name ?? 'System' }}
                            </p>
                        </article>
                    </div>

                    <div class="hidden overflow-x-auto md:block">
                        <table class="w-full text-sm">
                            <thead>
                                <tr
                                    class="border-b border-[var(--doc-border)]/75 bg-muted/70"
                                >
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
                                        Uploaded
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="document in matter.documents"
                                    :key="document.id"
                                    class="border-b border-[var(--doc-border)]/70 last:border-0"
                                >
                                    <td class="px-4 py-3 font-medium">
                                        <Link
                                            :href="
                                                DocumentController.show(
                                                    document,
                                                )
                                            "
                                            class="doc-title text-base font-semibold hover:underline"
                                        >
                                            {{ document.title }}
                                        </Link>
                                    </td>
                                    <td class="doc-subtle px-4 py-3">
                                        {{ document.file_name }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <DocumentStatusBadge
                                            :status="document.status"
                                        />
                                    </td>
                                    <td class="doc-subtle px-4 py-3">
                                        {{
                                            document.uploader?.name ?? 'System'
                                        }}
                                    </td>
                                    <td class="doc-subtle px-4 py-3">
                                        {{ formatDate(document.created_at) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </DocumentExperienceSurface>
        </DocumentExperienceFrame>
    </AppLayout>
</template>
