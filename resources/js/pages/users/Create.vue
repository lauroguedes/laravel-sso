<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import CheckboxFields from '@/components/CheckboxFields.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { create, index, store } from '@/routes/users';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Users', href: index() },
            { title: 'Add user', href: create() },
        ],
    },
});

const { availableRoles } = defineProps<{
    availableRoles: string[];
}>();

const roleOptions = computed(() =>
    availableRoles.map((role) => ({ value: role, label: role })),
);
</script>

<template>
    <Head title="Add user" />

    <div class="mx-auto w-full max-w-5xl px-4 py-6">
        <Heading
            title="Add user"
            description="Create an account that can authenticate through this Identity Provider"
        />

        <Form
            v-bind="store.form()"
            class="grid items-start gap-6 lg:grid-cols-2"
            v-slot="{ errors, processing }"
        >
            <Card>
                <CardHeader>
                    <CardTitle>Details</CardTitle>
                </CardHeader>

                <CardContent class="space-y-4">
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            required
                            autocomplete="off"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Email address</Label>
                        <Input
                            id="email"
                            name="email"
                            type="email"
                            required
                            autocomplete="off"
                        />
                        <InputError :message="errors.email" />
                    </div>

                    <div class="flex items-start gap-3">
                        <Checkbox
                            id="email_verified"
                            name="email_verified"
                            value="1"
                        />
                        <div class="grid gap-1">
                            <Label for="email_verified" class="font-normal">
                                Mark this address as already verified
                            </Label>
                            <p class="text-muted-foreground text-sm">
                                Leave unchecked to require the user to confirm
                                their address before signing in.
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Password</CardTitle>
                    <CardDescription>
                        Share this with the user over a trusted channel. They
                        can change it once signed in.
                    </CardDescription>
                </CardHeader>

                <CardContent class="space-y-4">
                    <div class="grid gap-2">
                        <Label for="password">Password</Label>
                        <PasswordInput
                            id="password"
                            name="password"
                            required
                            autocomplete="new-password"
                        />
                        <InputError :message="errors.password" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password_confirmation"
                            >Confirm password</Label
                        >
                        <PasswordInput
                            id="password_confirmation"
                            name="password_confirmation"
                            required
                            autocomplete="new-password"
                        />
                        <InputError :message="errors.password_confirmation" />
                    </div>
                </CardContent>
            </Card>

            <Card v-if="availableRoles.length > 0" class="lg:col-span-2">
                <CardHeader>
                    <CardTitle>Platform roles</CardTitle>
                    <CardDescription>
                        These grant access to this administration console. They
                        are separate from any role inside an application.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <CheckboxFields
                        name="roles"
                        :options="roleOptions"
                        :selected="[]"
                        :errors="errors"
                        error-key="roles"
                    />
                </CardContent>
            </Card>

            <div class="flex items-center gap-3 lg:col-span-2">
                <Button type="submit" :disabled="processing">
                    <Spinner v-if="processing" />
                    Create user
                </Button>

                <Button variant="ghost" as-child>
                    <Link :href="index()">Cancel</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
