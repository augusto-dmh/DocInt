<script setup lang="ts">
import { computed, ref } from 'vue';
import {
    documentSurfaceClass,
    documentTypographyClass,
} from '@/lib/document-experience';
import type { DocumentExperienceGuardrails } from '@/types';

type Props = {
    documentExperience: DocumentExperienceGuardrails;
    disabled?: boolean;
    serverError?: string;
};

const props = withDefaults(defineProps<Props>(), {
    disabled: false,
    serverError: '',
});

const emit = defineEmits<{
    'file-selected': [file: File];
    'file-cleared': [];
}>();

const fileInput = ref<HTMLInputElement | null>(null);
const selectedFile = ref<File | null>(null);
const localError = ref('');
const isDragActive = ref(false);

const selectedFileDetails = computed(() => {
    if (!selectedFile.value) {
        return '';
    }

    const sizeInMb = selectedFile.value.size / (1024 * 1024);

    if (sizeInMb >= 1) {
        return `${sizeInMb.toFixed(2)} MB`;
    }

    return `${Math.max(1, Math.round(selectedFile.value.size / 1024))} KB`;
});

const dropzoneClass = computed(() =>
    documentSurfaceClass(
        props.documentExperience,
        { reveal: false },
        'group relative cursor-pointer overflow-hidden p-6 transition',
    ),
);

const selectedFileClass = computed(() =>
    documentSurfaceClass(
        props.documentExperience,
        { reveal: false },
        'flex items-center justify-between gap-3 px-4 py-3',
    ),
);

const titleClass = computed(() =>
    documentTypographyClass(
        props.documentExperience,
        'title',
        'text-lg font-semibold',
    ),
);

const subtleClass = computed(() =>
    documentTypographyClass(props.documentExperience, 'subtle'),
);

const fileNameClass = computed(() =>
    documentTypographyClass(
        props.documentExperience,
        'title',
        'text-sm font-semibold',
    ),
);

const fileSizeClass = computed(() =>
    documentTypographyClass(props.documentExperience, 'subtle', 'text-xs'),
);

function openFilePicker(): void {
    if (props.disabled) {
        return;
    }

    fileInput.value?.click();
}

function onFileInputChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    processSelectedFile(file);
}

function onDrop(event: DragEvent): void {
    event.preventDefault();
    isDragActive.value = false;

    if (props.disabled) {
        return;
    }

    processSelectedFile(event.dataTransfer?.files?.[0] ?? null);
}

function onDragOver(event: DragEvent): void {
    event.preventDefault();

    if (!props.disabled) {
        isDragActive.value = true;
    }
}

function onDragLeave(): void {
    isDragActive.value = false;
}

function processSelectedFile(file: File | null): void {
    localError.value = '';

    if (!file) {
        selectedFile.value = null;
        emit('file-cleared');
        return;
    }

    selectedFile.value = file;
    emit('file-selected', file);
}

function clearSelection(): void {
    selectedFile.value = null;
    localError.value = '';

    if (fileInput.value) {
        fileInput.value.value = '';
    }

    emit('file-cleared');
}
</script>

<template>
    <div class="space-y-3">
        <input
            ref="fileInput"
            type="file"
            class="sr-only"
            :disabled="disabled"
            @change="onFileInputChange"
        />

        <div
            role="button"
            tabindex="0"
            :aria-disabled="disabled"
            :class="[
                dropzoneClass,
                {
                    'ring-2 ring-[var(--doc-seal)]/25': isDragActive,
                    'opacity-60': disabled,
                    'hover:-translate-y-0.5 hover:shadow-lg': !disabled,
                },
            ]"
            @click="openFilePicker"
            @keydown.enter.prevent="openFilePicker"
            @keydown.space.prevent="openFilePicker"
            @drop="onDrop"
            @dragover="onDragOver"
            @dragleave="onDragLeave"
        >
            <div
                class="absolute inset-y-0 left-0 w-1 bg-[var(--doc-seal)]/70"
            />

            <p :class="titleClass">Drop file to archive</p>
            <p :class="[subtleClass, 'mt-2 text-sm']">
                Choose a single file for secure tenant-scoped storage.
            </p>
            <p
                :class="[
                    subtleClass,
                    'mt-1 text-xs tracking-[0.14em] uppercase',
                ]"
            >
                or click to browse
            </p>
        </div>

        <div v-if="selectedFile" :class="selectedFileClass">
            <div>
                <p :class="fileNameClass">
                    {{ selectedFile.name }}
                </p>
                <p :class="fileSizeClass">{{ selectedFileDetails }}</p>
            </div>

            <button
                type="button"
                class="doc-seal text-sm font-medium hover:underline"
                @click="clearSelection"
            >
                Remove
            </button>
        </div>

        <p
            v-if="localError || serverError"
            class="text-sm font-medium text-destructive"
        >
            {{ localError || serverError }}
        </p>
    </div>
</template>
