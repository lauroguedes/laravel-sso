<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ChevronsUpDown } from '@lucide/vue';
import { computed, ref } from 'vue';
import DiscoveryDialog from '@/components/DiscoveryDialog.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import UserInfo from '@/components/UserInfo.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const { isMobile, state } = useSidebar();

/*
 * Held here rather than in the menu: choosing the item closes the menu, and a
 * dialog rendered inside it would be unmounted in the same moment.
 */
const discoveryOpen = ref(false);
const discoveryUrl = computed(() => page.props.sso.discoveryUrl);
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        size="lg"
                        class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                        data-test="sidebar-menu-button"
                    >
                        <UserInfo :user="user" />
                        <ChevronsUpDown class="ml-auto size-4" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                    :side="
                        isMobile
                            ? 'bottom'
                            : state === 'collapsed'
                              ? 'left'
                              : 'bottom'
                    "
                    align="end"
                    :side-offset="4"
                >
                    <UserMenuContent
                        :user="user"
                        @show-discovery="discoveryOpen = true"
                    />
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>

    <DiscoveryDialog v-model:open="discoveryOpen" :url="discoveryUrl" />
</template>
