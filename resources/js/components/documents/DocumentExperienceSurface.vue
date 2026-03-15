<script setup lang="ts">
import { computed, useAttrs } from 'vue';
import { documentSurfaceClass } from '@/lib/document-experience';
import type { DocumentExperienceGuardrails } from '@/types';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    documentExperience: DocumentExperienceGuardrails;
    reveal?: boolean;
    delay?: 1 | 2 | null;
};

const props = withDefaults(defineProps<Props>(), {
    reveal: true,
    delay: null,
});

const attrs = useAttrs();

const surfaceClass = computed(() =>
    documentSurfaceClass(props.documentExperience, {
        reveal: props.reveal,
        delay: props.delay,
    }),
);
</script>

<template>
    <section v-bind="attrs" :class="surfaceClass">
        <slot />
    </section>
</template>
