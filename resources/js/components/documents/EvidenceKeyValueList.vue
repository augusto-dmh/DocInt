<script setup lang="ts">
defineProps<{
    entries: Array<[string, unknown]>;
}>();

function formatFieldLabel(key: string): string {
    return key
        .replaceAll('_', ' ')
        .replaceAll('-', ' ')
        .split(' ')
        .map((segment) =>
            segment === ''
                ? segment
                : segment.charAt(0).toUpperCase() + segment.slice(1),
        )
        .join(' ');
}

function isStructuredValue(value: unknown): boolean {
    return (
        Array.isArray(value) || (typeof value === 'object' && value !== null)
    );
}

function formatStructuredValue(value: unknown): string {
    return JSON.stringify(value, null, 2);
}
</script>

<template>
    <dl class="space-y-3">
        <div
            v-for="[key, value] in entries"
            :key="key"
            class="rounded-2xl border border-[color:var(--doc-grid-line)] bg-white/60 p-4"
        >
            <dt
                class="doc-subtle text-xs font-semibold tracking-[0.12em] uppercase"
            >
                {{ formatFieldLabel(key) }}
            </dt>
            <dd class="mt-2 text-sm">
                <pre
                    v-if="isStructuredValue(value)"
                    class="overflow-x-auto font-mono text-xs leading-5 whitespace-pre-wrap"
                    >{{ formatStructuredValue(value) }}</pre
                >
                <span v-else>{{ String(value) }}</span>
            </dd>
        </div>
    </dl>
</template>
