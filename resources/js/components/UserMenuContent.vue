<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Building2, LogOut, Settings } from 'lucide-vue-next';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import { edit as editTenantContext } from '@/routes/tenant-context';
import type { User } from '@/types';

type Props = {
    user: User;
};

const handleLogout = () => {
    router.flushAll();
};

const page = usePage();

defineProps<Props>();
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
            <UserInfo :user="user" :show-email="true" />
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link
                class="block w-full cursor-pointer rounded-lg"
                :href="edit()"
                prefetch
            >
                <Settings class="mr-2 h-4 w-4" />
                Account settings
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem v-if="page.props.auth.isSuperAdmin" :as-child="true">
            <Link
                class="block w-full cursor-pointer rounded-lg"
                :href="editTenantContext()"
                prefetch
            >
                <Building2 class="mr-2 h-4 w-4" />
                Tenant context
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link
            class="block w-full cursor-pointer"
            :href="logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            Log out
        </Link>
    </DropdownMenuItem>
</template>
