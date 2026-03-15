<script setup lang="ts">
import { computed } from 'vue';
import {
    documentHeroClass,
    documentRootClass,
    documentTypographyClass,
} from '@/lib/document-experience';
import type { DocumentExperienceGuardrails } from '@/types';

type Props = {
    documentExperience: DocumentExperienceGuardrails;
    eyebrow: string;
    title: string;
    description?: string;
};

const props = withDefaults(defineProps<Props>(), {
    description: '',
});

const rootClass = computed(() =>
    documentRootClass(
        props.documentExperience,
        'rounded-[1.75rem] p-4 sm:p-6 lg:p-8',
    ),
);

const heroClass = computed(() =>
    documentHeroClass(props.documentExperience, 'p-6 sm:p-8'),
);

const eyebrowClass = computed(() =>
    documentTypographyClass(
        props.documentExperience,
        'seal',
        'text-xs font-semibold tracking-[0.18em] uppercase',
    ),
);

const titleClass = computed(() =>
    documentTypographyClass(
        props.documentExperience,
        'title',
        'mt-3 text-3xl font-semibold sm:text-4xl',
    ),
);

const descriptionClass = computed(() =>
    documentTypographyClass(
        props.documentExperience,
        'subtle',
        'mt-3 max-w-3xl text-sm leading-6 sm:text-base',
    ),
);
</script>

<template>
    <div :class="rootClass">
        <section :class="heroClass">
            <p :class="eyebrowClass">
                {{ eyebrow }}
            </p>
            <h1 :class="titleClass">
                {{ title }}
            </h1>

            <p v-if="description" :class="descriptionClass">
                {{ description }}
            </p>
            <div v-else-if="$slots.description" :class="descriptionClass">
                <slot name="description" />
            </div>

            <div
                v-if="$slots.actions"
                class="mt-6 flex flex-wrap items-center gap-3"
            >
                <slot name="actions" />
            </div>
        </section>

        <slot />
    </div>
</template>
