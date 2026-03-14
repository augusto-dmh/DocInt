<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Document, PaginatedData } from '@/types';

defineProps<{
    documents: PaginatedData<Document>;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Documents',
        href: DocumentController.index.url(),
    },
];

const page = usePage();
const canEditDocument = computed(() =>
    page.props.auth.permissions.includes('edit documents'),
);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Documents" />

        <div
            class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
        >
            <div class="space-y-2">
                <h1 class="text-2xl font-semibold tracking-tight">Documents</h1>
                <p class="text-sm text-muted-foreground">
                    Review tenant-scoped uploads, linked matters, and storage
                    metadata.
                </p>
            </div>

            <div
                class="overflow-hidden rounded-xl border border-sidebar-border/70"
            >
                <div
                    v-if="documents.data.length === 0"
                    class="flex min-h-64 items-center justify-center px-6 py-12 text-center text-sm text-muted-foreground"
                >
                    No documents have been uploaded yet.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b border-sidebar-border/70 bg-muted/40"
                            >
                                <th class="px-4 py-3 text-left font-medium">
                                    Title
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    Matter
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    File
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    Status
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="document in documents.data"
                                :key="document.id"
                                class="border-b border-sidebar-border/70 last:border-0"
                            >
                                <td class="px-4 py-3">
                                    <Link
                                        :href="
                                            DocumentController.show(document)
                                        "
                                        class="font-medium text-foreground hover:underline"
                                    >
                                        {{ document.title }}
                                    </Link>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ document.matter?.title ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ document.file_name }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ document.status.replaceAll('_', ' ') }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-3">
                                        <Link
                                            :href="
                                                DocumentController.show(
                                                    document,
                                                )
                                            "
                                            class="text-sm text-muted-foreground hover:text-foreground"
                                        >
                                            Open
                                        </Link>
                                        <Link
                                            v-if="canEditDocument"
                                            :href="
                                                DocumentController.edit(
                                                    document,
                                                )
                                            "
                                            class="text-sm text-muted-foreground hover:text-foreground"
                                        >
                                            Edit
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div
                v-if="documents.last_page > 1"
                class="flex flex-wrap items-center justify-center gap-2"
            >
                <template v-for="link in documents.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded-md px-3 py-1.5 text-sm transition"
                        :class="
                            link.active
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted text-muted-foreground hover:bg-muted/80 hover:text-foreground'
                        "
                    >
                        <span v-html="link.label" />
                    </Link>
                    <span
                        v-else
                        class="px-3 py-1.5 text-sm text-muted-foreground/50"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </AppLayout>
</template>
