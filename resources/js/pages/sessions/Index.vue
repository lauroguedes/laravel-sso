<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { X } from '@lucide/vue';
import DangerousAction from '@/components/DangerousAction.vue';
import Heading from '@/components/Heading.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import DataTable from '@/components/DataTable.vue';
import { formatDateTime, formatTimestamp } from '@/lib/datetime';
import { index } from '@/routes/sessions';
import { destroy as revokeSession } from '@/routes/sessions';
import { destroy as revokeToken } from '@/routes/tokens';
import type {
    DataTableColumn,
    BrowserSession,
    IssuedToken,
} from '@/types/administration';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Sessions', href: index() }],
    },
});

defineProps<{
    tracksSessions: boolean;
    browserSessions: BrowserSession[];
    tokens: IssuedToken[];
    canManage: boolean;
}>();

function endSession(session: BrowserSession) {
    router.delete(revokeSession(session.id).url);
}

function endToken(token: IssuedToken) {
    router.delete(revokeToken(token.id).url);
}

const sessionColumns: DataTableColumn[] = [
    { id: 'user', header: 'User', alwaysVisible: true },
    { id: 'ip_address', header: 'Address' },
    { id: 'last_activity', header: 'Last activity' },
    { id: 'actions', header: '', align: 'right', alwaysVisible: true },
];

const tokenColumns: DataTableColumn[] = [
    { id: 'user', header: 'User', alwaysVisible: true },
    { id: 'application', header: 'Application' },
    { id: 'scopes', header: 'Scopes' },
    { id: 'expires_at', header: 'Expires' },
    { id: 'actions', header: '', align: 'right', alwaysVisible: true },
];
</script>

<template>
    <Head title="Sessions" />

    <div class="mx-auto w-full max-w-5xl space-y-6 px-4 py-8">
        <Heading
            title="Sessions"
            description="Who is signed in here, and which applications hold tokens on their behalf"
        />

        <div class="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>Browser sessions</CardTitle>
                    <CardDescription>
                        Sessions on this server. Ending one signs the user out
                        of the administration interface, but does not revoke
                        tokens applications already hold.
                    </CardDescription>
                </CardHeader>

                <CardContent class="space-y-4">
                    <Alert v-if="!tracksSessions">
                        <AlertDescription>
                            Sessions are not stored in the database, so they
                            cannot be listed here. Set
                            <code class="font-mono"
                                >SESSION_DRIVER=database</code
                            >
                            to inspect them.
                        </AlertDescription>
                    </Alert>

                    <DataTable
                        v-else
                        :columns="sessionColumns"
                        :rows="browserSessions"
                        :row-key="(session) => session.id"
                        empty="Nobody is signed in."
                    >
                        <template #cell-user="{ row }">
                            <div class="flex items-center gap-2">
                                <span class="font-medium">
                                    {{ row.user.name }}
                                </span>
                                <Badge v-if="row.current" variant="secondary">
                                    This session
                                </Badge>
                            </div>
                            <div class="text-muted-foreground text-sm">
                                {{ row.user.email }}
                            </div>
                        </template>

                        <template #cell-ip_address="{ row }">
                            <div class="text-sm">
                                {{ row.ip_address ?? '—' }}
                            </div>
                            <div
                                class="text-muted-foreground line-clamp-1 max-w-xs text-xs"
                            >
                                {{ row.user_agent }}
                            </div>
                        </template>

                        <template #cell-last_activity="{ row }">
                            <span
                                class="text-muted-foreground text-sm whitespace-nowrap"
                            >
                                {{ formatTimestamp(row.last_activity) }}
                            </span>
                        </template>

                        <template #cell-actions="{ row }">
                            <DangerousAction
                                v-if="canManage"
                                title="End this session?"
                                :description="`${row.user.name} will be signed out of this server. Tokens applications already hold are not affected.`"
                                confirm-label="End session"
                                @confirm="endSession(row)"
                            >
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    :aria-label="`End session for ${row.user.name}`"
                                >
                                    <X class="size-4" />
                                </Button>
                            </DangerousAction>
                        </template>
                    </DataTable>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Issued tokens</CardTitle>
                    <CardDescription>
                        Access tokens applications hold on a user's behalf.
                        Revoking one stops that application acting as them.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <DataTable
                        :columns="tokenColumns"
                        :rows="tokens"
                        :row-key="(token) => token.id"
                        empty="No application holds a token."
                    >
                        <template #cell-user="{ row }">
                            <div class="font-medium">{{ row.user.name }}</div>
                            <div class="text-muted-foreground text-sm">
                                {{ row.user.email }}
                            </div>
                        </template>

                        <template #cell-application="{ row }">
                            <span class="text-sm">
                                {{ row.application ?? '—' }}
                            </span>
                        </template>

                        <template #cell-scopes="{ row }">
                            <div class="flex flex-wrap gap-1">
                                <Badge
                                    v-for="scope in row.scopes"
                                    :key="scope"
                                    variant="outline"
                                    class="font-mono"
                                >
                                    {{ scope }}
                                </Badge>
                            </div>
                        </template>

                        <template #cell-expires_at="{ row }">
                            <span
                                class="text-muted-foreground text-sm whitespace-nowrap"
                            >
                                {{ formatDateTime(row.expires_at) }}
                            </span>
                        </template>

                        <template #cell-actions="{ row }">
                            <DangerousAction
                                v-if="canManage"
                                title="Revoke this token?"
                                :description="`${row.application} will stop being able to act as ${row.user.name} until they authorize it again.`"
                                confirm-label="Revoke token"
                                @confirm="endToken(row)"
                            >
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    :aria-label="`Revoke token for ${row.user.name}`"
                                >
                                    <X class="size-4" />
                                </Button>
                            </DangerousAction>
                        </template>
                    </DataTable>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
