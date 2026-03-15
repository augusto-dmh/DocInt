<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import PasswordController from '@/actions/App/Http/Controllers/Settings/PasswordController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/user-password';
import { type BreadcrumbItem } from '@/types';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Password settings',
        href: edit().url,
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Password settings" />

        <SettingsLayout>
            <div class="space-y-6">
                <header>
                    <h2 class="doc-title text-2xl font-semibold">
                        Password security
                    </h2>
                    <p class="doc-subtle mt-2 text-sm">
                        Use a long random password and rotate credentials
                        regularly.
                    </p>
                </header>

                <Form
                    v-bind="PasswordController.update.form()"
                    :options="{ preserveScroll: true }"
                    reset-on-success
                    :reset-on-error="[
                        'password',
                        'password_confirmation',
                        'current_password',
                    ]"
                    class="grid gap-6"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <div class="grid gap-2">
                        <Label for="current_password" class="workspace-label"
                            >Current password</Label
                        >
                        <Input
                            id="current_password"
                            name="current_password"
                            type="password"
                            class="workspace-input"
                            autocomplete="current-password"
                            placeholder="Current password"
                        />
                        <InputError :message="errors.current_password" />
                    </div>

                    <div class="grid gap-2 sm:grid-cols-2 sm:gap-4">
                        <div class="grid gap-2">
                            <Label for="password" class="workspace-label"
                                >New password</Label
                            >
                            <Input
                                id="password"
                                name="password"
                                type="password"
                                class="workspace-input"
                                autocomplete="new-password"
                                placeholder="New password"
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <div class="grid gap-2">
                            <Label
                                for="password_confirmation"
                                class="workspace-label"
                            >
                                Confirm password
                            </Label>
                            <Input
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                class="workspace-input"
                                autocomplete="new-password"
                                placeholder="Confirm password"
                            />
                            <InputError
                                :message="errors.password_confirmation"
                            />
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <Button
                            :disabled="processing"
                            class="workspace-primary-button"
                            data-test="update-password-button"
                        >
                            Save password
                        </Button>
                        <p v-if="recentlySuccessful" class="doc-subtle text-sm">
                            Saved.
                        </p>
                    </div>
                </Form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
