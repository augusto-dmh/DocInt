<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { LayoutGrid, Menu } from 'lucide-vue-next';
import AppLogo from '@/components/AppLogo.vue';
import { getInitials } from '@/composables/useInitials';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();
</script>

<template>
    <div>
        <div class="workspace-topbar border-b border-[var(--doc-border)]/70">
            <div class="mx-auto flex h-16 items-center px-4 md:max-w-7xl">
                <div class="lg:hidden">
                    <Sheet>
                        <SheetTrigger :as-child="true">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="mr-2 h-9 w-9 rounded-lg"
                            >
                                <Menu class="h-5 w-5" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent
                            side="left"
                            class="workspace-sidebar w-[280px] p-6"
                        >
                            <SheetTitle class="sr-only"
                                >Navigation Menu</SheetTitle
                            >
                            <div class="space-y-4 py-4">
                                <Link
                                    :href="dashboard()"
                                    class="flex items-center gap-3 rounded-xl p-2"
                                >
                                    <AppLogo />
                                </Link>
                                <Link
                                    :href="dashboard()"
                                    class="flex items-center gap-2 rounded-xl bg-[var(--doc-seal)]/10 px-3 py-2 text-sm font-medium text-[var(--doc-seal)]"
                                >
                                    <LayoutGrid class="size-4" />
                                    Dashboard
                                </Link>
                            </div>
                        </SheetContent>
                    </Sheet>
                </div>

                <Link :href="dashboard()" class="flex items-center gap-x-2">
                    <AppLogo />
                </Link>

                <div class="ml-auto flex items-center space-x-2">
                    <DropdownMenu>
                        <DropdownMenuTrigger :as-child="true">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="relative size-10 w-auto rounded-full p-1 focus-within:ring-2 focus-within:ring-primary"
                            >
                                <Avatar
                                    class="size-8 overflow-hidden rounded-full"
                                >
                                    <AvatarImage
                                        v-if="page.props.auth.user.avatar"
                                        :src="page.props.auth.user.avatar"
                                        :alt="page.props.auth.user.name"
                                    />
                                    <AvatarFallback
                                        class="rounded-lg bg-secondary font-semibold text-secondary-foreground"
                                    >
                                        {{ getInitials(page.props.auth.user.name) }}
                                    </AvatarFallback>
                                </Avatar>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
                            <UserMenuContent :user="page.props.auth.user" />
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </div>

        <div
            v-if="breadcrumbs.length > 1"
            class="workspace-topbar flex w-full border-b border-[var(--doc-border)]/55"
        >
            <div
                class="mx-auto flex h-12 w-full items-center px-4 text-[var(--doc-muted)] md:max-w-7xl"
            >
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </div>
        </div>
    </div>
</template>
