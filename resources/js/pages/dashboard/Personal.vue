<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import StatusIndicator from '@/components/StatusIndicator.vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatDateTime } from '@/lib/datetime';
import { dashboard } from '@/routes';

/**
 * What one person can see about their own account.
 *
 * Read only by design: everything here is a fact about the reader that an
 * administrator maintains elsewhere. There is nothing to act on, so there are
 * no controls to mistake for ones.
 */
defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

defineProps<{
    user: {
        name: string;
        email: string;
        email_verified: boolean;
        last_login_at: string | null;
        two_factor_enabled: boolean;
    };
    access: {
        application: string;
        description: string | null;
        enabled: boolean;
        role: string | null;
        permissions: string[];
    }[];
    platformRoles: string[];
}>();
</script>

<template>
    <Head title="Dashboard" />

    <div class="mx-auto w-full max-w-5xl space-y-6 px-4 py-8">
        <Heading
            :title="`Welcome, ${user.name}`"
            description="Your account, and what it can reach through this Identity Provider"
        />

        <div class="grid items-start gap-6 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Your account</CardTitle>
                    <CardDescription>
                        Change your name, address or password under Settings.
                    </CardDescription>
                </CardHeader>

                <CardContent class="grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <div class="text-muted-foreground">Email address</div>
                        <div class="flex items-center gap-2">
                            {{ user.email }}
                            <Badge
                                v-if="!user.email_verified"
                                variant="outline"
                            >
                                Unverified
                            </Badge>
                        </div>
                    </div>

                    <div>
                        <div class="text-muted-foreground">Last sign-in</div>
                        <div>
                            {{ formatDateTime(user.last_login_at, 'Never') }}
                        </div>
                    </div>

                    <div>
                        <div class="text-muted-foreground">
                            Two-factor authentication
                        </div>
                        <div>{{ user.two_factor_enabled ? 'On' : 'Off' }}</div>
                    </div>

                    <div v-if="platformRoles.length > 0">
                        <div class="text-muted-foreground">Platform roles</div>
                        <div class="flex flex-wrap gap-1">
                            <Badge
                                v-for="role in platformRoles"
                                :key="role"
                                variant="secondary"
                            >
                                {{ role }}
                            </Badge>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Applications you can sign in to</CardTitle>
                    <CardDescription>
                        Granted by an administrator. The role and permissions
                        listed are reported to that application when you sign
                        in; the application decides what they allow.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <ul
                        v-if="access.length > 0"
                        class="divide-y rounded-lg border"
                    >
                        <li
                            v-for="entry in access"
                            :key="entry.application"
                            class="space-y-2 px-3 py-3"
                        >
                            <div class="flex items-center gap-2">
                                <StatusIndicator
                                    :active="entry.enabled"
                                    active-label="Enabled"
                                    inactive-label="Disabled"
                                />
                                <span class="font-medium">
                                    {{ entry.application }}
                                </span>
                                <Badge v-if="entry.role" variant="secondary">
                                    {{ entry.role }}
                                </Badge>
                                <span
                                    v-else
                                    class="text-muted-foreground text-sm"
                                >
                                    No role
                                </span>
                            </div>

                            <p
                                v-if="entry.description"
                                class="text-muted-foreground text-sm"
                            >
                                {{ entry.description }}
                            </p>

                            <div
                                v-if="entry.permissions.length > 0"
                                class="flex flex-wrap gap-1"
                            >
                                <Badge
                                    v-for="permission in entry.permissions"
                                    :key="permission"
                                    variant="outline"
                                    class="font-mono"
                                >
                                    {{ permission }}
                                </Badge>
                            </div>
                        </li>
                    </ul>

                    <p v-else class="text-muted-foreground text-sm">
                        You have not been granted access to any application yet.
                        Applications that do not restrict access will still let
                        you sign in.
                    </p>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
