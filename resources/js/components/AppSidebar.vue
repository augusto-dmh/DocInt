<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Briefcase,
    FileText,
    LayoutGrid,
    Settings,
    Users,
} from 'lucide-vue-next';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as clientsIndex } from '@/routes/clients';
import { index as documentsIndex } from '@/routes/documents';
import { index as mattersIndex } from '@/routes/matters';
import { edit as editProfile } from '@/routes/profile';
import { type NavItem } from '@/types';
import AppLogo from './AppLogo.vue';

const page = usePage();
const tenantContext = page.props.tenantContext as
    | { activeTenant?: { name: string } | null }
    | undefined;

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Clients',
        href: clientsIndex(),
        icon: Users,
    },
    {
        title: 'Matters',
        href: mattersIndex(),
        icon: Briefcase,
    },
    {
        title: 'Documents',
        href: documentsIndex(),
        icon: FileText,
    },
    {
        title: 'Settings',
        href: editProfile(),
        icon: Settings,
    },
];
</script>

<template>
    <Sidebar
        collapsible="offcanvas"
        variant="inset"
        class="workspace-sidebar border-r border-[var(--doc-border)]/70"
    >
        <SidebarHeader class="border-b border-[var(--doc-border)]/70 px-3 py-3">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child class="h-auto p-1.5">
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter class="border-t border-[var(--doc-border)]/70 px-3 py-3">
            <p class="doc-subtle px-2 text-[11px] tracking-[0.11em] uppercase">
                {{
                    tenantContext?.activeTenant
                        ? `Context: ${tenantContext.activeTenant.name}`
                        : 'Tenant-scoped security'
                }}
            </p>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
