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
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime, formatTimestamp } from '@/lib/datetime';
import { index } from '@/routes/sessions';
import { destroy as revokeSession } from '@/routes/sessions';
import { destroy as revokeToken } from '@/routes/tokens';
import type { BrowserSession, IssuedToken } from '@/types/administration';

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
</script>

<template>
    <Head title="Sessions" />

    <div class="px-4 py-6">
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

                    <div v-else class="overflow-x-auto rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>User</TableHead>
                                    <TableHead>Address</TableHead>
                                    <TableHead>Last activity</TableHead>
                                    <TableHead class="w-12" />
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                <TableEmpty
                                    v-if="browserSessions.length === 0"
                                    :colspan="4"
                                >
                                    Nobody is signed in.
                                </TableEmpty>

                                <TableRow
                                    v-for="session in browserSessions"
                                    :key="session.id"
                                >
                                    <TableCell>
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium">
                                                {{ session.user.name }}
                                            </span>
                                            <Badge
                                                v-if="session.current"
                                                variant="secondary"
                                            >
                                                This session
                                            </Badge>
                                        </div>
                                        <div
                                            class="text-muted-foreground text-sm"
                                        >
                                            {{ session.user.email }}
                                        </div>
                                    </TableCell>

                                    <TableCell class="text-sm">
                                        <div>
                                            {{ session.ip_address ?? '—' }}
                                        </div>
                                        <div
                                            class="text-muted-foreground line-clamp-1 max-w-xs text-xs"
                                        >
                                            {{ session.user_agent }}
                                        </div>
                                    </TableCell>

                                    <TableCell
                                        class="text-muted-foreground text-sm whitespace-nowrap"
                                    >
                                        {{
                                            formatTimestamp(
                                                session.last_activity,
                                            )
                                        }}
                                    </TableCell>

                                    <TableCell class="text-right">
                                        <DangerousAction
                                            v-if="canManage"
                                            title="End this session?"
                                            :description="`${session.user.name} will be signed out of this server. Tokens applications already hold are not affected.`"
                                            confirm-label="End session"
                                            @confirm="endSession(session)"
                                        >
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                :aria-label="`End session for ${session.user.name}`"
                                            >
                                                <X class="size-4" />
                                            </Button>
                                        </DangerousAction>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
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
                    <div class="overflow-x-auto rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>User</TableHead>
                                    <TableHead>Application</TableHead>
                                    <TableHead>Scopes</TableHead>
                                    <TableHead>Expires</TableHead>
                                    <TableHead class="w-12" />
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                <TableEmpty
                                    v-if="tokens.length === 0"
                                    :colspan="5"
                                >
                                    No application holds a token.
                                </TableEmpty>

                                <TableRow
                                    v-for="token in tokens"
                                    :key="token.id"
                                >
                                    <TableCell>
                                        <div class="font-medium">
                                            {{ token.user.name }}
                                        </div>
                                        <div
                                            class="text-muted-foreground text-sm"
                                        >
                                            {{ token.user.email }}
                                        </div>
                                    </TableCell>

                                    <TableCell class="text-sm">
                                        {{ token.application ?? '—' }}
                                    </TableCell>

                                    <TableCell>
                                        <div class="flex flex-wrap gap-1">
                                            <Badge
                                                v-for="scope in token.scopes"
                                                :key="scope"
                                                variant="outline"
                                                class="font-mono"
                                            >
                                                {{ scope }}
                                            </Badge>
                                        </div>
                                    </TableCell>

                                    <TableCell
                                        class="text-muted-foreground text-sm whitespace-nowrap"
                                    >
                                        {{ formatDateTime(token.expires_at) }}
                                    </TableCell>

                                    <TableCell class="text-right">
                                        <DangerousAction
                                            v-if="canManage"
                                            title="Revoke this token?"
                                            :description="`${token.application} will stop being able to act as ${token.user.name} until they authorize it again.`"
                                            confirm-label="Revoke token"
                                            @confirm="endToken(token)"
                                        >
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                :aria-label="`Revoke token for ${token.user.name}`"
                                            >
                                                <X class="size-4" />
                                            </Button>
                                        </DangerousAction>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
