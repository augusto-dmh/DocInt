<script setup lang="ts">
import { computed } from 'vue';
import {
    documentSurfaceClass,
    documentTypographyClass,
} from '@/lib/document-experience';
import type { DocumentExperienceGuardrails } from '@/types';

type Props = {
    documentExperience: DocumentExperienceGuardrails;
    title: string;
    description: string;
};

const props = defineProps<Props>();

const rootClass = computed(() =>
    documentSurfaceClass(
        props.documentExperience,
        { reveal: false },
        'doc-empty-state px-6 py-10 text-center sm:px-8',
    ),
);

const titleClass = computed(() =>
    documentTypographyClass(
        props.documentExperience,
        'title',
        'text-lg font-semibold',
    ),
);

const descriptionClass = computed(() =>
    documentTypographyClass(
        props.documentExperience,
        'subtle',
        'mx-auto mt-3 max-w-xl text-sm leading-6',
    ),
);
</script>

<template>
    <div :class="rootClass">
        <h3 :class="titleClass">
            {{ title }}
        </h3>
        <p :class="descriptionClass">
            {{ description }}
        </p>

        <div v-if="$slots.actions" class="mt-6">
            <slot name="actions" />
        </div>
    </div>
</template>
