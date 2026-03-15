<script setup lang="ts">
import { computed } from 'vue';
import {
    documentStatusToneClass,
    documentSurfaceClass,
    documentTypographyClass,
} from '@/lib/document-experience';
import type { DocumentExperienceGuardrails } from '@/types';

type UploadStatus = 'uploading' | 'completed' | 'failed';

type UploadProgressItem = {
    name: string;
    size: number;
    progress: number;
    status: UploadStatus;
};

type Props = {
    documentExperience: DocumentExperienceGuardrails;
    items: UploadProgressItem[];
};

const props = defineProps<Props>();

const trackerClass = computed(() =>
    documentSurfaceClass(
        props.documentExperience,
        { reveal: false },
        'space-y-3 p-4',
    ),
);

const trackerTitleClass = computed(() =>
    documentTypographyClass(
        props.documentExperience,
        'title',
        'text-sm font-semibold',
    ),
);

function formatSize(size: number): string {
    const sizeInMb = size / (1024 * 1024);

    if (sizeInMb >= 1) {
        return `${sizeInMb.toFixed(2)} MB`;
    }

    return `${Math.max(1, Math.round(size / 1024))} KB`;
}

function statusLabel(status: UploadStatus): string {
    if (status === 'completed') {
        return 'Completed';
    }

    if (status === 'failed') {
        return 'Failed';
    }

    return 'Uploading';
}

function statusClass(status: UploadStatus): string {
    return documentStatusToneClass(status);
}
</script>

<template>
    <div v-if="items.length" :class="trackerClass">
        <h3 :class="trackerTitleClass">Transfer progress</h3>

        <ul class="space-y-3">
            <li
                v-for="item in items"
                :key="item.name"
                class="space-y-2 rounded-lg border border-[var(--doc-border)]/80 p-3"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <p class="doc-title text-sm font-semibold">
                            {{ item.name }}
                        </p>
                        <p class="doc-subtle text-xs">
                            {{ formatSize(item.size) }}
                        </p>
                    </div>

                    <span
                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                        :class="statusClass(item.status)"
                    >
                        {{ statusLabel(item.status) }}
                    </span>
                </div>

                <div
                    class="h-1.5 overflow-hidden rounded-full bg-[var(--doc-paper-strong)]"
                >
                    <div
                        class="h-full rounded-full bg-[var(--doc-seal)] transition-all duration-300"
                        :style="{ width: `${item.progress}%` }"
                    />
                </div>
            </li>
        </ul>
    </div>
</template>
