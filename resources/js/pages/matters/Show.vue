<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Client, Document, Matter } from '@/types';
import ClientController from '@/actions/App/Http/Controllers/ClientController';
import MatterController from '@/actions/App/Http/Controllers/MatterController';

const props = defineProps<{
    matter: Matter & { client: Client; documents: Document[] };
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Matters',
        href: MatterController.index.url(),
    },
    {
        title: props.matter.title,
    },
];

function matterStatusClass(status: Matter['status']): string {
    if (status === 'open') {
        return 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300';
    }

    if (status === 'closed') {
        return 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300';
    }

    return 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300';
}

function documentStatusClass(status: Document['status']): string {
    if (status === 'uploaded') {
        return 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300';
    }

    if (status === 'approved') {
        return 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300';
    }

    return 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300';
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="matter.title" />

        <div
            class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="text-2xl font-semibold tracking-tight">
                            {{ matter.title }}
                        </h1>
                        <span
                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="matterStatusClass(matter.status)"
                        >
                            {{ matter.status.replace('_', ' ') }}
                        </span>
                    </div>

                    <p class="text-sm text-muted-foreground">
                        Matter details and linked documents for the active
                        tenant.
                    </p>
                </div>

                <Button as-child variant="outline">
                    <Link :href="MatterController.edit(matter)">Edit</Link>
                </Button>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 p-6">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-muted-foreground">
                            Client
                        </dt>
                        <dd class="mt-1">
                            <Link
                                :href="ClientController.show(matter.client)"
                                class="text-foreground hover:underline"
                            >
                                {{ matter.client.name }}
                            </Link>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-muted-foreground">
                            Reference Number
                        </dt>
                        <dd class="mt-1">
                            {{ matter.reference_number ?? '—' }}
                        </dd>
                    </div>

                    <div v-if="matter.description" class="sm:col-span-2">
                        <dt class="text-sm font-medium text-muted-foreground">
                            Description
                        </dt>
                        <dd class="mt-1 whitespace-pre-line">
                            {{ matter.description }}
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-xl border border-sidebar-border/70">
                <div
                    class="flex items-center justify-between border-b border-sidebar-border/70 px-6 py-4"
                >
                    <div>
                        <h2 class="text-lg font-semibold">Documents</h2>
                        <p class="text-sm text-muted-foreground">
                            Placeholder table for the upcoming document
                            workflow.
                        </p>
                    </div>
                    <span class="text-sm text-muted-foreground"
                        >{{ matter.documents.length }} linked</span
                    >
                </div>

                <div
                    v-if="matter.documents.length === 0"
                    class="px-6 py-12 text-center text-sm text-muted-foreground"
                >
                    No documents uploaded to this matter yet.
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
                                    File
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    Status
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="document in matter.documents"
                                :key="document.id"
                                class="border-b border-sidebar-border/70 last:border-0"
                            >
                                <td class="px-4 py-3 font-medium">
                                    {{ document.title }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ document.file_name }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="
                                            documentStatusClass(document.status)
                                        "
                                    >
                                        {{
                                            document.status.replaceAll('_', ' ')
                                        }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
