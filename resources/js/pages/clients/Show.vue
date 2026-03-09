<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Client, Matter } from '@/types';
import ClientController from '@/actions/App/Http/Controllers/ClientController';
import MatterController from '@/actions/App/Http/Controllers/MatterController';

const props = defineProps<{
    client: Client & { matters: Matter[] };
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Clients',
        href: ClientController.index.url(),
    },
    {
        title: props.client.name,
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
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="client.name" />

        <div
            class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">
                        {{ client.name }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Client record and linked matters for the active tenant.
                    </p>
                </div>

                <Button as-child variant="outline">
                    <Link :href="ClientController.edit(client)">Edit</Link>
                </Button>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 p-6">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-muted-foreground">
                            Email
                        </dt>
                        <dd class="mt-1">{{ client.email ?? '—' }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-muted-foreground">
                            Phone
                        </dt>
                        <dd class="mt-1">{{ client.phone ?? '—' }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-muted-foreground">
                            Company
                        </dt>
                        <dd class="mt-1">{{ client.company ?? '—' }}</dd>
                    </div>

                    <div v-if="client.notes" class="sm:col-span-2">
                        <dt class="text-sm font-medium text-muted-foreground">
                            Notes
                        </dt>
                        <dd class="mt-1 whitespace-pre-line">
                            {{ client.notes }}
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-xl border border-sidebar-border/70">
                <div
                    class="flex items-center justify-between border-b border-sidebar-border/70 px-6 py-4"
                >
                    <div>
                        <h2 class="text-lg font-semibold">Matters</h2>
                        <p class="text-sm text-muted-foreground">
                            Matters associated with this client.
                        </p>
                    </div>
                    <span class="text-sm text-muted-foreground"
                        >{{ client.matters.length }} linked</span
                    >
                </div>

                <div
                    v-if="client.matters.length === 0"
                    class="px-6 py-12 text-center text-sm text-muted-foreground"
                >
                    No matters linked to this client yet.
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
                                    Reference
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    Documents
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    Status
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="matter in client.matters"
                                :key="matter.id"
                                class="border-b border-sidebar-border/70 last:border-0"
                            >
                                <td class="px-4 py-3">
                                    <Link
                                        :href="MatterController.show(matter)"
                                        class="font-medium text-foreground hover:underline"
                                    >
                                        {{ matter.title }}
                                    </Link>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ matter.reference_number ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
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
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
