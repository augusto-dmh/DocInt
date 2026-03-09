<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import MatterController from '@/actions/App/Http/Controllers/MatterController';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Matter, PaginatedData } from '@/types';

defineProps<{
    matters: PaginatedData<Matter>;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Matters',
        href: MatterController.index.url(),
    },
];

const page = usePage();
const canCreateMatter = computed(() =>
    page.props.auth.permissions.includes('create matters'),
);
const canEditMatter = computed(() =>
    page.props.auth.permissions.includes('edit matters'),
);

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
        <Head title="Matters" />

        <div
            class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">
                        Matters
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Track tenant-scoped matters and the documents attached
                        to each one.
                    </p>
                </div>

                <Button v-if="canCreateMatter" as-child>
                    <Link :href="MatterController.create()">New Matter</Link>
                </Button>
            </div>

            <div
                class="overflow-hidden rounded-xl border border-sidebar-border/70"
            >
                <div
                    v-if="matters.data.length === 0"
                    class="flex min-h-64 items-center justify-center px-6 py-12 text-center text-sm text-muted-foreground"
                >
                    No matters found. Create the first matter to start
                    organizing documents.
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
                                    Client
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
                                <th class="px-4 py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="matter in matters.data"
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
                                    {{ matter.client?.name ?? '—' }}
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
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-3">
                                        <Link
                                            :href="
                                                MatterController.show(matter)
                                            "
                                            class="text-sm text-muted-foreground hover:text-foreground"
                                        >
                                            Open
                                        </Link>
                                        <Link
                                            v-if="canEditMatter"
                                            :href="
                                                MatterController.edit(matter)
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
                v-if="matters.last_page > 1"
                class="flex flex-wrap items-center justify-center gap-2"
            >
                <template v-for="link in matters.links" :key="link.label">
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
