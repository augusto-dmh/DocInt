<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ShieldBan, ShieldCheck } from 'lucide-vue-next';
import { onUnmounted, ref } from 'vue';
import TwoFactorRecoveryCodes from '@/components/TwoFactorRecoveryCodes.vue';
import TwoFactorSetupModal from '@/components/TwoFactorSetupModal.vue';
import { Button } from '@/components/ui/button';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { disable, enable, show } from '@/routes/two-factor';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        requiresConfirmation?: boolean;
        twoFactorEnabled?: boolean;
    }>(),
    {
        requiresConfirmation: false,
        twoFactorEnabled: false,
    },
);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Two-Factor Authentication',
        href: show.url(),
    },
];

const { hasSetupData, clearTwoFactorAuthData } = useTwoFactorAuth();
const showSetupModal = ref<boolean>(false);

onUnmounted(() => {
    clearTwoFactorAuthData();
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Two-Factor Authentication" />

        <SettingsLayout>
            <div class="space-y-6">
                <header>
                    <h2 class="doc-title text-2xl font-semibold">
                        Two-factor authentication
                    </h2>
                    <p class="doc-subtle mt-2 text-sm">
                        Add a second verification step to reduce account
                        takeover risk.
                    </p>
                </header>

                <section
                    class="workspace-panel border-[var(--doc-border)]/75 p-5"
                >
                    <p
                        class="workspace-status-pill"
                        :class="
                            twoFactorEnabled
                                ? 'workspace-status-pill--success'
                                : 'workspace-status-pill--warning'
                        "
                    >
                        {{ twoFactorEnabled ? 'Enabled' : 'Disabled' }}
                    </p>

                    <p class="doc-subtle mt-3 text-sm">
                        {{
                            twoFactorEnabled
                                ? 'Your account currently requires a 6-digit verification code from an authenticator app.'
                                : 'Enable 2FA to require a rotating code from your authenticator app during login.'
                        }}
                    </p>

                    <div class="mt-4">
                        <div v-if="!twoFactorEnabled">
                            <Button
                                v-if="hasSetupData"
                                @click="showSetupModal = true"
                                class="workspace-primary-button"
                            >
                                <ShieldCheck />
                                Continue setup
                            </Button>
                            <Form
                                v-else
                                v-bind="enable.form()"
                                @success="showSetupModal = true"
                                #default="{ processing }"
                            >
                                <Button
                                    type="submit"
                                    :disabled="processing"
                                    class="workspace-primary-button"
                                >
                                    <ShieldCheck />
                                    Enable 2FA
                                </Button>
                            </Form>
                        </div>

                        <Form
                            v-else
                            v-bind="disable.form()"
                            #default="{ processing }"
                        >
                            <Button
                                variant="destructive"
                                type="submit"
                                :disabled="processing"
                            >
                                <ShieldBan />
                                Disable 2FA
                            </Button>
                        </Form>
                    </div>
                </section>

                <TwoFactorRecoveryCodes v-if="twoFactorEnabled" />

                <TwoFactorSetupModal
                    v-model:isOpen="showSetupModal"
                    :requiresConfirmation="requiresConfirmation"
                    :twoFactorEnabled="twoFactorEnabled"
                />
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
