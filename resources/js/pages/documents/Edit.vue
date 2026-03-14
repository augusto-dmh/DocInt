<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Document, Matter } from '@/types';

const isDeleteDialogOpen = ref(false);

const props = defineProps<{
    document: Document & { matter: Matter };
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Documents',
        href: DocumentController.index.url(),
    },
    {
        title: props.document.title,
        href: DocumentController.show.url(props.document),
    },
    {
        title: 'Edit',
    },
];

const page = usePage();
const canDeleteDocument = computed(() =>
    page.props.auth.permissions.includes('delete documents'),
);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Edit ${document.title}`" />

        <div
            class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 rounded-xl p-4"
        >
            <div class="space-y-2">
                <h1 class="text-2xl font-semibold tracking-tight">
                    Edit Document
                </h1>
                <p class="text-sm text-muted-foreground">
                    Update the document title and manage its stored file record.
                </p>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 p-6">
                <Form
                    v-bind="DocumentController.update.form(document)"
                    v-slot="{ errors, processing, recentlySuccessful }"
                    class="space-y-6"
                >
                    <div class="grid gap-2">
                        <Label for="title">Title</Label>
                        <Input
                            id="title"
                            name="title"
                            required
                            :default-value="document.title"
                            placeholder="Document title"
                        />
                        <InputError :message="errors.title" />
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <Button :disabled="processing">Save Changes</Button>
                        <Button as-child variant="outline">
                            <Link :href="DocumentController.show(document)"
                                >Cancel</Link
                            >
                        </Button>
                        <p
                            v-if="recentlySuccessful"
                            class="text-sm text-muted-foreground"
                        >
                            Saved.
                        </p>
                    </div>
                </Form>
            </div>

            <div
                v-if="canDeleteDocument"
                class="rounded-xl border border-destructive/30 p-6"
            >
                <h2 class="text-lg font-semibold text-destructive">
                    Delete Document
                </h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    This removes the document record and deletes the stored S3
                    file for the active tenant.
                </p>

                <Dialog v-model:open="isDeleteDialogOpen">
                    <DialogTrigger as-child>
                        <Button variant="destructive" class="mt-4">
                            Delete Document
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle
                                >Delete {{ document.title }}?</DialogTitle
                            >
                            <DialogDescription>
                                This action cannot be undone.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                @click="isDeleteDialogOpen = false"
                            >
                                Cancel
                            </Button>
                            <Form
                                v-bind="
                                    DocumentController.destroy.form(document)
                                "
                                v-slot="{ processing }"
                            >
                                <Button
                                    variant="destructive"
                                    :disabled="processing"
                                >
                                    Delete Document
                                </Button>
                            </Form>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </div>
    </AppLayout>
</template>
