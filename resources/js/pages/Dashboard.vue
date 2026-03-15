<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowRight, Briefcase, FileText, Users } from 'lucide-vue-next';
import { ref } from 'vue';
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import DocumentStatusBadge from '@/components/documents/DocumentStatusBadge.vue';
import { useDocumentChannel } from '@/composables/useDocumentChannel';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { index as clientsIndex } from '@/routes/clients';
import { index as documentsIndex } from '@/routes/documents';
import { index as mattersIndex } from '@/routes/matters';
import type {
    BreadcrumbItem,
    DashboardRecentDocument,
    DashboardStats,
} from '@/types';

const props = defineProps<{
    realtimeTenantId: string | null;
    stats: DashboardStats;
    recentDocuments: DashboardRecentDocument[];
}>();

const page = usePage();
const tenant = page.props.tenant;
const isReloadingDashboard = ref(false);
const hasPendingDashboardReload = ref(false);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const quickLinks = [
    {
        title: 'Clients',
        description: 'Manage contacts and engagement records.',
        href: clientsIndex(),
        icon: Users,
    },
    {
        title: 'Matters',
        description: 'Track case progress, ownership, and status.',
        href: mattersIndex(),
        icon: Briefcase,
    },
    {
        title: 'Documents',
        description: 'Review uploads, approvals, and audit activity.',
        href: documentsIndex(),
        icon: FileText,
    },
];

function formatUpdatedAt(value: string): string {
    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));
}

function reloadDashboardSnapshot(): void {
    if (isReloadingDashboard.value) {
        hasPendingDashboardReload.value = true;

        return;
    }

    isReloadingDashboard.value = true;

    router.reload({
        only: ['stats', 'recentDocuments'],
        onFinish: () => {
            isReloadingDashboard.value = false;

            if (!hasPendingDashboardReload.value) {
                return;
            }

            hasPendingDashboardReload.value = false;
            reloadDashboardSnapshot();
        },
    });
}

useDocumentChannel({
    tenantId: props.realtimeTenantId,
    onStatusUpdated: () => {
        reloadDashboardSnapshot();
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <section class="workspace-hero p-6 sm:p-8">
            <p
                class="doc-seal text-xs font-semibold tracking-[0.16em] uppercase"
            >
                Operations center
            </p>
            <h1 class="doc-title mt-2 text-3xl font-semibold sm:text-4xl">
                {{ tenant?.name ?? 'Docintern workspace' }}
            </h1>
            <p class="doc-subtle mt-3 max-w-3xl text-sm sm:text-base">
                Run client, matter, and document workflows from one secure
                tenant-scoped surface.
            </p>
        </section>

        <div class="mt-6 grid gap-4 md:grid-cols-3">
            <article
                v-for="item in quickLinks"
                :key="item.title"
                class="workspace-panel workspace-fade-up p-5"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="doc-title text-xl font-semibold">
                            {{ item.title }}
                        </p>
                        <p class="doc-subtle mt-2 text-sm">
                            {{ item.description }}
                        </p>
                    </div>
                    <component
                        :is="item.icon"
                        class="size-5 text-[var(--doc-seal)]"
                    />
                </div>

                <Link
                    :href="item.href"
                    class="doc-seal mt-5 inline-flex items-center gap-2 text-xs font-semibold tracking-[0.12em] uppercase hover:underline"
                >
                    Open
                    <ArrowRight class="size-4" />
                </Link>
            </article>
        </div>

        <section
            class="workspace-panel workspace-fade-up workspace-delay-1 mt-6 p-6 sm:p-8"
        >
            <div
                class="flex flex-col gap-4 border-b border-[color:var(--doc-border)]/70 pb-6 sm:flex-row sm:items-start sm:justify-between"
            >
                <div>
                    <h2 class="doc-title text-2xl font-semibold">
                        Live processing snapshot
                    </h2>
                    <p class="doc-subtle mt-2 max-w-2xl text-sm">
                        Tenant-scoped document throughput and the most recent
                        processing movements currently visible in this
                        workspace.
                    </p>
                </div>

                <Link
                    :href="documentsIndex()"
                    class="doc-seal inline-flex items-center gap-2 text-xs font-semibold tracking-[0.12em] uppercase hover:underline"
                >
                    Open documents
                    <ArrowRight class="size-4" />
                </Link>
            </div>

            <div class="mt-6 grid gap-3 sm:grid-cols-3">
                <div class="workspace-kpi">
                    <p class="workspace-label">Processed today</p>
                    <p class="workspace-kpi-value mt-2">
                        {{ props.stats.processed_today }}
                    </p>
                </div>
                <div class="workspace-kpi">
                    <p class="workspace-label">Pending review</p>
                    <p class="workspace-kpi-value mt-2">
                        {{ props.stats.pending_review }}
                    </p>
                </div>
                <div class="workspace-kpi">
                    <p class="workspace-label">Failed documents</p>
                    <p class="workspace-kpi-value mt-2">
                        {{ props.stats.failed }}
                    </p>
                </div>
            </div>

            <div class="mt-6">
                <div
                    class="mb-4 flex flex-wrap items-center justify-between gap-2"
                >
                    <h3 class="doc-title text-lg font-semibold">
                        Recent document activity
                    </h3>
                    <span
                        class="doc-subtle text-xs font-semibold tracking-[0.12em] uppercase"
                    >
                        {{ props.recentDocuments.length }} visible
                    </span>
                </div>

                <div
                    v-if="props.realtimeTenantId === null"
                    class="doc-grid-line rounded-2xl border border-dashed p-5"
                >
                    <p class="doc-title text-sm font-semibold">
                        Select a tenant context to load dashboard activity
                    </p>
                    <p class="doc-subtle mt-2 text-sm">
                        Super-admin access is available, but tenant-scoped
                        dashboard metrics stay disabled until a workspace
                        context is selected.
                    </p>
                </div>

                <div
                    v-else-if="props.recentDocuments.length === 0"
                    class="doc-grid-line rounded-2xl border border-dashed p-5"
                >
                    <p class="doc-title text-sm font-semibold">
                        No recent documents yet
                    </p>
                    <p class="doc-subtle mt-2 text-sm">
                        Upload the first matter document to start tracking live
                        intake and review activity here.
                    </p>
                </div>

                <ol v-else class="space-y-3">
                    <li
                        v-for="document in props.recentDocuments"
                        :key="document.id"
                        class="doc-grid-line rounded-2xl border p-4"
                    >
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div class="min-w-0">
                                <Link
                                    :href="DocumentController.show(document.id)"
                                    class="doc-title block truncate text-sm font-semibold hover:underline sm:text-base"
                                >
                                    {{ document.title }}
                                </Link>
                                <p class="doc-subtle mt-1 text-xs sm:text-sm">
                                    {{
                                        document.matter_title ??
                                        'No matter linked'
                                    }}
                                </p>
                            </div>

                            <div
                                class="flex flex-wrap items-center gap-3 sm:justify-end"
                            >
                                <DocumentStatusBadge
                                    :status="document.status"
                                />
                                <span class="doc-subtle text-xs">
                                    {{ formatUpdatedAt(document.updated_at) }}
                                </span>
                            </div>
                        </div>
                    </li>
                </ol>
            </div>
        </section>

        <section
            class="workspace-panel workspace-fade-up workspace-delay-2 mt-6 p-6 sm:p-8"
        >
            <h2 class="doc-title text-2xl font-semibold">Workflow posture</h2>
            <p class="doc-subtle mt-3 text-sm">
                Keep intake quality high by reviewing new uploads daily,
                promoting approved documents, and validating tenant context
                before cross-workspace work.
            </p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="workspace-kpi">
                    <p class="workspace-kpi-value">Client intake</p>
                    <p class="workspace-kpi-label">
                        Maintain complete contact data before opening matters.
                    </p>
                </div>
                <div class="workspace-kpi">
                    <p class="workspace-kpi-value">Matter hygiene</p>
                    <p class="workspace-kpi-label">
                        Keep status and reference numbers current.
                    </p>
                </div>
                <div class="workspace-kpi">
                    <p class="workspace-kpi-value">Document review</p>
                    <p class="workspace-kpi-label">
                        Move files from uploaded to approved quickly.
                    </p>
                </div>
                <div class="workspace-kpi">
                    <p class="workspace-kpi-value">Audit readiness</p>
                    <p class="workspace-kpi-label">
                        Verify timeline entries for critical files.
                    </p>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
