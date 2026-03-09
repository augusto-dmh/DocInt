<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Client, PaginatedData } from '@/types';
import ClientController from '@/actions/App/Http/Controllers/ClientController';

defineProps<{
    clients: PaginatedData<Client>;
}>();

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

        <div
            class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">
                        Clients
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Manage the people and organizations connected to your
                        tenant.
                    </p>
                </div>

                <Button as-child>
                    <Link :href="ClientController.create()">New Client</Link>
                </Button>
            </div>

            <div
                class="overflow-hidden rounded-xl border border-sidebar-border/70"
            >
                <div
                    v-if="clients.data.length === 0"
                    class="flex min-h-64 items-center justify-center px-6 py-12 text-center text-sm text-muted-foreground"
                >
                    No clients found. Create the first client record to get
                    started.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b border-sidebar-border/70 bg-muted/40"
                            >
                                <th class="px-4 py-3 text-left font-medium">
                                    Name
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    Email
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    Company
                                </th>
                                <th class="px-4 py-3 text-left font-medium">
                                    Matters
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="client in clients.data"
                                :key="client.id"
                                class="border-b border-sidebar-border/70 last:border-0"
                            >
                                <td class="px-4 py-3">
                                    <Link
                                        :href="ClientController.show(client)"
                                        class="font-medium text-foreground hover:underline"
                                    >
                                        {{ client.name }}
                                    </Link>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ client.email ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ client.company ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ client.matters_count ?? 0 }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-3">
                                        <Link
                                            :href="
                                                ClientController.show(client)
                                            "
                                            class="text-sm text-muted-foreground hover:text-foreground"
                                        >
                                            Open
                                        </Link>
                                        <Link
                                            :href="
                                                ClientController.edit(client)
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
                v-if="clients.last_page > 1"
                class="flex flex-wrap items-center justify-center gap-2"
            >
                <template v-for="link in clients.links" :key="link.label">
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
