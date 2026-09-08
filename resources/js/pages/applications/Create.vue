<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
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
import {
    Stepper,
    StepperDescription,
    StepperIndicator,
    StepperItem,
    StepperSeparator,
    StepperTitle,
    StepperTrigger,
} from '@/components/ui/stepper';
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
            { title: 'Add application', href: create() },
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

/*
 * A machine-to-machine client has no browser to send anywhere, so the URI
 * step does not exist for it rather than standing empty. The step list is
 * therefore derived from the chosen type, not fixed.
 */
const steps = computed(() =>
    [
        {
            id: 'details',
            title: 'Details',
            description: 'Name and purpose',
            fields: ['name', 'description'],
        },
        {
            id: 'type',
            title: 'Type',
            description: 'How it authenticates',
            fields: ['type'],
        },
        currentType.value?.uses_redirect_uris
            ? {
                  id: 'uris',
                  title: 'URIs',
                  description: 'Where users are sent back',
                  fields: ['redirect_uris', 'post_logout_redirect_uris'],
              }
            : null,
        {
            id: 'scopes',
            title: 'Scopes',
            description: 'What it may request',
            fields: ['scopes'],
        },
    ].filter((step) => step !== null),
);

/** One-based, as the stepper counts. */
const step = ref(1);

const isLastStep = computed(() => step.value >= steps.value.length);

const currentStepId = computed(() => steps.value[step.value - 1]?.id);

/*
 * Dropping the URI step while standing on it, or past it, would otherwise
 * leave the form showing nothing.
 */
watch(steps, (available) => {
    if (step.value > available.length) {
        step.value = available.length;
    }
});

/**
 * Send the reader to the first step holding something the server rejected.
 *
 * Validation happens on submit, by which point the offending field is usually
 * on a step nobody is looking at. Without this the form simply refuses to
 * submit with no visible reason.
 */
function revealFirstError(errors: Record<string, string>) {
    /* "redirect_uris.0" belongs to the step that owns "redirect_uris". */
    const failed = new Set(Object.keys(errors).map((key) => key.split('.')[0]));

    if (failed.size === 0) {
        return;
    }

    const position = steps.value.findIndex((candidate) =>
        candidate.fields.some((field) => failed.has(field)),
    );

    if (position !== -1) {
        step.value = position + 1;
    }
}
</script>

<template>
    <Head title="Add application" />

    <div class="mx-auto w-full max-w-3xl px-4 py-8">
        <Heading
            title="Add application"
            description="Issue OAuth2 credentials so an application can authenticate users through this server"
        />

        <Form
            v-bind="store.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
            @error="revealFirstError"
        >
            <Stepper v-model="step" class="w-full items-start">
                <StepperItem
                    v-for="(item, position) in steps"
                    :key="item.id"
                    :step="position + 1"
                    class="relative flex w-full flex-col items-center justify-center"
                >
                    <StepperSeparator
                        v-if="position !== steps.length - 1"
                        class="bg-muted group-data-[state=completed]:bg-primary absolute top-4 right-[calc(-50%+1rem)] left-[calc(50%+1rem)] block h-0.5 shrink-0 rounded-full"
                    />

                    <StepperTrigger as-child>
                        <Button
                            :variant="
                                step >= position + 1 ? 'default' : 'outline'
                            "
                            size="icon"
                            class="z-10 size-8 shrink-0 rounded-full"
                            type="button"
                        >
                            <Check v-if="step > position + 1" class="size-4" />
                            <span v-else>{{ position + 1 }}</span>
                        </Button>
                    </StepperTrigger>

                    <div class="mt-2 text-center">
                        <StepperTitle class="text-sm font-medium">
                            {{ item.title }}
                        </StepperTitle>
                        <StepperDescription
                            class="text-muted-foreground hidden text-xs sm:block"
                        >
                            {{ item.description }}
                        </StepperDescription>
                    </div>
                </StepperItem>
            </Stepper>

            <!--
                Every step stays mounted and is only hidden, so one submit
                carries the whole form and a field the reader has moved past
                still holds what they typed.
            -->
            <Card v-show="currentStepId === 'details'">
                <CardHeader>
                    <CardTitle>Details</CardTitle>
                    <CardDescription>
                        The name is shown to users on the consent screen, so
                        name it the way they would recognise it.
                    </CardDescription>
                </CardHeader>

                <CardContent class="space-y-4">
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <!--
                            Deliberately not "required": a step the reader has
                            moved past is hidden, and the browser refuses to
                            submit a form with an invalid control it cannot
                            focus, failing silently. The server validates the
                            name, and @error walks back to this step.
                        -->
                        <Input
                            id="name"
                            name="name"
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

            <Card v-show="currentStepId === 'type'">
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

            <!--
                v-if, not v-show, and the exception to the rule above: these
                inputs are unmounted so a machine-to-machine submit carries no
                redirect_uris at all, rather than a list of empty strings.
            -->
            <template v-if="currentType?.uses_redirect_uris">
                <Card v-show="currentStepId === 'uris'">
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

                <Card v-show="currentStepId === 'uris'">
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
                            :model-value="[]"
                            :errors="errors"
                            name="post_logout_redirect_uris"
                            label="post-logout URI"
                            placeholder="https://app.example.com/signed-out"
                        />
                    </CardContent>
                </Card>
            </template>

            <Card v-show="currentStepId === 'scopes'">
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

            <div class="flex items-center justify-between gap-3">
                <Button
                    variant="ghost"
                    type="button"
                    :disabled="step === 1"
                    @click="step -= 1"
                >
                    Back
                </Button>

                <div class="flex items-center gap-3">
                    <Button variant="ghost" as-child>
                        <Link :href="index()">Cancel</Link>
                    </Button>

                    <Button v-if="!isLastStep" type="button" @click="step += 1">
                        Next
                    </Button>

                    <Button v-else type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        Add application
                    </Button>
                </div>
            </div>
        </Form>
    </div>
</template>
