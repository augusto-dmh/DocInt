<script setup lang="ts">
import { computed } from 'vue';
import DocumentStatusBadge from '@/components/documents/DocumentStatusBadge.vue';
import {
    isActivePipelineDocument,
    isFailureDocumentStatus,
    pipelineStageForStatus,
    pipelineStages,
} from '@/lib/document-pipeline';
import type {
    PipelineStageId,
    RealtimeConnectionStatus,
} from '@/lib/document-pipeline';
import type { DashboardPipelineDocument } from '@/types';

type Props = {
    documents: DashboardPipelineDocument[];
    connectionStatus: RealtimeConnectionStatus;
    realtimeEnabled: boolean;
};

type StageGroup = {
    id: PipelineStageId;
    label: string;
    description: string;
    documents: DashboardPipelineDocument[];
};

const props = defineProps<Props>();

const stageGroups = computed<StageGroup[]>(() =>
    pipelineStages.map((stage) => ({
        ...stage,
        documents: props.documents.filter(
            (document) => pipelineStageForStatus(document.status) === stage.id,
        ),
    })),
);

const connectionCopy = computed(() => {
    if (!props.realtimeEnabled) {
        return {
            label: 'No tenant context',
            description:
                'Select or resolve a tenant context before subscribing to live document lanes.',
            tone: 'workspace-status-pill--warning',
        };
    }

    if (props.connectionStatus === 'connecting') {
        return {
            label: 'Connecting',
            description:
                'Opening the tenant broadcast stream and waiting for the first lane updates.',
            tone: 'workspace-status-pill--success',
        };
    }

    if (props.connectionStatus === 'disabled') {
        return {
            label: 'Realtime disabled',
            description:
                'Tenant context is active, but the broadcast client is not configured for this workspace.',
            tone: 'workspace-status-pill--warning',
        };
    }

    if (props.connectionStatus === 'reconnecting') {
        return {
            label: 'Reconnecting',
            description:
                'Connection dipped briefly. Showing the last known pipeline state while the stream resumes.',
            tone: 'workspace-status-pill--warning',
        };
    }

    if (
        props.connectionStatus === 'disconnected' ||
        props.connectionStatus === 'failed'
    ) {
        return {
            label:
                props.connectionStatus === 'failed'
                    ? 'Connection failed'
                    : 'Offline',
            description:
                'Realtime delivery is unavailable. The pipeline view remains visible with the latest received state.',
            tone: 'workspace-status-pill--warning',
        };
    }

    return {
        label: 'Realtime connected',
        description:
            'Tenant-scoped status transitions stream into each stage as processing advances.',
        tone: 'workspace-status-pill--success',
    };
});

function formatDateTime(value: string): string {
    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));
}

function stageCardClass(stageId: PipelineStageId): string {
    const documents =
        stageGroups.value.find((stage) => stage.id === stageId)?.documents ??
        [];
    const hasFailure = documents.some((document) =>
        isFailureDocumentStatus(document.status),
    );
    const hasActive = documents.some((document) =>
        isActivePipelineDocument(document.status),
    );

    if (hasFailure) {
        return 'border-[hsl(3_52%_74%)] bg-[hsl(4_74%_95%/0.7)]';
    }

    if (hasActive) {
        return 'border-[var(--doc-seal)]/35 bg-[hsl(39_58%_98%/0.96)] shadow-[0_18px_35px_-30px_hsl(9_72%_34%/0.35)]';
    }

    return 'border-[var(--doc-border)] bg-[hsl(39_52%_97%/0.9)]';
}
</script>

<template>
    <div class="space-y-5">
        <div
            class="flex flex-col gap-3 rounded-2xl border border-[var(--doc-border)]/70 bg-[hsl(39_58%_98%/0.92)] p-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div class="space-y-1.5">
                <p class="doc-title text-lg font-semibold">Processing lanes</p>
                <p class="doc-subtle max-w-3xl text-sm">
                    {{ connectionCopy.description }}
                </p>
            </div>

            <span class="workspace-status-pill" :class="connectionCopy.tone">
                {{ connectionCopy.label }}
            </span>
        </div>

        <div
            v-if="!realtimeEnabled"
            class="rounded-2xl border border-dashed border-[var(--doc-border)] bg-[hsl(39_40%_97%/0.8)] p-5"
        >
            <p class="doc-title text-base font-semibold">
                Realtime lanes are paused
            </p>
            <p class="doc-subtle mt-2 text-sm">
                This workspace can still render dashboard data, but live
                document grouping stays disabled until a tenant context is
                active.
            </p>
        </div>

        <div
            v-else-if="documents.length === 0"
            class="rounded-2xl border border-dashed border-[var(--doc-border)] bg-[hsl(39_40%_97%/0.8)] p-5"
        >
            <p class="doc-title text-base font-semibold">
                No document traffic in the current snapshot
            </p>
            <p class="doc-subtle mt-2 text-sm">
                New uploads and status transitions will settle into these lanes
                as soon as processing starts.
            </p>
        </div>

        <div v-else class="grid gap-4 xl:grid-cols-5">
            <section
                v-for="stage in stageGroups"
                :key="stage.id"
                class="workspace-fade-up rounded-[1.1rem] border p-4"
                :class="stageCardClass(stage.id)"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p
                            class="doc-seal text-[0.68rem] font-semibold tracking-[0.14em] uppercase"
                        >
                            {{ stage.label }}
                        </p>
                        <h3 class="doc-title mt-2 text-lg font-semibold">
                            {{ stage.documents.length }} document{{
                                stage.documents.length === 1 ? '' : 's'
                            }}
                        </h3>
                    </div>

                    <span
                        class="inline-flex min-w-10 justify-center rounded-full border border-[var(--doc-border)]/80 bg-[hsl(39_52%_97%/0.92)] px-2.5 py-1 text-xs font-semibold text-[var(--doc-seal)]"
                    >
                        {{ stage.documents.length }}
                    </span>
                </div>

                <p class="doc-subtle mt-2 text-sm">
                    {{ stage.description }}
                </p>

                <div v-if="stage.documents.length > 0" class="mt-4 space-y-3">
                    <article
                        v-for="document in stage.documents"
                        :key="document.id"
                        class="rounded-xl border border-[var(--doc-border)]/75 bg-[hsl(39_58%_98%/0.95)] p-3.5 shadow-[0_12px_24px_-26px_hsl(24_28%_18%/0.42)]"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <p class="doc-title text-sm font-semibold">
                                {{ document.title }}
                            </p>
                            <span class="doc-subtle text-[0.68rem] uppercase">
                                #{{ document.id }}
                            </span>
                        </div>

                        <p class="doc-subtle mt-2 text-xs">
                            {{ document.matter_title ?? 'Unassigned matter' }}
                        </p>

                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <DocumentStatusBadge :status="document.status" />
                            <span class="doc-subtle text-xs">
                                Updated
                                {{ formatDateTime(document.updated_at) }}
                            </span>
                        </div>
                    </article>
                </div>

                <p
                    v-else
                    class="doc-subtle mt-4 rounded-xl border border-dashed border-[var(--doc-border)]/75 bg-[hsl(39_52%_98%/0.55)] px-3 py-4 text-sm"
                >
                    No documents are parked in this stage right now.
                </p>
            </section>
        </div>
    </div>
</template>
