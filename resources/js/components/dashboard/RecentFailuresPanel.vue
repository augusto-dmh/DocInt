<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight, FolderClock } from 'lucide-vue-next';
import { show } from '@/actions/App/Http/Controllers/DocumentController';
import DocumentStatusBadge from '@/components/documents/DocumentStatusBadge.vue';
import type { DashboardFailureDocument } from '@/types';

defineProps<{
    failures: DashboardFailureDocument[];
}>();

function formatDateTime(value: string): string {
    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));
}
</script>

<template>
    <section
        class="workspace-panel workspace-fade-up workspace-delay-2 mt-5 overflow-hidden p-6"
    >
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p
                    class="doc-seal text-xs font-semibold tracking-[0.16em] uppercase"
                >
                    Failure surface
                </p>
                <h3 class="doc-title mt-2 text-xl font-semibold">
                    Recent pipeline interruptions
                </h3>
                <p class="doc-subtle mt-2 max-w-3xl text-sm">
                    Track the latest failed documents without leaving the
                    operations view.
                </p>
            </div>
            <span class="workspace-status-pill workspace-status-pill--warning">
                {{ failures.length }} active
            </span>
        </div>

        <div
            v-if="failures.length === 0"
            class="workspace-failure-empty mt-5 flex items-start gap-4 rounded-[1.1rem] border border-dashed border-[var(--doc-border)]/85 p-5"
        >
            <div class="workspace-failure-empty-icon">
                <FolderClock class="size-5" />
            </div>
            <div>
                <p class="doc-title text-base font-semibold">
                    No failed documents in the current tenant snapshot
                </p>
                <p class="doc-subtle mt-2 text-sm">
                    When a document stops in scan, extraction, or
                    classification, it will appear here instantly.
                </p>
            </div>
        </div>

        <div v-else class="mt-5 grid gap-3">
            <article
                v-for="failure in failures"
                :key="failure.id"
                class="workspace-failure-card"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="doc-title text-base font-semibold">
                            {{ failure.title }}
                        </p>
                        <p class="doc-subtle mt-1 text-sm">
                            {{ failure.matter_title ?? 'Unassigned matter' }}
                        </p>
                    </div>

                    <DocumentStatusBadge :status="failure.status" />
                </div>

                <div
                    class="mt-4 flex flex-wrap items-center justify-between gap-3"
                >
                    <span class="doc-subtle text-xs uppercase">
                        Updated {{ formatDateTime(failure.updated_at) }}
                    </span>

                    <Link
                        :href="show(failure.id).url"
                        class="doc-seal inline-flex items-center gap-1.5 text-xs font-semibold tracking-[0.12em] uppercase hover:underline"
                    >
                        Open document
                        <ArrowUpRight class="size-3.5" />
                    </Link>
                </div>
            </article>
        </div>
    </section>
</template>
