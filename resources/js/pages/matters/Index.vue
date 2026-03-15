<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import MatterController from '@/actions/App/Http/Controllers/MatterController';
import DocumentEmptyState from '@/components/documents/DocumentEmptyState.vue';
import DocumentExperienceFrame from '@/components/documents/DocumentExperienceFrame.vue';
import DocumentExperienceSurface from '@/components/documents/DocumentExperienceSurface.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { matterStatusToneClass } from '@/lib/document-experience';
import {
    type BreadcrumbItem,
    type DocumentExperienceGuardrails,
    type Matter,
    type PaginatedData,
} from '@/types';

defineProps<{
    matters: PaginatedData<Matter>;
    documentExperience: DocumentExperienceGuardrails;
}>();

const permissions = usePage().props.auth.permissions;
const canCreateMatters = permissions.includes('create matters');
const canEditMatters = permissions.includes('edit matters');

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Matters',
        href: MatterController.index.url(),
    },
];

function matterStatusClass(status: Matter['status']): string {
    return matterStatusToneClass(status);
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Matters" />

        <DocumentExperienceFrame
            :document-experience="documentExperience"
            eyebrow="Case ledger"
            title="Matter registry"
            description="Track active legal matters with quick access to related document counts and status."
        >
            <template #actions>
                <Button
                    v-if="canCreateMatters"
                    as-child
                    class="bg-[var(--doc-seal)] text-white hover:bg-primary/90"
                >
                    <Link :href="MatterController.create()">New matter</Link>
                </Button>
            </template>

            <DocumentEmptyState
                v-if="matters.data.length === 0"
                :document-experience="documentExperience"
                title="No matters opened yet"
                description="Create a matter to connect client context and begin collecting documents."
                class="doc-fade-up doc-delay-1 mt-6"
            >
                <template #actions>
                    <Button v-if="canCreateMatters" as-child variant="outline">
                        <Link :href="MatterController.create()">
                            Create matter
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
                        v-for="matter in matters.data"
                        :key="`mobile-${matter.id}`"
                        class="doc-grid-line rounded-xl border p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <Link
                                :href="MatterController.show(matter)"
                                class="doc-title text-base font-semibold hover:underline"
                            >
                                {{ matter.title }}
                            </Link>
                            <span
                                class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="matterStatusClass(matter.status)"
                            >
                                {{ matter.status.replace('_', ' ') }}
                            </span>
                        </div>

                        <p class="doc-subtle mt-2 text-xs">
                            Client: {{ matter.client?.name ?? '—' }}
                        </p>
                        <p class="doc-subtle mt-1 text-xs">
                            Ref: {{ matter.reference_number ?? '—' }}
                        </p>
                        <p class="doc-subtle mt-1 text-xs">
                            {{ matter.documents_count ?? 0 }} documents
                        </p>

                        <div class="mt-4 flex items-center gap-3">
                            <Link
                                :href="MatterController.show(matter)"
                                class="doc-seal text-xs font-medium tracking-[0.12em] uppercase hover:underline"
                            >
                                Open
                            </Link>
                            <Link
                                v-if="canEditMatters"
                                :href="MatterController.edit(matter)"
                                class="doc-subtle text-xs font-medium tracking-[0.12em] uppercase hover:underline"
                            >
                                Edit
                            </Link>
                        </div>
                    </article>
                </div>

                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="doc-grid-line border-b bg-muted/75"
                            >
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold tracking-[0.12em] uppercase"
                                >
                                    Title
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold tracking-[0.12em] uppercase"
                                >
                                    Client
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold tracking-[0.12em] uppercase"
                                >
                                    Reference
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold tracking-[0.12em] uppercase"
                                >
                                    Documents
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold tracking-[0.12em] uppercase"
                                >
                                    Status
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
                                v-for="matter in matters.data"
                                :key="matter.id"
                                class="doc-grid-line border-b last:border-0"
                            >
                                <td class="px-4 py-3">
                                    <Link
                                        :href="MatterController.show(matter)"
                                        class="doc-title text-base font-semibold hover:underline"
                                    >
                                        {{ matter.title }}
                                    </Link>
                                </td>
                                <td class="doc-subtle px-4 py-3">
                                    {{ matter.client?.name ?? '—' }}
                                </td>
                                <td class="doc-subtle px-4 py-3">
                                    {{ matter.reference_number ?? '—' }}
                                </td>
                                <td class="doc-subtle px-4 py-3">
                                    {{ matter.documents_count ?? 0 }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="
                                            matterStatusClass(matter.status)
                                        "
                                    >
                                        {{ matter.status.replace('_', ' ') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-3">
                                        <Link
                                            :href="
                                                MatterController.show(matter)
                                            "
                                            class="doc-seal text-xs font-medium tracking-[0.12em] uppercase hover:underline"
                                        >
                                            Open
                                        </Link>
                                        <Link
                                            v-if="canEditMatters"
                                            :href="
                                                MatterController.edit(matter)
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
                v-if="matters.last_page > 1"
                class="doc-fade-up doc-delay-2 mt-6 flex flex-wrap items-center justify-center gap-2"
                aria-label="Matters pagination"
            >
                <template v-for="link in matters.links" :key="link.label">
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
