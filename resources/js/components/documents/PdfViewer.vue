<script setup lang="ts">
import { GlobalWorkerOptions, getDocument } from 'pdfjs-dist';
import pdfWorkerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import AnnotationLayer from '@/components/documents/AnnotationLayer.vue';
import DocumentExperienceSurface from '@/components/documents/DocumentExperienceSurface.vue';
import type {
    DocumentAnnotation,
    DocumentAnnotationCoordinates,
    DocumentAnnotationType,
    DocumentExperienceGuardrails,
} from '@/types';

GlobalWorkerOptions.workerSrc = pdfWorkerUrl;

type Props = {
    documentExperience: DocumentExperienceGuardrails;
    src: string;
    title: string;
    annotations: DocumentAnnotation[];
    canAnnotate?: boolean;
    canDeleteAnyAnnotation?: boolean;
    mutationPending?: boolean;
    delay?: 1 | 2 | null;
};

type AnnotationCreatePayload = {
    type: DocumentAnnotationType;
    page_number: number;
    coordinates: DocumentAnnotationCoordinates;
    content: string | null;
};

type PdfDocumentHandle = {
    numPages: number;
    getPage: (pageNumber: number) => Promise<{
        getViewport: (options: { scale: number }) => {
            width: number;
            height: number;
        };
        render: (options: {
            canvasContext: CanvasRenderingContext2D;
            transform: [number, number, number, number, number, number] | null;
            viewport: {
                width: number;
                height: number;
            };
        }) => { promise: Promise<void> };
    }>;
    destroy: () => Promise<void> | void;
};

type PdfLoadingTask = {
    promise: Promise<PdfDocumentHandle>;
    destroy?: () => void;
};

const props = withDefaults(defineProps<Props>(), {
    canAnnotate: false,
    canDeleteAnyAnnotation: false,
    mutationPending: false,
    delay: null,
});

const emit = defineEmits<{
    (event: 'create-annotation', payload: AnnotationCreatePayload): void;
    (event: 'delete-annotation', annotation: DocumentAnnotation): void;
}>();

const loading = ref(true);
const errorMessage = ref<string | null>(null);
const zoomMultiplier = ref(1);
const pageNumbers = ref<number[]>([]);
const isFullscreen = ref(false);
const activeAnnotationTool = ref<DocumentAnnotationType | null>(null);
const viewerRoot = ref<HTMLDivElement | null>(null);
const viewportElement = ref<HTMLDivElement | null>(null);
const fitScale = ref(1);

const canvasElements = new Map<number, HTMLCanvasElement>();
let documentHandle: PdfDocumentHandle | null = null;
let loadingTask: PdfLoadingTask | null = null;
let renderToken = 0;
let naturalPageWidth = 0;
let resizeObserver: ResizeObserver | null = null;

const renderScale = computed(() => fitScale.value * zoomMultiplier.value);
const zoomLabel = computed(() => `${Math.round(zoomMultiplier.value * 100)}%`);
const pageCountLabel = computed(() =>
    pageNumbers.value.length > 0
        ? `${pageNumbers.value.length} pages`
        : 'Loading',
);
const annotationTools: Array<{
    label: string;
    value: DocumentAnnotationType | null;
}> = [
    { label: 'Browse', value: null },
    { label: 'Highlight', value: 'highlight' },
    { label: 'Comment', value: 'comment' },
    { label: 'Note', value: 'note' },
];

function setCanvasRef(
    pageNumber: number,
    element: HTMLCanvasElement | null,
): void {
    if (element === null) {
        canvasElements.delete(pageNumber);

        return;
    }

    canvasElements.set(pageNumber, element);
}

function zoomOut(): void {
    zoomMultiplier.value = Math.max(
        0.8,
        Number((zoomMultiplier.value - 0.1).toFixed(1)),
    );
}

function zoomIn(): void {
    zoomMultiplier.value = Math.min(
        1.4,
        Number((zoomMultiplier.value + 0.1).toFixed(1)),
    );
}

function syncFitScale(): void {
    if (naturalPageWidth === 0 || viewportElement.value === null) {
        return;
    }

    const viewportWidth = viewportElement.value.clientWidth;

    if (viewportWidth === 0) {
        return;
    }

    const horizontalPadding = viewportWidth >= 768 ? 64 : 36;
    const availableWidth = Math.max(viewportWidth - horizontalPadding, 240);

    fitScale.value = Math.max(
        0.72,
        Math.min(1.05, availableWidth / naturalPageWidth),
    );
}

async function destroyDocumentHandle(): Promise<void> {
    renderToken += 1;
    pageNumbers.value = [];
    canvasElements.clear();
    naturalPageWidth = 0;

    if (loadingTask?.destroy) {
        loadingTask.destroy();
    }

    loadingTask = null;

    if (documentHandle !== null) {
        await documentHandle.destroy();
        documentHandle = null;
    }
}

async function renderPages(): Promise<void> {
    if (documentHandle === null) {
        return;
    }

    const token = ++renderToken;

    await nextTick();

    for (const pageNumber of pageNumbers.value) {
        if (token !== renderToken || documentHandle === null) {
            return;
        }

        const canvas = canvasElements.get(pageNumber);

        if (canvas === undefined) {
            continue;
        }

        const page = await documentHandle.getPage(pageNumber);

        if (token !== renderToken) {
            return;
        }

        const viewport = page.getViewport({ scale: renderScale.value });
        const context = canvas.getContext('2d');

        if (context === null) {
            continue;
        }

        const outputScale = window.devicePixelRatio || 1;

        canvas.width = Math.floor(viewport.width * outputScale);
        canvas.height = Math.floor(viewport.height * outputScale);
        canvas.style.width = `${viewport.width}px`;
        canvas.style.height = `${viewport.height}px`;

        await page.render({
            canvasContext: context,
            transform:
                outputScale === 1
                    ? null
                    : [outputScale, 0, 0, outputScale, 0, 0],
            viewport,
        }).promise;
    }
}

async function loadDocument(): Promise<void> {
    loading.value = true;
    errorMessage.value = null;

    await destroyDocumentHandle();

    try {
        loadingTask = getDocument(props.src) as PdfLoadingTask;
        documentHandle = await loadingTask.promise;
        const firstPage = await documentHandle.getPage(1);
        naturalPageWidth = firstPage.getViewport({ scale: 1 }).width;
        syncFitScale();
        pageNumbers.value = Array.from(
            { length: documentHandle.numPages },
            (_, index) => index + 1,
        );

        await renderPages();
    } catch (error) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'The PDF preview could not be loaded.';
    } finally {
        loading.value = false;
    }
}

async function syncViewerLayout(): Promise<void> {
    await nextTick();
    syncFitScale();

    if (documentHandle !== null) {
        await renderPages();
    }
}

async function syncFullscreenState(): Promise<void> {
    isFullscreen.value = document.fullscreenElement === viewerRoot.value;
    await syncViewerLayout();
}

async function toggleFullscreen(): Promise<void> {
    if (viewerRoot.value === null) {
        return;
    }

    if (document.fullscreenElement === viewerRoot.value) {
        await document.exitFullscreen();

        return;
    }

    await viewerRoot.value.requestFullscreen();
}

function handleFullscreenChange(): void {
    void syncFullscreenState();
}

function annotationsForPage(pageNumber: number): DocumentAnnotation[] {
    return props.annotations.filter(
        (annotation) => annotation.page_number === pageNumber,
    );
}

function selectAnnotationTool(tool: DocumentAnnotationType | null): void {
    activeAnnotationTool.value = tool;
}

function emitCreateAnnotation(payload: AnnotationCreatePayload): void {
    emit('create-annotation', payload);
}

function emitDeleteAnnotation(annotation: DocumentAnnotation): void {
    emit('delete-annotation', annotation);
}

onMounted(async () => {
    await loadDocument();
});

watch(
    () => props.src,
    async () => {
        await loadDocument();
    },
);

watch(renderScale, async () => {
    if (documentHandle === null) {
        return;
    }

    await renderPages();
});

onMounted(() => {
    resizeObserver = new ResizeObserver(() => {
        const previousFitScale = fitScale.value;

        syncFitScale();

        if (Math.abs(previousFitScale - fitScale.value) > 0.01) {
            void renderPages();
        }
    });

    if (viewportElement.value !== null) {
        resizeObserver.observe(viewportElement.value);
    }

    document.addEventListener('fullscreenchange', handleFullscreenChange);
});

onBeforeUnmount(async () => {
    resizeObserver?.disconnect();
    document.removeEventListener('fullscreenchange', handleFullscreenChange);

    await destroyDocumentHandle();
});
</script>

<template>
    <div
        ref="viewerRoot"
        :class="[
            isFullscreen
                ? 'h-full w-full bg-[var(--doc-paper)] p-4 sm:p-6'
                : '',
        ]"
    >
        <DocumentExperienceSurface
            :document-experience="documentExperience"
            :delay="delay"
            :class="[
                'overflow-hidden p-0',
                isFullscreen ? 'flex h-full flex-col' : '',
            ]"
        >
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--doc-border)]/70 px-5 py-4 sm:px-6"
            >
                <div class="min-w-0">
                    <p
                        class="doc-subtle text-xs font-semibold tracking-[0.14em] uppercase"
                    >
                        Inline preview
                    </p>
                    <h2 class="doc-title mt-1 truncate text-lg font-semibold">
                        {{ title }}
                    </h2>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-2">
                    <span class="doc-subtle hidden text-xs sm:inline">
                        {{ pageCountLabel }}
                    </span>

                    <div
                        v-if="canAnnotate"
                        class="flex flex-wrap items-center gap-1 rounded-full border border-[var(--doc-border)]/80 bg-[hsl(38_30%_97%/0.85)] px-1.5 py-1"
                    >
                        <span
                            class="doc-subtle px-2 text-[11px] font-semibold tracking-[0.12em] uppercase"
                        >
                            Annotate
                        </span>
                        <button
                            v-for="tool in annotationTools"
                            :key="tool.label"
                            type="button"
                            class="rounded-full px-3 py-1 text-xs font-semibold transition"
                            :class="
                                activeAnnotationTool === tool.value
                                    ? 'bg-[var(--doc-seal)] text-white shadow-sm'
                                    : 'text-slate-600 hover:bg-black/5'
                            "
                            :disabled="mutationPending"
                            @click="selectAnnotationTool(tool.value)"
                        >
                            {{ tool.label }}
                        </button>
                    </div>

                    <button
                        type="button"
                        class="rounded-full border border-[var(--doc-border)]/80 px-3 py-1 text-sm transition hover:border-[var(--doc-seal)]/40 hover:text-[var(--doc-seal)]"
                        @click="toggleFullscreen"
                    >
                        {{ isFullscreen ? 'Exit full screen' : 'Full screen' }}
                    </button>

                    <button
                        type="button"
                        class="rounded-full border border-[var(--doc-border)]/80 px-3 py-1 text-sm transition hover:border-[var(--doc-seal)]/40 hover:text-[var(--doc-seal)]"
                        @click="zoomOut"
                    >
                        -
                    </button>
                    <span class="min-w-14 text-center text-sm font-medium">
                        {{ zoomLabel }}
                    </span>
                    <button
                        type="button"
                        class="rounded-full border border-[var(--doc-border)]/80 px-3 py-1 text-sm transition hover:border-[var(--doc-seal)]/40 hover:text-[var(--doc-seal)]"
                        @click="zoomIn"
                    >
                        +
                    </button>
                </div>
            </div>

            <div
                ref="viewportElement"
                :class="[
                    'bg-[linear-gradient(180deg,hsl(34_40%_94%/0.84),hsl(33_32%_92%/0.92))]',
                    isFullscreen
                        ? 'min-h-0 flex-1 px-3 py-4 sm:px-6 sm:py-5'
                        : 'px-3 py-4 sm:px-5',
                ]"
            >
                <div
                    v-if="errorMessage"
                    class="flex min-h-[28rem] items-center justify-center rounded-[1.6rem] border border-dashed border-red-200 bg-red-50/70 px-6 text-center"
                >
                    <div class="space-y-3">
                        <p class="text-base font-semibold text-red-700">
                            Preview unavailable
                        </p>
                        <p class="text-sm text-red-600">
                            {{ errorMessage }}
                        </p>
                    </div>
                </div>

                <div v-else class="relative">
                    <div
                        :class="[
                            'space-y-5 overflow-auto rounded-[1.6rem] border border-[var(--doc-border)]/70 bg-[hsl(34_33%_98%/0.96)] p-3 shadow-[inset_0_1px_0_hsl(0_0%_100%/0.75)] sm:p-4',
                            isFullscreen
                                ? 'max-h-[calc(100vh-12rem)]'
                                : 'max-h-[74vh]',
                        ]"
                    >
                        <article
                            v-if="pageNumbers.length === 0"
                            aria-hidden="true"
                            class="rounded-[1.35rem] border border-[var(--doc-border)]/65 bg-[linear-gradient(180deg,hsl(38_34%_97%),hsl(36_28%_94%))] p-3 shadow-[0_18px_40px_hsl(22_18%_45%/0.09)]"
                        >
                            <div
                                class="mb-3 flex items-center justify-between gap-3"
                            >
                                <span
                                    class="doc-subtle text-[11px] font-semibold tracking-[0.14em] uppercase"
                                >
                                    Preparing page 1
                                </span>
                            </div>

                            <div
                                class="rounded-[1rem] bg-[hsl(36_22%_92%/0.52)] p-2 sm:p-3"
                            >
                                <div
                                    class="mx-auto aspect-[0.72] w-full max-w-[42rem] rounded-[0.95rem] bg-white shadow-[0_24px_55px_hsl(24_18%_26%/0.12)]"
                                />
                            </div>
                        </article>

                        <article
                            v-for="pageNumber in pageNumbers"
                            :key="pageNumber"
                            class="rounded-[1.35rem] border border-[var(--doc-border)]/65 bg-[linear-gradient(180deg,hsl(38_34%_97%),hsl(36_28%_94%))] p-3 shadow-[0_18px_40px_hsl(22_18%_45%/0.09)]"
                        >
                            <div
                                class="mb-3 flex items-center justify-between gap-3"
                            >
                                <span
                                    class="doc-subtle text-[11px] font-semibold tracking-[0.14em] uppercase"
                                >
                                    Page {{ pageNumber }}
                                </span>
                            </div>

                            <div
                                class="relative rounded-[1rem] bg-[hsl(36_22%_92%/0.52)] p-2 sm:p-3"
                            >
                                <canvas
                                    :ref="
                                        (element) =>
                                            setCanvasRef(
                                                pageNumber,
                                                element as HTMLCanvasElement | null,
                                            )
                                    "
                                    class="mx-auto block rounded-[0.95rem] bg-white shadow-[0_24px_55px_hsl(24_18%_26%/0.12)]"
                                />

                                <AnnotationLayer
                                    :annotations="
                                        annotationsForPage(pageNumber)
                                    "
                                    :page-number="pageNumber"
                                    :active-tool="activeAnnotationTool"
                                    :can-annotate="canAnnotate"
                                    :can-delete-any-annotation="
                                        canDeleteAnyAnnotation
                                    "
                                    :busy="mutationPending"
                                    @create="emitCreateAnnotation"
                                    @delete="emitDeleteAnnotation"
                                />
                            </div>
                        </article>
                    </div>

                    <div
                        v-if="loading"
                        class="pointer-events-none absolute inset-0 rounded-[1.6rem] bg-[hsl(34_35%_97%/0.68)] p-3 backdrop-blur-[1px] sm:p-4"
                    >
                        <article
                            class="rounded-[1.35rem] border border-[var(--doc-border)]/65 bg-[linear-gradient(180deg,hsl(38_34%_97%),hsl(36_28%_94%))] p-3 shadow-[0_18px_40px_hsl(22_18%_45%/0.09)]"
                        >
                            <div
                                class="mb-3 flex items-center justify-between gap-3"
                            >
                                <span
                                    class="doc-subtle text-[11px] font-semibold tracking-[0.14em] uppercase"
                                >
                                    Preparing page 1
                                </span>
                                <span class="doc-subtle text-xs">
                                    Rendering secure review copy
                                </span>
                            </div>

                            <div
                                class="rounded-[1rem] bg-[hsl(36_22%_92%/0.52)] p-2 sm:p-3"
                            >
                                <div
                                    class="mx-auto aspect-[0.72] w-full max-w-[42rem] rounded-[0.95rem] bg-white p-6 shadow-[0_24px_55px_hsl(24_18%_26%/0.12)] sm:p-10"
                                >
                                    <div class="space-y-6">
                                        <div class="flex justify-between gap-4">
                                            <div class="space-y-2">
                                                <div
                                                    class="h-3 w-28 rounded-full bg-[var(--doc-border)]/45"
                                                />
                                                <div
                                                    class="h-3 w-40 rounded-full bg-[var(--doc-border)]/32"
                                                />
                                            </div>
                                            <div
                                                class="h-4 w-24 rounded-full bg-[var(--doc-border)]/38"
                                            />
                                        </div>

                                        <div class="space-y-3 pt-4">
                                            <div
                                                class="mx-auto h-4 w-40 rounded-full bg-[var(--doc-border)]/42"
                                            />
                                            <div
                                                class="mx-auto h-3 w-56 rounded-full bg-[var(--doc-border)]/30"
                                            />
                                        </div>

                                        <div class="space-y-3 pt-4">
                                            <div
                                                class="h-3 w-full rounded-full bg-[var(--doc-border)]/22"
                                            />
                                            <div
                                                class="h-3 w-full rounded-full bg-[var(--doc-border)]/22"
                                            />
                                            <div
                                                class="h-3 w-[88%] rounded-full bg-[var(--doc-border)]/22"
                                            />
                                            <div
                                                class="h-3 w-[92%] rounded-full bg-[var(--doc-border)]/22"
                                            />
                                            <div
                                                class="h-3 w-[72%] rounded-full bg-[var(--doc-border)]/22"
                                            />
                                        </div>

                                        <div class="space-y-3 pt-6">
                                            <div
                                                class="h-3 w-[85%] rounded-full bg-[var(--doc-border)]/18"
                                            />
                                            <div
                                                class="h-3 w-[90%] rounded-full bg-[var(--doc-border)]/18"
                                            />
                                            <div
                                                class="h-3 w-[76%] rounded-full bg-[var(--doc-border)]/18"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
        </DocumentExperienceSurface>
    </div>
</template>
