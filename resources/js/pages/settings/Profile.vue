<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import { type BreadcrumbItem } from '@/types';

defineProps<{
    mustVerifyEmail: boolean;
    status?: string;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: edit().url,
    },
];

const page = usePage();
const user = page.props.auth.user;
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Profile settings" />

        <SettingsLayout>
            <div class="space-y-6">
                <header>
                    <h2 class="doc-title text-2xl font-semibold">
                        Profile information
                    </h2>
                    <p class="doc-subtle mt-2 text-sm">
                        Update your account identity and verified email address.
                    </p>
                </header>

                <Form
                    v-bind="ProfileController.update.form()"
                    class="grid gap-6"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <div class="grid gap-2">
                        <Label for="name" class="workspace-label">Name</Label>
                        <Input
                            id="name"
                            class="workspace-input"
                            name="name"
                            :default-value="user.name"
                            required
                            autocomplete="name"
                            placeholder="Full name"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email" class="workspace-label"
                            >Email address</Label
                        >
                        <Input
                            id="email"
                            type="email"
                            class="workspace-input"
                            name="email"
                            :default-value="user.email"
                            required
                            autocomplete="username"
                            placeholder="Email address"
                        />
                        <InputError :message="errors.email" />
                    </div>

                    <div
                        v-if="mustVerifyEmail && !user.email_verified_at"
                        class="workspace-alert"
                    >
                        <p class="text-sm">
                            Your email address is unverified.
                            <Link
                                :href="send()"
                                as="button"
                                class="font-semibold underline underline-offset-4"
                            >
                                Resend verification email
                            </Link>
                        </p>
                        <p
                            v-if="status === 'verification-link-sent'"
                            class="mt-2 text-sm font-medium text-[hsl(146_62%_30%)]"
                        >
                            A new verification link has been sent.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <Button
                            :disabled="processing"
                            class="workspace-primary-button"
                            data-test="update-profile-button"
                        >
                            Save profile
                        </Button>
                        <p v-if="recentlySuccessful" class="doc-subtle text-sm">
                            Saved.
                        </p>
                    </div>
                </Form>
            </div>

            <DeleteUser />
        </SettingsLayout>
    </AppLayout>
</template>
