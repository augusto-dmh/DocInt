<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, ShieldCheck } from 'lucide-vue-next';
import { dashboard, login, register } from '@/routes';

const appName = usePage().props.name ?? 'Docintern';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);
</script>

<template>
    <Head title="Welcome" />

    <div
        class="min-h-screen bg-background p-6 text-foreground lg:p-10"
    >
        <div class="mx-auto flex max-w-6xl flex-col gap-8">
            <header class="flex items-center justify-between">
                <p class="doc-title text-2xl font-semibold">{{ appName }}</p>
                <nav class="flex items-center gap-3">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="rounded-full border border-[var(--doc-border)] px-4 py-2 text-sm font-medium hover:bg-accent"
                    >
                        Dashboard
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="rounded-full border border-[var(--doc-border)] px-4 py-2 text-sm font-medium hover:bg-accent"
                        >
                            Log in
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="register()"
                            class="rounded-full bg-[var(--doc-seal)] px-4 py-2 text-sm font-medium text-white hover:bg-primary/90"
                        >
                            Register
                        </Link>
                    </template>
                </nav>
            </header>

            <main class="grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
                <section class="workspace-hero p-8">
                    <p
                        class="doc-seal text-xs font-semibold tracking-[0.16em] uppercase"
                    >
                        Legal workflow platform
                    </p>
                    <h1
                        class="doc-title mt-3 text-4xl font-semibold sm:text-5xl"
                    >
                        Tenant-safe matter and document operations.
                    </h1>
                    <p class="doc-subtle mt-4 max-w-xl text-base">
                        Coordinate clients, matters, and audited document
                        lifecycles in a single secure workspace built for legal
                        teams.
                    </p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <Link
                            :href="
                                $page.props.auth.user ? dashboard() : login()
                            "
                            class="inline-flex items-center gap-2 rounded-full bg-[var(--doc-seal)] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-primary/90"
                        >
                            {{
                                $page.props.auth.user
                                    ? 'Open dashboard'
                                    : 'Access workspace'
                            }}
                            <ArrowRight class="size-4" />
                        </Link>
                    </div>
                </section>

                <section class="workspace-panel p-6 sm:p-8">
                    <h2 class="doc-title text-2xl font-semibold">
                        Core capabilities
                    </h2>
                    <ul class="mt-5 space-y-3">
                        <li class="flex items-start gap-3">
                            <ShieldCheck
                                class="mt-0.5 size-4 text-[var(--doc-seal)]"
                            />
                            <p class="doc-subtle text-sm">
                                Tenant-aware authorization across clients,
                                matters, and documents.
                            </p>
                        </li>
                        <li class="flex items-start gap-3">
                            <ShieldCheck
                                class="mt-0.5 size-4 text-[var(--doc-seal)]"
                            />
                            <p class="doc-subtle text-sm">
                                Fortify-backed authentication with optional
                                two-factor security.
                            </p>
                        </li>
                        <li class="flex items-start gap-3">
                            <ShieldCheck
                                class="mt-0.5 size-4 text-[var(--doc-seal)]"
                            />
                            <p class="doc-subtle text-sm">
                                Traceable document activity timeline for
                                compliance workflows.
                            </p>
                        </li>
                    </ul>
                </section>
            </main>
        </div>
    </div>
</template>
