<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import ClientController from '@/actions/App/Http/Controllers/ClientController';
import DocumentEmptyState from '@/components/documents/DocumentEmptyState.vue';
import DocumentExperienceFrame from '@/components/documents/DocumentExperienceFrame.vue';
import DocumentExperienceSurface from '@/components/documents/DocumentExperienceSurface.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    type BreadcrumbItem,
    type Client,
    type DocumentExperienceGuardrails,
    type PaginatedData,
} from '@/types';

defineProps<{
    clients: PaginatedData<Client>;
    documentExperience: DocumentExperienceGuardrails;
}>();

const permissions = usePage().props.auth.permissions;
const canCreateClients = permissions.includes('create clients');
const canEditClients = permissions.includes('edit clients');

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Clients',
        href: ClientController.index.url(),
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Clients" />

        <DocumentExperienceFrame
            :document-experience="documentExperience"
            eyebrow="Relationship ledger"
            title="Client registry"
            description="Centralized client records connected to matter and document workflows."
        >
            <template #actions>
                <Button
                    v-if="canCreateClients"
                    as-child
                    class="bg-[var(--doc-seal)] text-white hover:bg-primary/90"
                >
                    <Link :href="ClientController.create()">New client</Link>
                </Button>
            </template>

            <DocumentEmptyState
                v-if="clients.data.length === 0"
                :document-experience="documentExperience"
                title="No clients added yet"
                description="Create your first client profile to start opening matters and linking documents."
                class="doc-fade-up doc-delay-1 mt-6"
            >
                <template #actions>
                    <Button v-if="canCreateClients" as-child variant="outline">
                        <Link :href="ClientController.create()">
                            Create client
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
                        v-for="client in clients.data"
                        :key="`mobile-${client.id}`"
                        class="doc-grid-line rounded-xl border p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <Link
                                :href="ClientController.show(client)"
                                class="doc-title text-base font-semibold hover:underline"
                            >
                                {{ client.name }}
                            </Link>
                            <span
                                class="doc-subtle text-xs tracking-[0.12em] uppercase"
                            >
                                {{ client.matters_count ?? 0 }} matters
                            </span>
                        </div>

                        <p class="doc-subtle mt-2 text-xs">
                            {{ client.email ?? 'No email on file' }}
                        </p>
                        <p class="doc-subtle mt-1 text-xs">
                            {{ client.company ?? 'No company listed' }}
                        </p>

                        <div class="mt-4 flex items-center gap-3">
                            <Link
                                :href="ClientController.show(client)"
                                class="doc-seal text-xs font-medium tracking-[0.12em] uppercase hover:underline"
                            >
                                Open
                            </Link>
                            <Link
                                v-if="canEditClients"
                                :href="ClientController.edit(client)"
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
                                    Name
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold tracking-[0.12em] uppercase"
                                >
                                    Email
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold tracking-[0.12em] uppercase"
                                >
                                    Company
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold tracking-[0.12em] uppercase"
                                >
                                    Matters
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
                                v-for="client in clients.data"
                                :key="client.id"
                                class="doc-grid-line border-b last:border-0"
                            >
                                <td class="px-4 py-3">
                                    <Link
                                        :href="ClientController.show(client)"
                                        class="doc-title text-base font-semibold hover:underline"
                                    >
                                        {{ client.name }}
                                    </Link>
                                </td>
                                <td class="doc-subtle px-4 py-3">
                                    {{ client.email ?? '—' }}
                                </td>
                                <td class="doc-subtle px-4 py-3">
                                    {{ client.company ?? '—' }}
                                </td>
                                <td class="doc-subtle px-4 py-3">
                                    {{ client.matters_count ?? 0 }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-3">
                                        <Link
                                            :href="
                                                ClientController.show(client)
                                            "
                                            class="doc-seal text-xs font-medium tracking-[0.12em] uppercase hover:underline"
                                        >
                                            Open
                                        </Link>
                                        <Link
                                            v-if="canEditClients"
                                            :href="
                                                ClientController.edit(client)
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
                v-if="clients.last_page > 1"
                class="doc-fade-up doc-delay-2 mt-6 flex flex-wrap items-center justify-center gap-2"
                aria-label="Clients pagination"
            >
                <template v-for="link in clients.links" :key="link.label">
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
