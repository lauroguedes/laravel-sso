<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    AppWindow,
    BookOpen,
    LayoutGrid,
    MonitorSmartphone,
    ScrollText,
    Users,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import DiscoveryDialog from '@/components/DiscoveryDialog.vue';
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

/*
 * Built from the issuer rather than route(), which would use APP_URL: the
 * discovery URL is the one relying parties fetch, and behind a proxy the two
 * differ. The server puts the canonical value on every page.
 */
const page = usePage();

const discoveryUrl = computed(() => page.props.sso.discoveryUrl);

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

const footerNavItems: NavItem[] = [
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
            <!--
                Protocol references, for whoever integrates applications. A
                member who only signs in through this server has no use for the
                discovery document and no application to point at it.
            -->
            <template v-if="page.props.can.viewApplications">
                <SidebarMenu class="px-2 group-data-[collapsible=icon]:p-0">
                    <DiscoveryDialog :url="discoveryUrl" />
                </SidebarMenu>

                <NavFooter :items="footerNavItems" />
            </template>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
