<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import RedirectUriFields from '@/components/applications/RedirectUriFields.vue';
import ScopeFields from '@/components/applications/ScopeFields.vue';
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
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { index, show, update } from '@/routes/applications';
import type {
    ApplicationDetail,
    ScopeOption,
} from '@/types/administration';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Applications', href: index() }],
    },
});

const { application } = defineProps<{
    application: ApplicationDetail;
    availableScopes: ScopeOption[];
}>();
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

            <Card v-if="application.type !== 'machine'">
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

            <Card>
                <CardHeader>
                    <CardTitle>Scopes</CardTitle>
                    <CardDescription>
                        What this application is allowed to request.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <ScopeFields
                        :scopes="availableScopes"
                        :selected="application.scopes"
                        :errors="errors"
                    />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Consent</CardTitle>
                </CardHeader>

                <CardContent>
                    <div class="flex items-start justify-between gap-4">
                        <div class="grid gap-1">
                            <Label for="skips_authorization">
                                Skip the consent screen
                            </Label>
                            <p class="text-muted-foreground text-sm">
                                Users will not be asked to approve this
                                application. Only enable this for applications
                                you own.
                            </p>
                        </div>

                        <Switch
                            id="skips_authorization"
                            name="skips_authorization"
                            value="1"
                            :default-value="application.skips_authorization"
                        />
                    </div>

                    <InputError
                        class="mt-2"
                        :message="errors.skips_authorization"
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
