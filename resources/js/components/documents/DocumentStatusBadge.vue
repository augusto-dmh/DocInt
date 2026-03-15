<script setup lang="ts">
import { computed } from 'vue';
import { documentStatusToneClass } from '@/lib/document-experience';
import type { DocumentStatus } from '@/types';

type Props = {
    status: DocumentStatus;
};

const props = defineProps<Props>();

const badgeClass = computed(() => documentStatusToneClass(props.status));
const label = computed(() => props.status.replaceAll('_', ' '));
</script>

<template>
    <span
        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
        :class="badgeClass"
    >
        {{ label }}
    </span>
</template>
