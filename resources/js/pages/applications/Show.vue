<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { RefreshCw, TriangleAlert } from '@lucide/vue';
import ApplicationLayout from '@/layouts/applications/Layout.vue';
import InputGroupIconButton from '@/components/InputGroupIconButton.vue';
import ReadOnlyField from '@/components/ReadOnlyField.vue';
import DangerousAction from '@/components/DangerousAction.vue';
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
import { index } from '@/routes/applications';
import { update as updateSecret } from '@/routes/applications/secret';
import { update as updateStatus } from '@/routes/applications/status';
import { destroy as revokeTokens } from '@/routes/applications/tokens';
import type {
    ApplicationDetail,
    ApplicationSection,
} from '@/types/administration';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Applications', href: index() }],
    },
});

/*
 * Shared on every page rather than passed per controller, so the value has one
 * source. See HandleInertiaRequests.
 */
const discoveryUrl = computed(() => usePage().props.sso.discoveryUrl);

const { application } = defineProps<{
    application: ApplicationDetail;
    sections: ApplicationSection[];
    issuer: string;
    clientSecret: string | null;
    canManage: boolean;
    canChangeStatus: boolean;
    canRegenerateSecret: boolean;
}>();

function setEnabled(enabled: boolean) {
    router.put(updateStatus(application.id).url, { enabled });
}

const regenerating = ref(false);

function regenerateSecret() {
    router.put(updateSecret(application.id).url);
}

function revokeAllTokens() {
    router.delete(revokeTokens(application.id).url);
}
</script>

<template>
    <Head :title="application.name" />

    <ApplicationLayout
        :application="application"
        :sections="sections"
        :description="application.description ?? application.type_label"
        :can-manage="canManage"
    >
        <Alert v-if="clientSecret" class="border-amber-500/50">
            <TriangleAlert class="size-4" />
            <AlertTitle>Copy the client secret now</AlertTitle>
            <AlertDescription class="space-y-3">
                <p>
                    This is the only time it will be shown. It is stored as a
                    hash and cannot be recovered — if you lose it you will have
                    to generate a new one.
                </p>
                <ReadOnlyField
                    label="Client secret"
                    label-hidden
                    :value="clientSecret"
                    copy-label="Copy client secret"
                />
            </AlertDescription>
        </Alert>

        <Card>
            <CardHeader>
                <CardTitle>Credentials</CardTitle>
                <CardDescription>
                    The values an OpenID Connect client library needs to connect
                    to this server.
                </CardDescription>
            </CardHeader>

            <CardContent class="space-y-4">
                <ReadOnlyField label="Issuer" :value="issuer" />

                <ReadOnlyField
                    label="Discovery document"
                    :value="discoveryUrl"
                />

                <ReadOnlyField label="Client ID" :value="application.id" />

                <!--
                    Copyable only while a freshly issued secret is still in
                    hand. What is stored is a hash, so at any other time there
                    is nothing to copy and a button offering to would be
                    copying the mask.
                -->
                <ReadOnlyField
                    v-if="application.confidential"
                    label="Client secret"
                    :value="clientSecret ?? '••••••••••••••••••••••••••••••••'"
                    :copyable="clientSecret !== null"
                    copy-label="Copy client secret"
                >
                    <TooltipProvider
                        v-if="canRegenerateSecret"
                        :delay-duration="150"
                    >
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <InputGroupButton
                                    size="icon-xs"
                                    aria-label="Regenerate client secret"
                                    @click="regenerating = true"
                                >
                                    <RefreshCw />
                                </InputGroupButton>
                            </TooltipTrigger>

                            <TooltipContent>
                                Regenerate client secret
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </ReadOnlyField>

                <p v-else class="text-muted-foreground text-sm">
                    This is a public client. It has no secret and authenticates
                    with PKCE instead.
                </p>

                <DangerousAction
                    v-model:open="regenerating"
                    title="Generate a new client secret?"
                    description="The current secret stops working immediately. Every integration using it will fail until it is updated."
                    confirm-label="Generate new secret"
                    @confirm="regenerateSecret"
                />
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
                    <li v-for="uri in application.redirect_uris" :key="uri">
                        <ReadOnlyField
                            label="Redirect URI"
                            label-hidden
                            :value="uri"
                            copy-label="Copy URI"
                        />
                    </li>
                </ul>
            </CardContent>
        </Card>

        <Card v-if="application.uses_redirect_uris">
            <CardHeader>
                <CardTitle>Post-logout redirect URIs</CardTitle>
                <CardDescription>
                    Where this application may send the browser after signing
                    out. Matched exactly.
                </CardDescription>
            </CardHeader>

            <CardContent>
                <ul
                    v-if="application.post_logout_redirect_uris.length > 0"
                    class="space-y-2"
                >
                    <li
                        v-for="uri in application.post_logout_redirect_uris"
                        :key="uri"
                    >
                        <ReadOnlyField
                            label="Post-logout redirect URI"
                            label-hidden
                            :value="uri"
                            copy-label="Copy URI"
                        />
                    </li>
                </ul>

                <p v-else class="text-muted-foreground text-sm">
                    None registered. Signing out ends the session here and the
                    user stays on this server.
                </p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Scopes</CardTitle>
                <CardDescription>
                    Anything not listed here is dropped when a token is issued.
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
                    No scopes selected, so this application cannot request any.
                </p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    Who may sign in
                    <Badge
                        :variant="
                            application.restricts_access
                                ? 'default'
                                : 'secondary'
                        "
                    >
                        {{
                            application.restricts_access ? 'Restricted' : 'Open'
                        }}
                    </Badge>
                </CardTitle>
            </CardHeader>

            <CardContent class="text-muted-foreground text-sm">
                <template v-if="application.restricts_access">
                    Only users granted access on the Access page may sign in.
                </template>
                <template v-else>
                    Anyone with an account on this server may sign in.
                </template>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    Consent
                    <Badge
                        :variant="
                            application.skips_authorization
                                ? 'destructive'
                                : 'secondary'
                        "
                    >
                        {{
                            application.skips_authorization
                                ? 'Skipped'
                                : 'Required'
                        }}
                    </Badge>
                </CardTitle>
            </CardHeader>

            <CardContent class="text-muted-foreground text-sm">
                <template v-if="application.skips_authorization">
                    Users are not asked to approve this application. Only
                    appropriate for applications you own.
                </template>
                <template v-else>
                    Users are asked to approve this application the first time
                    it requests access.
                </template>
            </CardContent>
        </Card>

        <Card v-if="canChangeStatus" class="border-destructive/40">
            <CardHeader>
                <CardTitle>Revoke issued tokens</CardTitle>
                <CardDescription>
                    Invalidates every token this application currently holds,
                    without taking it out of service. Its users sign in again
                    the next time it asks.
                </CardDescription>
            </CardHeader>

            <CardContent>
                <DangerousAction
                    title="Revoke every token this application holds?"
                    :description="`${application.name} will lose access immediately and everyone using it will be sent back to sign in. The application itself keeps working.`"
                    confirm-label="Revoke tokens"
                    @confirm="revokeAllTokens"
                >
                    <Button
                        variant="destructive"
                        :aria-label="`Revoke every token held by ${application.name}`"
                    >
                        Revoke tokens
                    </Button>
                </DangerousAction>
            </CardContent>
        </Card>

        <Card v-if="canChangeStatus" class="border-destructive/40">
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
                        Disabling revokes every token this application holds and
                        refuses new ones.
                    </template>
                    <template v-else>
                        The application can request new tokens again. Previously
                        revoked tokens stay revoked.
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
                    <Button variant="destructive"> Disable application </Button>
                </DangerousAction>
            </CardContent>
        </Card>
    </ApplicationLayout>
</template>
