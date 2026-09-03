<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, TriangleAlert } from '@lucide/vue';
import CopyButton from '@/components/CopyButton.vue';
import CredentialValue from '@/components/applications/CredentialValue.vue';
import DangerousAction from '@/components/DangerousAction.vue';
import Heading from '@/components/Heading.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { edit, index } from '@/routes/applications';
import { update as updateSecret } from '@/routes/applications/secret';
import { update as updateStatus } from '@/routes/applications/status';
import type { ApplicationDetail } from '@/types/administration';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Applications', href: index() }],
    },
});

const { application } = defineProps<{
    application: ApplicationDetail;
    issuer: string;
    discoveryUrl: string;
    clientSecret: string | null;
    canManage: boolean;
    canRegenerateSecret: boolean;
}>();

function setEnabled(enabled: boolean) {
    router.put(updateStatus(application.id).url, { enabled });
}

function regenerateSecret() {
    router.put(updateSecret(application.id).url);
}
</script>

<template>
    <Head :title="application.name" />

    <div class="max-w-3xl px-4 py-6">
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="application.name"
                :description="application.description ?? application.type_label"
            />

            <div class="flex items-center gap-2">
                <Badge
                    :variant="application.enabled ? 'secondary' : 'destructive'"
                >
                    {{ application.enabled ? 'Enabled' : 'Disabled' }}
                </Badge>

                <Button v-if="canManage" variant="outline" as-child>
                    <Link :href="edit(application.id)">
                        <Pencil class="size-4" />
                        Edit
                    </Link>
                </Button>
            </div>
        </div>

        <div class="space-y-6">
            <Alert v-if="clientSecret" class="border-amber-500/50">
                <TriangleAlert class="size-4" />
                <AlertTitle>Copy the client secret now</AlertTitle>
                <AlertDescription class="space-y-3">
                    <p>
                        This is the only time it will be shown. It is stored as
                        a hash and cannot be recovered — if you lose it you will
                        have to generate a new one.
                    </p>
                    <div class="flex w-full flex-wrap items-center gap-2">
                        <code
                            class="bg-muted min-w-0 flex-1 overflow-x-auto rounded px-3 py-2 font-mono text-sm"
                        >
                            {{ clientSecret }}
                        </code>
                        <CopyButton :value="clientSecret" label="Copy secret" />
                    </div>
                </AlertDescription>
            </Alert>

            <Card>
                <CardHeader>
                    <CardTitle>Credentials</CardTitle>
                    <CardDescription>
                        The values an OpenID Connect client library needs to
                        connect to this server.
                    </CardDescription>
                </CardHeader>

                <CardContent class="space-y-4">
                    <CredentialValue label="Issuer" :value="issuer" />

                    <CredentialValue
                        label="Discovery document"
                        :value="discoveryUrl"
                    />

                    <CredentialValue
                        label="Client ID"
                        :value="application.id"
                    />

                    <CredentialValue
                        v-if="application.confidential"
                        label="Client secret"
                        value="••••••••••••••••••••••••••••••••"
                        :copyable="false"
                    >
                        <DangerousAction
                            v-if="canRegenerateSecret"
                            title="Generate a new client secret?"
                            description="The current secret stops working immediately. Every integration using it will fail until it is updated."
                            confirm-label="Generate new secret"
                            @confirm="regenerateSecret"
                        >
                            <Button variant="outline" size="sm">
                                Regenerate
                            </Button>
                        </DangerousAction>
                    </CredentialValue>

                    <p v-else class="text-muted-foreground text-sm">
                        This is a public client. It has no secret and
                        authenticates with PKCE instead.
                    </p>
                </CardContent>
            </Card>

            <Card v-if="application.redirect_uris.length > 0">
                <CardHeader>
                    <CardTitle>Redirect URIs</CardTitle>
                    <CardDescription>
                        Matched exactly when an authorization request arrives.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <ul class="space-y-2">
                        <li
                            v-for="uri in application.redirect_uris"
                            :key="uri"
                            class="bg-muted overflow-x-auto rounded px-3 py-2 font-mono text-sm"
                        >
                            {{ uri }}
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Scopes</CardTitle>
                    <CardDescription>
                        Anything not listed here is dropped when a token is
                        issued.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <div
                        v-if="application.scopes.length > 0"
                        class="flex flex-wrap gap-2"
                    >
                        <Badge
                            v-for="scope in application.scopes"
                            :key="scope"
                            variant="outline"
                            class="font-mono"
                        >
                            {{ scope }}
                        </Badge>
                    </div>
                    <p v-else class="text-muted-foreground text-sm">
                        No scopes selected, so this application cannot request
                        any.
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Consent</CardTitle>
                </CardHeader>

                <CardContent class="text-sm">
                    <template v-if="application.skips_authorization">
                        Users are not asked to approve this application. Only
                        appropriate for applications you own.
                    </template>
                    <template v-else>
                        Users are asked to approve this application the first
                        time it requests access.
                    </template>
                </CardContent>
            </Card>

            <Card v-if="canManage" class="border-destructive/40">
                <CardHeader>
                    <CardTitle>
                        {{
                            application.enabled
                                ? 'Disable application'
                                : 'Enable application'
                        }}
                    </CardTitle>
                    <CardDescription>
                        <template v-if="application.enabled">
                            Disabling revokes every token this application holds
                            and refuses new ones.
                        </template>
                        <template v-else>
                            The application can request new tokens again.
                            Previously revoked tokens stay revoked.
                        </template>
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <Button
                        v-if="!application.enabled"
                        variant="outline"
                        @click="setEnabled(true)"
                    >
                        Enable application
                    </Button>

                    <DangerousAction
                        v-else
                        title="Disable this application?"
                        :description="`${application.name} will stop working immediately and every token it holds will be revoked.`"
                        confirm-label="Disable application"
                        @confirm="setEnabled(false)"
                    >
                        <Button variant="destructive">
                            Disable application
                        </Button>
                    </DangerousAction>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
