<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import MatterController from '@/actions/App/Http/Controllers/MatterController';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AuditActivity, BreadcrumbItem, Document, Matter } from '@/types';

const props = defineProps<{
    document: Document & { matter: Matter };
    recentActivity: AuditActivity[];
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Documents',
        href: DocumentController.index.url(),
    },
    {
        title: props.document.title,
    },
];

const page = usePage();
const canEditDocument = computed(() =>
    page.props.auth.permissions.includes('edit documents'),
);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="document.title" />

        <div
            class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="space-y-2">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        {{ document.title }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Document metadata, linked matter context, and recent
                        activity.
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <Button as-child variant="outline">
                        <Link :href="DocumentController.download(document)"
                            >Download</Link
                        >
                    </Button>
                    <Button v-if="canEditDocument" as-child>
                        <Link :href="DocumentController.edit(document)"
                            >Edit</Link
                        >
                    </Button>
                </div>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 p-6">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-muted-foreground">
                            Matter
                        </dt>
                        <dd class="mt-1">
                            <Link
                                :href="MatterController.show(document.matter)"
                                class="text-foreground hover:underline"
                            >
                                {{ document.matter.title }}
                            </Link>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-muted-foreground">
                            Status
                        </dt>
                        <dd class="mt-1">
                            {{ document.status.replaceAll('_', ' ') }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-muted-foreground">
                            File name
                        </dt>
                        <dd class="mt-1">{{ document.file_name }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-muted-foreground">
                            Uploaded by
                        </dt>
                        <dd class="mt-1">
                            {{ document.uploader?.name ?? '—' }}
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-xl border border-sidebar-border/70">
                <div class="border-b border-sidebar-border/70 px-6 py-4">
                    <h2 class="text-lg font-semibold">Recent activity</h2>
                </div>

                <div
                    v-if="recentActivity.length === 0"
                    class="px-6 py-12 text-center text-sm text-muted-foreground"
                >
                    No activity has been recorded for this document yet.
                </div>

                <ul v-else class="divide-y divide-sidebar-border/70">
                    <li
                        v-for="activity in recentActivity"
                        :key="activity.id"
                        class="flex flex-wrap items-center justify-between gap-3 px-6 py-4 text-sm"
                    >
                        <div>
                            <p class="font-medium">
                                {{ activity.action.replaceAll('_', ' ') }}
                            </p>
                            <p class="text-muted-foreground">
                                {{ activity.user?.name ?? 'System' }}
                            </p>
                        </div>
                        <div class="text-right text-muted-foreground">
                            <p>
                                {{
                                    new Date(
                                        activity.created_at,
                                    ).toLocaleString()
                                }}
                            </p>
                            <p>{{ activity.ip_address ?? 'No IP recorded' }}</p>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
