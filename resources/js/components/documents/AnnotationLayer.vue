<script setup lang="ts">
import { computed, ref } from 'vue';
import type {
    DocumentAnnotation,
    DocumentAnnotationCoordinates,
    DocumentAnnotationType,
} from '@/types';

type AnnotationDraft = {
    type: DocumentAnnotationType;
    page_number: number;
    coordinates: DocumentAnnotationCoordinates;
    content: string | null;
};

const props = withDefaults(
    defineProps<{
        annotations: DocumentAnnotation[];
        pageNumber: number;
        activeTool: DocumentAnnotationType | null;
        canAnnotate: boolean;
        canDeleteAnyAnnotation?: boolean;
        busy?: boolean;
    }>(),
    {
        canDeleteAnyAnnotation: false,
        busy: false,
    },
);

const emit = defineEmits<{
    create: [payload: AnnotationDraft];
    delete: [annotation: DocumentAnnotation];
}>();

const layerElement = ref<HTMLDivElement | null>(null);
const pointerStart = ref<{ x: number; y: number } | null>(null);
const draftCoordinates = ref<DocumentAnnotationCoordinates | null>(null);
const draftType = ref<DocumentAnnotationType | null>(null);
const draftContent = ref('');
const composerAnchor = ref<{ x: number; y: number } | null>(null);
const selectedAnnotationId = ref<number | null>(null);

const selectedAnnotation = computed(
    () =>
        props.annotations.find(
            (annotation) => annotation.id === selectedAnnotationId.value,
        ) ?? null,
);

function clamp(value: number, min: number, max: number): number {
    return Math.min(max, Math.max(min, value));
}

function formatLabel(value: string): string {
    return value.charAt(0).toUpperCase() + value.slice(1);
}

function toneClass(type: DocumentAnnotationType): string {
    if (type === 'highlight') {
        return 'border-amber-500/80 bg-amber-300/30 text-amber-900';
    }

    if (type === 'comment') {
        return 'border-[var(--doc-seal)]/80 bg-[var(--doc-seal)]/12 text-[var(--doc-seal)]';
    }

    return 'border-emerald-700/70 bg-emerald-100/55 text-emerald-900';
}

function annotationCanBeDeleted(annotation: DocumentAnnotation): boolean {
    return annotation.is_owner || props.canDeleteAnyAnnotation;
}

function coordinatesStyle(coordinates: DocumentAnnotationCoordinates): string {
    return [
        `left:${coordinates.x * 100}%`,
        `top:${coordinates.y * 100}%`,
        `width:${coordinates.width * 100}%`,
        `height:${coordinates.height * 100}%`,
    ].join(';');
}

function overlayPoint(event: PointerEvent): { x: number; y: number } | null {
    if (layerElement.value === null) {
        return null;
    }

    const bounds = layerElement.value.getBoundingClientRect();

    if (bounds.width === 0 || bounds.height === 0) {
        return null;
    }

    return {
        x: clamp((event.clientX - bounds.left) / bounds.width, 0, 1),
        y: clamp((event.clientY - bounds.top) / bounds.height, 0, 1),
    };
}

function resetDraft(): void {
    pointerStart.value = null;
    draftCoordinates.value = null;
    draftType.value = null;
    draftContent.value = '';
    composerAnchor.value = null;
}

function cancelComposer(): void {
    resetDraft();
}

function handlePointerDown(event: PointerEvent): void {
    if (
        !props.canAnnotate ||
        props.activeTool === null ||
        props.busy ||
        event.button !== 0
    ) {
        return;
    }

    selectedAnnotationId.value = null;

    const point = overlayPoint(event);

    if (point === null) {
        return;
    }

    if (event.currentTarget instanceof HTMLElement) {
        event.currentTarget.setPointerCapture(event.pointerId);
    }

    pointerStart.value = point;
    draftCoordinates.value = {
        x: point.x,
        y: point.y,
        width: 0,
        height: 0,
    };
}

function handlePointerMove(event: PointerEvent): void {
    if (pointerStart.value === null || props.busy) {
        return;
    }

    const point = overlayPoint(event);

    if (point === null) {
        return;
    }

    const left = Math.min(pointerStart.value.x, point.x);
    const top = Math.min(pointerStart.value.y, point.y);
    const width = Math.abs(pointerStart.value.x - point.x);
    const height = Math.abs(pointerStart.value.y - point.y);

    draftCoordinates.value = {
        x: left,
        y: top,
        width,
        height,
    };
}

function handlePointerUp(event: PointerEvent): void {
    if (pointerStart.value === null || props.activeTool === null) {
        resetDraft();

        return;
    }

    const point = overlayPoint(event) ?? pointerStart.value;
    const left = Math.min(pointerStart.value.x, point.x);
    const top = Math.min(pointerStart.value.y, point.y);
    const width = Math.abs(pointerStart.value.x - point.x);
    const height = Math.abs(pointerStart.value.y - point.y);
    const draft = {
        x: left,
        y: top,
        width,
        height,
    };

    if (event.currentTarget instanceof HTMLElement) {
        event.currentTarget.releasePointerCapture(event.pointerId);
    }

    draftCoordinates.value = draft;
    draftType.value = props.activeTool;
    pointerStart.value = null;

    if (props.activeTool === 'highlight') {
        emit('create', {
            type: props.activeTool,
            page_number: props.pageNumber,
            coordinates: draft,
            content: null,
        });
        resetDraft();

        return;
    }

    composerAnchor.value = {
        x: draft.x + draft.width,
        y: draft.y + draft.height,
    };
}

function saveDraft(): void {
    if (draftCoordinates.value === null || draftType.value === null) {
        return;
    }

    const trimmedContent = draftContent.value.trim();

    if (trimmedContent.length === 0) {
        return;
    }

    emit('create', {
        type: draftType.value,
        page_number: props.pageNumber,
        coordinates: draftCoordinates.value,
        content: trimmedContent,
    });

    resetDraft();
}

function selectAnnotation(annotation: DocumentAnnotation): void {
    selectedAnnotationId.value =
        selectedAnnotationId.value === annotation.id ? null : annotation.id;
}

function removeSelectedAnnotation(): void {
    if (selectedAnnotation.value === null || props.busy) {
        return;
    }

    emit('delete', selectedAnnotation.value);
    selectedAnnotationId.value = null;
}

const composerStyle = computed(() => {
    if (composerAnchor.value === null) {
        return '';
    }

    return [
        `left:${clamp(composerAnchor.value.x, 0.1, 0.72) * 100}%`,
        `top:${clamp(composerAnchor.value.y, 0.08, 0.78) * 100}%`,
    ].join(';');
});

const selectedAnnotationStyle = computed(() => {
    if (selectedAnnotation.value === null) {
        return '';
    }

    return [
        `left:${clamp(selectedAnnotation.value.coordinates.x + selectedAnnotation.value.coordinates.width, 0.1, 0.72) * 100}%`,
        `top:${clamp(selectedAnnotation.value.coordinates.y, 0.06, 0.74) * 100}%`,
    ].join(';');
});
</script>

<template>
    <div ref="layerElement" class="absolute inset-0 z-20 select-none">
        <div
            v-if="canAnnotate && activeTool"
            class="pointer-events-auto absolute inset-0"
            :class="busy ? 'cursor-wait' : 'cursor-crosshair'"
            @pointerdown.prevent="handlePointerDown"
            @pointermove="handlePointerMove"
            @pointerup="handlePointerUp"
            @pointercancel="cancelComposer"
        />

        <button
            v-for="annotation in annotations"
            :key="annotation.id"
            type="button"
            class="pointer-events-auto absolute rounded-[0.6rem] border-2 shadow-[0_0_0_1px_hsl(38_20%_96%/0.7)] transition hover:shadow-[0_0_0_1px_hsl(38_20%_96%/0.92),0_10px_25px_hsl(28_18%_30%/0.16)]"
            :class="[
                toneClass(annotation.type),
                selectedAnnotationId === annotation.id
                    ? 'ring-2 ring-[var(--doc-seal)]/35'
                    : '',
            ]"
            :style="coordinatesStyle(annotation.coordinates)"
            :data-annotation-box="annotation.id"
            @click.stop="selectAnnotation(annotation)"
        >
            <span class="sr-only">
                {{ formatLabel(annotation.type) }} annotation on page
                {{ pageNumber }}
            </span>
        </button>

        <div
            v-if="draftCoordinates"
            class="absolute rounded-[0.75rem] border-2 border-dashed border-[var(--doc-seal)]/70 bg-[var(--doc-seal)]/10"
            :style="coordinatesStyle(draftCoordinates)"
        />

        <form
            v-if="composerAnchor && draftType && draftCoordinates"
            class="pointer-events-auto absolute z-30 w-[min(18rem,calc(100%-1.25rem))] rounded-[1.2rem] border border-[var(--doc-border)]/70 bg-[hsl(37_35%_97%/0.98)] p-4 text-left shadow-[0_18px_45px_hsl(24_18%_26%/0.18)] backdrop-blur"
            :style="composerStyle"
            @submit.prevent="saveDraft"
        >
            <p
                class="doc-subtle text-[11px] font-semibold tracking-[0.14em] uppercase"
            >
                {{ formatLabel(draftType) }} on page {{ pageNumber }}
            </p>
            <label class="mt-3 block">
                <span class="sr-only">Annotation text</span>
                <textarea
                    v-model="draftContent"
                    rows="4"
                    maxlength="2000"
                    class="min-h-24 w-full rounded-[0.95rem] border border-[var(--doc-border)]/75 bg-white/90 px-3 py-2 text-sm text-slate-800 shadow-[inset_0_1px_0_hsl(0_0%_100%/0.7)] transition outline-none focus:border-[var(--doc-seal)]/45 focus:ring-2 focus:ring-[var(--doc-seal)]/15"
                    :placeholder="`Add a ${draftType} for this region`"
                />
            </label>
            <div class="mt-3 flex items-center justify-between gap-3">
                <button
                    type="button"
                    class="rounded-full px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-black/5"
                    @click="cancelComposer"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    class="rounded-full bg-[var(--doc-seal)] px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-[color-mix(in_oklab,var(--doc-seal)_90%,black)] disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="busy || draftContent.trim().length === 0"
                >
                    Save annotation
                </button>
            </div>
        </form>

        <div
            v-if="selectedAnnotation"
            class="pointer-events-auto absolute z-30 w-[min(17rem,calc(100%-1rem))] rounded-[1.2rem] border border-[var(--doc-border)]/70 bg-[hsl(37_35%_97%/0.98)] p-4 text-left shadow-[0_18px_45px_hsl(24_18%_26%/0.18)] backdrop-blur"
            :style="selectedAnnotationStyle"
        >
            <p
                class="doc-subtle text-[11px] font-semibold tracking-[0.14em] uppercase"
            >
                {{ formatLabel(selectedAnnotation.type) }} on page
                {{ pageNumber }}
            </p>
            <p class="doc-title mt-2 text-sm font-semibold">
                {{ selectedAnnotation.user?.name ?? 'System' }}
            </p>
            <p
                v-if="selectedAnnotation.content"
                class="mt-2 text-sm leading-6 text-slate-700"
            >
                {{ selectedAnnotation.content }}
            </p>
            <p v-else class="mt-2 text-sm leading-6 text-slate-600">
                Highlighted region
            </p>
            <div class="mt-3 flex items-center justify-between gap-3">
                <span class="doc-subtle text-xs">
                    {{ formatLabel(selectedAnnotation.type) }}
                </span>
                <button
                    v-if="annotationCanBeDeleted(selectedAnnotation)"
                    type="button"
                    class="rounded-full border border-red-300/70 px-3 py-1.5 text-xs font-semibold text-red-700 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="busy"
                    @click="removeSelectedAnnotation"
                >
                    Delete
                </button>
            </div>
        </div>
    </div>
</template>
