<script setup lang="ts">
import { Download, Expand, LoaderCircle, Minus, Plus } from 'lucide-vue-next';
import workerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';
import {
    computed,
    onBeforeUnmount,
    onMounted,
    ref,
    shallowRef,
    watch,
} from 'vue';
import { Button } from '@/components/ui/button';

type PdfViewport = {
    width: number;
    height: number;
};

type PdfRenderTask = {
    cancel: () => void;
    promise: Promise<unknown>;
};
type PdfPageProxy = {
    getViewport: (options: { scale: number }) => PdfViewport;
    render: (options: {
        canvasContext: CanvasRenderingContext2D;
        viewport: PdfViewport;
    }) => PdfRenderTask;
};
type PdfDocumentProxy = {
    numPages: number;
    getPage: (pageNumber: number) => Promise<PdfPageProxy>;
    destroy: () => Promise<void>;
};
type PdfLoadingTask = {
    promise: Promise<PdfDocumentProxy>;
    destroy: () => void;
};
type PdfJsModule = {
    getDocument: (src: string) => PdfLoadingTask;
    GlobalWorkerOptions: {
        workerSrc: string;
    };
};

const props = defineProps<{
    previewUrl: string;
    downloadUrl: string;
    fileName: string;
}>();

const containerElement = ref<HTMLElement | null>(null);
const canvasElement = ref<HTMLCanvasElement | null>(null);
const isMounted = ref(false);
const isLoading = ref(true);
const isRendering = ref(false);
const isFullscreen = ref(false);
const renderError = ref<string | null>(null);
const currentPage = ref(1);
const totalPages = ref(0);
const zoomLevel = ref(1);

const pdfjsModule = shallowRef<PdfJsModule | null>(null);
const pdfDocument = shallowRef<PdfDocumentProxy | null>(null);
const loadingTask = shallowRef<PdfLoadingTask | null>(null);
const renderTask = shallowRef<PdfRenderTask | null>(null);

let activeLoadToken = 0;
let activeRenderToken = 0;

const canGoBack = computed(() => currentPage.value > 1);
const canGoForward = computed(
    () => pdfDocument.value !== null && currentPage.value < totalPages.value,
);
const canZoomOut = computed(() => zoomLevel.value > 0.75);
const canZoomIn = computed(() => zoomLevel.value < 2.5);
const zoomLabel = computed(() => `${Math.round(zoomLevel.value * 100)}%`);

async function ensurePdfJs(): Promise<PdfJsModule> {
    if (pdfjsModule.value !== null) {
        return pdfjsModule.value;
    }

    const module = await import('pdfjs-dist');

    module.GlobalWorkerOptions.workerSrc = workerUrl;
    pdfjsModule.value = module;

    return module;
}

function cancelRenderTask(): void {
    renderTask.value?.cancel();
    renderTask.value = null;
}

function cleanupDocument(): void {
    cancelRenderTask();

    const currentDocument = pdfDocument.value;

    pdfDocument.value = null;
    totalPages.value = 0;

    if (currentDocument !== null) {
        void currentDocument.destroy();
    }
}

function isCancelled(error: unknown): boolean {
    return (
        error instanceof Error &&
        (error.name === 'RenderingCancelledException' ||
            error.name === 'AbortException')
    );
}

async function renderCurrentPage(): Promise<void> {
    if (!isMounted.value || pdfDocument.value === null) {
        return;
    }

    const canvas = canvasElement.value;

    if (canvas === null) {
        return;
    }

    cancelRenderTask();
    renderError.value = null;
    isRendering.value = true;

    const renderToken = ++activeRenderToken;

    try {
        const page = await pdfDocument.value.getPage(currentPage.value);
        const context = canvas.getContext('2d');

        if (context === null) {
            throw new Error('Canvas rendering context is unavailable.');
        }

        const outputScale =
            typeof window === 'undefined' ? 1 : window.devicePixelRatio || 1;
        const cssViewport = page.getViewport({ scale: zoomLevel.value });
        const renderViewport = page.getViewport({
            scale: zoomLevel.value * outputScale,
        });

        canvas.width = Math.floor(renderViewport.width);
        canvas.height = Math.floor(renderViewport.height);
        canvas.style.width = `${cssViewport.width}px`;
        canvas.style.height = `${cssViewport.height}px`;

        const task = page.render({
            canvasContext: context,
            viewport: renderViewport,
        });

        renderTask.value = task;
        await task.promise;

        if (renderToken !== activeRenderToken) {
            return;
        }
    } catch (error) {
        if (!isCancelled(error)) {
            renderError.value = 'Unable to render this PDF page.';
        }
    } finally {
        if (renderToken === activeRenderToken) {
            renderTask.value = null;
        }

        isRendering.value = false;
    }
}

async function loadPdf(): Promise<void> {
    if (!isMounted.value) {
        return;
    }

    const module = await ensurePdfJs();
    const loadToken = ++activeLoadToken;

    loadingTask.value?.destroy();
    loadingTask.value = null;
    cleanupDocument();

    isLoading.value = true;
    renderError.value = null;
    currentPage.value = 1;

    try {
        const task = module.getDocument(props.previewUrl);

        loadingTask.value = task;

        const loadedDocument = await task.promise;

        if (loadToken !== activeLoadToken) {
            void loadedDocument.destroy();

            return;
        }

        pdfDocument.value = loadedDocument;
        totalPages.value = loadedDocument.numPages;

        await renderCurrentPage();
    } catch (error) {
        if (!isCancelled(error)) {
            renderError.value = 'Unable to load the inline preview.';
        }
    } finally {
        if (loadToken === activeLoadToken) {
            loadingTask.value = null;
            isLoading.value = false;
        }
    }
}

async function toggleFullscreen(): Promise<void> {
    const container = containerElement.value;

    if (container === null || typeof document === 'undefined') {
        return;
    }

    if (document.fullscreenElement === container) {
        await document.exitFullscreen();

        return;
    }

    await container.requestFullscreen();
}

function syncFullscreenState(): void {
    if (typeof document === 'undefined') {
        return;
    }

    isFullscreen.value = document.fullscreenElement === containerElement.value;
}

function zoomIn(): void {
    if (!canZoomIn.value) {
        return;
    }

    zoomLevel.value = Math.min(2.5, zoomLevel.value + 0.25);
}

function zoomOut(): void {
    if (!canZoomOut.value) {
        return;
    }

    zoomLevel.value = Math.max(0.75, zoomLevel.value - 0.25);
}

function goToPreviousPage(): void {
    if (!canGoBack.value) {
        return;
    }

    currentPage.value -= 1;
}

function goToNextPage(): void {
    if (!canGoForward.value) {
        return;
    }

    currentPage.value += 1;
}

watch(
    () => props.previewUrl,
    () => {
        if (isMounted.value) {
            void loadPdf();
        }
    },
);

watch([currentPage, zoomLevel], () => {
    if (isMounted.value && pdfDocument.value !== null) {
        void renderCurrentPage();
    }
});

onMounted(() => {
    isMounted.value = true;

    document.addEventListener('fullscreenchange', syncFullscreenState);

    void loadPdf();
});

onBeforeUnmount(() => {
    document.removeEventListener('fullscreenchange', syncFullscreenState);

    loadingTask.value?.destroy();
    cleanupDocument();
});
</script>

<template>
    <section
        ref="containerElement"
        class="rounded-[1.5rem] border border-[color:var(--doc-grid-line)] bg-[rgba(246,241,232,0.88)] shadow-[0_30px_80px_rgba(57,43,30,0.08)]"
    >
        <div
            class="flex flex-wrap items-center justify-between gap-3 border-b border-[color:var(--doc-grid-line)] px-4 py-4 sm:px-5"
        >
            <div>
                <p class="doc-title text-sm font-semibold">
                    Inline PDF preview
                </p>
                <p class="doc-subtle mt-1 text-xs leading-5">
                    Page {{ totalPages === 0 ? 0 : currentPage }} of
                    {{ totalPages }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!canGoBack || isLoading"
                    @click="goToPreviousPage"
                >
                    Previous
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!canGoForward || isLoading"
                    @click="goToNextPage"
                >
                    Next
                </Button>
                <Button
                    variant="outline"
                    size="icon"
                    :disabled="!canZoomOut || isLoading"
                    @click="zoomOut"
                >
                    <Minus class="size-4" />
                    <span class="sr-only">Zoom out</span>
                </Button>
                <span
                    class="doc-subtle min-w-14 text-center text-xs font-semibold"
                >
                    {{ zoomLabel }}
                </span>
                <Button
                    variant="outline"
                    size="icon"
                    :disabled="!canZoomIn || isLoading"
                    @click="zoomIn"
                >
                    <Plus class="size-4" />
                    <span class="sr-only">Zoom in</span>
                </Button>
                <Button variant="outline" size="icon" @click="toggleFullscreen">
                    <Expand class="size-4" />
                    <span class="sr-only">
                        {{
                            isFullscreen
                                ? 'Exit fullscreen preview'
                                : 'Open fullscreen preview'
                        }}
                    </span>
                </Button>
                <Button
                    as-child
                    size="sm"
                    class="bg-[var(--doc-seal)] text-white hover:bg-primary/90"
                >
                    <a :href="downloadUrl">
                        <Download class="mr-2 size-4" />
                        Download
                    </a>
                </Button>
            </div>
        </div>

        <div class="p-4 sm:p-5">
            <div
                class="relative overflow-hidden rounded-[1.25rem] border border-[color:var(--doc-grid-line)] bg-[linear-gradient(180deg,rgba(255,255,255,0.78),rgba(241,234,223,0.92))] p-4 sm:p-6"
            >
                <div
                    v-if="isLoading"
                    class="flex min-h-[28rem] flex-col items-center justify-center gap-3"
                >
                    <LoaderCircle
                        class="size-8 animate-spin text-[var(--doc-seal)]"
                    />
                    <div class="text-center">
                        <p class="doc-title text-sm font-semibold">
                            Loading preview
                        </p>
                        <p class="doc-subtle mt-1 text-xs">
                            Rendering {{ fileName }} inside the review
                            workspace.
                        </p>
                    </div>
                </div>

                <div
                    v-else-if="renderError"
                    class="flex min-h-[28rem] flex-col items-center justify-center gap-4 text-center"
                >
                    <div>
                        <p class="doc-title text-base font-semibold">
                            Preview unavailable
                        </p>
                        <p class="doc-subtle mt-2 max-w-md text-sm leading-6">
                            {{ renderError }}
                        </p>
                    </div>
                    <Button
                        as-child
                        class="bg-[var(--doc-seal)] text-white hover:bg-primary/90"
                    >
                        <a :href="downloadUrl">
                            <Download class="mr-2 size-4" />
                            Download original file
                        </a>
                    </Button>
                </div>

                <div v-else class="flex min-h-[28rem] justify-center">
                    <div class="relative">
                        <canvas
                            ref="canvasElement"
                            class="block rounded-[1rem] bg-white shadow-[0_25px_70px_rgba(21,14,9,0.16)]"
                        />

                        <div
                            v-if="isRendering"
                            class="absolute inset-0 flex items-start justify-end p-3"
                        >
                            <span
                                class="inline-flex items-center gap-2 rounded-full bg-[rgba(37,29,20,0.8)] px-3 py-1 text-xs font-medium text-white"
                            >
                                <LoaderCircle class="size-3.5 animate-spin" />
                                Rendering page
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
