<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    AppWindow,
    ExternalLink,
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
import { index as applications } from '@/routes/applications';
import { index as audit } from '@/routes/audit';
import { index as sessions } from '@/routes/sessions';
import { index as users } from '@/routes/users';
import type { NavItem } from '@/types';

const page = usePage();

/*
 * Only what this user can actually open. The abilities come from the server,
 * which decides them with the same policies that guard the routes.
 */
const mainNavItems = computed<NavItem[]>(() => {
    const can = page.props.can;

    return [
        { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
        ...(can.viewApplications
            ? [{ title: 'Applications', href: applications(), icon: AppWindow }]
            : []),
        ...(can.viewUsers
            ? [{ title: 'Users', href: users(), icon: Users }]
            : []),
        ...(can.viewUsers
            ? [
                  {
                      title: 'Sessions',
                      href: sessions(),
                      icon: MonitorSmartphone,
                  },
              ]
            : []),
        ...(can.viewAudit
            ? [{ title: 'Audit', href: audit(), icon: ScrollText }]
            : []),
    ];
});

/*
 * Whatever an administrator listed, including the protocol reference they
 * start with. Nothing is hard-coded here, so a link can be reworded, reordered
 * or removed from the interface rather than from this file.
 */
const footerNavItems = computed<NavItem[]>(() =>
    page.props.branding.documentationLinks.map((link) => ({
        title: link.label,
        href: link.url,
        icon: ExternalLink,
    })),
);
</script>

<template>
    <Sidebar collapsible="icon" :variant="page.props.branding.sidebarVariant">
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
            <!--
                Reference links, for whoever integrates applications. A member
                who only signs in through this server has no application to
                point at them.
            -->
            <NavFooter
                v-if="page.props.can.viewApplications"
                :items="footerNavItems"
            />

            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
