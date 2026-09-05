<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    AppWindow,
    BookOpen,
    FolderGit2,
    LayoutGrid,
    MonitorSmartphone,
    ScrollText,
    Users,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
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
import { discovery } from '@/routes/oidc';
import { index as applications } from '@/routes/applications';
import { index as audit } from '@/routes/audit';
import { index as sessions } from '@/routes/sessions';
import { index as users } from '@/routes/users';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Applications',
        href: applications(),
        icon: AppWindow,
    },
    {
        title: 'Users',
        href: users(),
        icon: Users,
    },
    {
        title: 'Sessions',
        href: sessions(),
        icon: MonitorSmartphone,
    },
    {
        title: 'Audit',
        href: audit(),
        icon: ScrollText,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Discovery document',
        href: discovery(),
        icon: FolderGit2,
    },
    {
        title: 'OpenID Connect',
        href: 'https://openid.net/developers/how-connect-works/',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
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

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
