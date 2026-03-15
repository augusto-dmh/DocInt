<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import QueueHealthController from '@/actions/App/Http/Controllers/Admin/QueueHealthController';
import AlertError from '@/components/AlertError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type {BreadcrumbItem, QueueHealthSnapshot} from '@/types';

const props = defineProps<{
    queueHealth: QueueHealthSnapshot;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Queue Health',
        href: QueueHealthController.url(),
    },
];

const summaryCards = [
    {
        title: 'Total messages',
        value: props.queueHealth.summary.total_messages,
    },
    {
        title: 'Ready',
        value: props.queueHealth.summary.total_ready,
    },
    {
        title: 'Unacked',
        value: props.queueHealth.summary.total_unacked,
    },
    {
        title: 'Consumers',
        value: props.queueHealth.summary.total_consumers,
    },
    {
        title: 'DLQ messages',
        value: props.queueHealth.summary.dead_letter_messages,
    },
];

function formatGeneratedAt(value: string): string {
    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}
</script>

<template>
    <Head title="Queue Health" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <section class="workspace-hero p-6 sm:p-8">
            <p
                class="doc-seal text-xs font-semibold tracking-[0.16em] uppercase"
            >
                Operations monitor
            </p>
            <h1 class="doc-title mt-2 text-3xl font-semibold sm:text-4xl">
                Queue health
            </h1>
            <p class="doc-subtle mt-3 max-w-3xl text-sm sm:text-base">
                Live RabbitMQ metrics for pipeline and dead-letter visibility.
            </p>
            <div
                class="doc-subtle mt-4 flex flex-wrap items-center gap-2 text-xs tracking-[0.08em] uppercase"
            >
                <span
                    >Last refresh:
                    {{ formatGeneratedAt(queueHealth.generated_at) }}</span
                >
                <Link
                    :href="QueueHealthController()"
                    class="doc-seal rounded-md border border-[var(--doc-seal)]/40 px-2.5 py-1 transition hover:bg-[var(--doc-seal)]/10"
                >
                    Refresh
                </Link>
            </div>
        </section>

        <AlertError
            v-if="!queueHealth.available && queueHealth.error"
            :errors="[queueHealth.error]"
            title="Queue health is temporarily unavailable."
            class="mt-6"
        />

        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <article
                v-for="card in summaryCards"
                :key="card.title"
                class="workspace-panel workspace-fade-up p-5"
            >
                <p class="doc-subtle text-xs tracking-[0.12em] uppercase">
                    {{ card.title }}
                </p>
                <p class="doc-title mt-3 text-3xl font-semibold">
                    {{ card.value }}
                </p>
            </article>
        </div>

        <section
            class="workspace-panel workspace-fade-up workspace-delay-1 mt-6 p-5"
        >
            <h2 class="doc-title text-xl font-semibold">Queues</h2>
            <p class="doc-subtle mt-2 text-sm">
                Includes all processing, notification, and dead-letter queues.
            </p>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="doc-grid-line border-b bg-muted/75">
                            <th
                                class="px-4 py-3 text-left text-xs font-semibold tracking-[0.12em] uppercase"
                            >
                                Queue
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-semibold tracking-[0.12em] uppercase"
                            >
                                Pipeline
                            </th>
                            <th
                                class="px-4 py-3 text-right text-xs font-semibold tracking-[0.12em] uppercase"
                            >
                                Messages
                            </th>
                            <th
                                class="px-4 py-3 text-right text-xs font-semibold tracking-[0.12em] uppercase"
                            >
                                Ready
                            </th>
                            <th
                                class="px-4 py-3 text-right text-xs font-semibold tracking-[0.12em] uppercase"
                            >
                                Unacked
                            </th>
                            <th
                                class="px-4 py-3 text-right text-xs font-semibold tracking-[0.12em] uppercase"
                            >
                                Consumers
                            </th>
                            <th
                                class="px-4 py-3 text-left text-xs font-semibold tracking-[0.12em] uppercase"
                            >
                                State
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="queue in queueHealth.queues"
                            :key="queue.name"
                            class="doc-grid-line border-b last:border-0"
                            :class="
                                queue.is_dead_letter
                                    ? 'bg-[var(--doc-seal)]/6'
                                    : ''
                            "
                        >
                            <td class="px-4 py-3">
                                <span
                                    class="font-medium"
                                    :class="
                                        queue.is_dead_letter
                                            ? 'doc-seal'
                                            : 'doc-title'
                                    "
                                >
                                    {{ queue.name }}
                                </span>
                            </td>
                            <td class="doc-subtle px-4 py-3">
                                {{ queue.pipeline }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                {{ queue.messages }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                {{ queue.messages_ready }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                {{ queue.messages_unacknowledged }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                {{ queue.consumers }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-md border px-2 py-0.5 text-xs font-medium uppercase"
                                    :class="
                                        queue.state === 'running'
                                            ? 'border-emerald-700/30 bg-emerald-700/10 text-emerald-700'
                                            : 'border-[var(--doc-border)] text-[var(--doc-muted)]'
                                    "
                                >
                                    {{ queue.state }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </AppLayout>
</template>
