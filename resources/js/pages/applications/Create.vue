<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import RedirectUriFields from '@/components/applications/RedirectUriFields.vue';
import CheckboxFields from '@/components/CheckboxFields.vue';
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
import { create, index, store } from '@/routes/applications';
import type {
    ApplicationTypeOption,
    ScopeOption,
} from '@/types/administration';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Applications', href: index() },
            { title: 'Register application', href: create() },
        ],
    },
});

const { applicationTypes, availableScopes } = defineProps<{
    applicationTypes: ApplicationTypeOption[];
    availableScopes: ScopeOption[];
}>();

const selectedType = ref(applicationTypes[0]?.value ?? 'confidential');

const currentType = computed(() =>
    applicationTypes.find((type) => type.value === selectedType.value),
);

const defaultScopes = computed(() => currentType.value?.default_scopes ?? []);

const scopeOptions = computed(() =>
    availableScopes.map((scope) => ({
        value: scope.id,
        label: scope.id,
        description: scope.description,
    })),
);
</script>

<template>
    <Head title="Register application" />

    <div class="max-w-2xl px-4 py-6">
        <Heading
            title="Register application"
            description="Issue OAuth2 credentials so an application can authenticate users through this server"
        />

        <Form
            v-bind="store.form()"
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
                            required
                            placeholder="Customer Portal"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="description">Description</Label>
                        <Textarea
                            id="description"
                            name="description"
                            rows="2"
                            placeholder="What this application is for"
                        />
                        <InputError :message="errors.description" />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Application type</CardTitle>
                    <CardDescription>
                        This fixes the grant types and whether a client secret
                        is issued. It cannot be changed later.
                    </CardDescription>
                </CardHeader>

                <CardContent class="space-y-3">
                    <label
                        v-for="type in applicationTypes"
                        :key="type.value"
                        class="hover:bg-muted/50 flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-colors"
                        :class="{
                            'border-primary bg-muted/50':
                                selectedType === type.value,
                        }"
                    >
                        <input
                            v-model="selectedType"
                            type="radio"
                            name="type"
                            :value="type.value"
                            class="accent-primary mt-1"
                        />
                        <div class="grid gap-0.5">
                            <span class="text-sm font-medium">
                                {{ type.label }}
                            </span>
                            <span class="text-muted-foreground text-sm">
                                {{ type.description }}
                            </span>
                        </div>
                    </label>

                    <InputError :message="errors.type" />
                </CardContent>
            </Card>

            <Card v-if="currentType?.uses_redirect_uris">
                <CardHeader>
                    <CardTitle>Redirect URIs</CardTitle>
                    <CardDescription>
                        Where the browser is sent back after the user has
                        authenticated.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <RedirectUriFields :model-value="[]" :errors="errors" />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Scopes</CardTitle>
                    <CardDescription>
                        What this application is allowed to request. Anything
                        not selected is dropped when a token is issued.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <CheckboxFields
                        :key="selectedType"
                        name="scopes"
                        :options="scopeOptions"
                        :selected="defaultScopes"
                        :errors="errors"
                        error-key="scopes"
                        mono
                    />
                </CardContent>
            </Card>

            <div class="flex items-center gap-3">
                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" />
                    Register application
                </Button>

                <Button variant="ghost" as-child>
                    <Link :href="index()">Cancel</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
