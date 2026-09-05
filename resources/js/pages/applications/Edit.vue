<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import RedirectUriFields from '@/components/applications/RedirectUriFields.vue';
import CheckboxFields from '@/components/CheckboxFields.vue';
import SwitchField from '@/components/SwitchField.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { index, show, update } from '@/routes/applications';
import type { ApplicationDetail, ScopeOption } from '@/types/administration';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Applications', href: index() }],
    },
});

const { application, availableScopes } = defineProps<{
    application: ApplicationDetail;
    availableScopes: ScopeOption[];
}>();

const scopeOptions = computed(() =>
    availableScopes.map((scope) => ({
        value: scope.id,
        label: scope.id,
        description: scope.description,
    })),
);
</script>

<template>
    <Head :title="`Edit ${application.name}`" />

    <div class="max-w-2xl px-4 py-6">
        <Heading
            :title="`Edit ${application.name}`"
            :description="application.type_label"
        />

        <Form
            v-bind="update.form(application.id)"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <Card>
                <CardHeader>
                    <CardTitle>General</CardTitle>
                </CardHeader>

                <CardContent class="space-y-4">
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            :default-value="application.name"
                            required
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="description">Description</Label>
                        <Textarea
                            id="description"
                            name="description"
                            rows="2"
                            :default-value="application.description ?? ''"
                        />
                        <InputError :message="errors.description" />
                    </div>
                </CardContent>
            </Card>

            <Card v-if="application.uses_redirect_uris">
                <CardHeader>
                    <CardTitle>Redirect URIs</CardTitle>
                    <CardDescription>
                        Where the browser is sent back after the user has
                        authenticated.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <RedirectUriFields
                        :model-value="application.redirect_uris"
                        :errors="errors"
                    />
                </CardContent>
            </Card>

            <Card v-if="application.uses_redirect_uris">
                <CardHeader>
                    <CardTitle>Post-logout redirect URIs</CardTitle>
                    <CardDescription>
                        Where the browser may be sent after signing out. A
                        separate list from the redirect URIs above, which
                        receive authorization codes. Leave empty to end the
                        session without redirecting anywhere.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <RedirectUriFields
                        :model-value="application.post_logout_redirect_uris"
                        :errors="errors"
                        name="post_logout_redirect_uris"
                        label="post-logout URI"
                        placeholder="https://app.example.com/signed-out"
                    />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Scopes</CardTitle>
                    <CardDescription>
                        What this application is allowed to request.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <CheckboxFields
                        name="scopes"
                        :options="scopeOptions"
                        :selected="application.scopes"
                        :errors="errors"
                        error-key="scopes"
                        mono
                    />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Who may sign in</CardTitle>
                </CardHeader>

                <CardContent>
                    <SwitchField
                        name="restricts_access"
                        label="Restrict to users with access"
                        description="Only users granted access on the Access page may sign in. Leave off to admit anyone with an account on this server."
                        :default-value="application.restricts_access"
                        :errors="errors"
                    />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Consent</CardTitle>
                </CardHeader>

                <CardContent>
                    <SwitchField
                        name="skips_authorization"
                        label="Skip the consent screen"
                        description="Users will not be asked to approve this application. Only enable this for applications you own."
                        :default-value="application.skips_authorization"
                        :errors="errors"
                    />
                </CardContent>
            </Card>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" />
                    Save changes
                </Button>

                <Button variant="ghost" as-child>
                    <Link :href="show(application.id)">Cancel</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
