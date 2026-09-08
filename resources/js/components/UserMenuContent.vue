<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { FolderGit2, LogOut, Settings } from '@lucide/vue';
import { computed } from 'vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

type Props = {
    user: User;
};

const handleLogout = () => {
    router.flushAll();
};

/*
 * The discovery document is for whoever integrates an application, which is
 * the same reader who can see the applications section. A member who only
 * signs in through this server has nothing to point at it.
 */
const page = usePage();
const canIntegrate = computed(() => page.props.can.viewApplications);

const emit = defineEmits<{ showDiscovery: [] }>();

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
        <DropdownMenuItem
            v-if="canIntegrate"
            class="cursor-pointer"
            @select="emit('showDiscovery')"
        >
            <FolderGit2 class="mr-2 h-4 w-4" />
            Discovery document
        </DropdownMenuItem>

        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" :href="edit()" prefetch>
                <Settings class="mr-2 h-4 w-4" />
                Settings
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <!--
        Signing out is the one item here that ends something, so it carries the
        destructive colour the rest of the interface uses for that.
    -->
    <DropdownMenuItem
        :as-child="true"
        class="text-destructive focus:text-destructive focus:bg-destructive/10"
    >
        <Link
            class="block w-full cursor-pointer"
            :href="logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="text-destructive mr-2 h-4 w-4" />
            Log out
        </Link>
    </DropdownMenuItem>
</template>
