<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import CheckboxFields from '@/components/CheckboxFields.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Badge } from '@/components/ui/badge';
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
import DangerousAction from '@/components/DangerousAction.vue';
import { edit, index, update } from '@/routes/users';
import { formatDateTime } from '@/lib/datetime';
import { index as grants } from '@/routes/applications/grants';
import { destroy as revokeSessions } from '@/routes/users/sessions';
import { update as updateStatus } from '@/routes/users/status';
import type {
    ApplicationAccessSummary,
    UserSummary,
} from '@/types/administration';

const { user, availableRoles } = defineProps<{
    user: UserSummary;
    applicationAccess: ApplicationAccessSummary[];
    availableRoles: string[];
    canManage: boolean;
    canChangeStatus: boolean;
    canRevokeSessions: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Users', href: index() }],
    },
});

const roleOptions = computed(() =>
    availableRoles.map((role) => ({ value: role, label: role })),
);

function setEnabled(enabled: boolean) {
    router.put(updateStatus(user.id).url, { enabled });
}

function signOutEverywhere() {
    router.delete(revokeSessions(user.id).url);
}
</script>

<template>
    <Head :title="user.name" />

    <div class="max-w-2xl px-4 py-6">
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <Heading :title="user.name" :description="user.email" />

            <Badge v-if="user.disabled" variant="destructive">Disabled</Badge>
        </div>

        <div class="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>Activity</CardTitle>
                </CardHeader>

                <CardContent class="grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <div class="text-muted-foreground">Last sign-in</div>
                        <div>
                            {{ formatDateTime(user.last_login_at, 'Never') }}
                        </div>
                    </div>
                    <div>
                        <div class="text-muted-foreground">Created</div>
                        <div>
                            {{ formatDateTime(user.created_at, 'Never') }}
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Application access</CardTitle>
                    <CardDescription>
                        Granted from each application's own page, where the
                        roles it defines are listed.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <ul
                        v-if="applicationAccess.length > 0"
                        class="divide-y rounded-lg border"
                    >
                        <li
                            v-for="access in applicationAccess"
                            :key="access.application_id"
                            class="flex items-center justify-between gap-3 px-3 py-2"
                        >
                            <div class="flex items-center gap-2">
                                <Link
                                    :href="grants(access.application_id)"
                                    class="font-medium hover:underline"
                                >
                                    {{ access.application_name }}
                                </Link>
                                <Badge
                                    v-if="!access.application_enabled"
                                    variant="destructive"
                                >
                                    Disabled
                                </Badge>
                            </div>

                            <span class="text-muted-foreground text-sm">
                                {{ access.role_name ?? 'No role' }}
                            </span>
                        </li>
                    </ul>

                    <p v-else class="text-muted-foreground text-sm">
                        This user has not been granted access to any
                        application.
                    </p>
                </CardContent>
            </Card>

            <Form
                v-bind="update.form(user.id)"
                class="space-y-6"
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
                                :default-value="user.name"
                                :disabled="!canManage"
                                required
                            />
                            <InputError :message="errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="email">Email address</Label>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                :default-value="user.email"
                                :disabled="!canManage"
                                required
                            />
                            <InputError :message="errors.email" />
                        </div>

                        <div class="flex items-start gap-3">
                            <Checkbox
                                id="email_verified"
                                name="email_verified"
                                value="1"
                                :default-value="user.email_verified"
                                :disabled="!canManage"
                            />
                            <Label for="email_verified" class="font-normal">
                                Email address is verified
                            </Label>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Password</CardTitle>
                        <CardDescription>
                            Leave blank to keep the current password.
                        </CardDescription>
                    </CardHeader>

                    <CardContent class="space-y-4">
                        <div class="grid gap-2">
                            <Label for="password">New password</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                autocomplete="new-password"
                                :disabled="!canManage"
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="password_confirmation">
                                Confirm new password
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                autocomplete="new-password"
                                :disabled="!canManage"
                            />
                            <InputError
                                :message="errors.password_confirmation"
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="availableRoles.length > 0">
                    <CardHeader>
                        <CardTitle>Platform roles</CardTitle>
                        <CardDescription>
                            These grant access to this administration console.
                            They are separate from any role inside an
                            application.
                        </CardDescription>
                    </CardHeader>

                    <CardContent>
                        <CheckboxFields
                            name="roles"
                            :options="roleOptions"
                            :selected="user.roles"
                            :errors="errors"
                            error-key="roles"
                            :disabled="!canManage"
                        />
                    </CardContent>
                </Card>

                <Button v-if="canManage" type="submit" :disabled="processing">
                    <Spinner v-if="processing" />
                    Save changes
                </Button>
            </Form>

            <Card v-if="canRevokeSessions" class="border-destructive/40">
                <CardHeader>
                    <CardTitle>Sign out everywhere</CardTitle>
                    <CardDescription>
                        Ends every browser session and revokes every token
                        applications hold for this user. They can sign in again
                        unless the account is also disabled.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <DangerousAction
                        title="Sign this user out everywhere?"
                        :description="`${user.name} will be signed out of this server and every application that holds a token for them. Applications will have to send them back to sign in.`"
                        confirm-label="Sign out everywhere"
                        @confirm="signOutEverywhere"
                    >
                        <Button
                            variant="destructive"
                            :aria-label="`Sign ${user.name} out everywhere`"
                        >
                            Sign out everywhere
                        </Button>
                    </DangerousAction>
                </CardContent>
            </Card>

            <Card v-if="canChangeStatus" class="border-destructive/40">
                <CardHeader>
                    <CardTitle>{{
                        user.disabled ? 'Restore access' : 'Disable access'
                    }}</CardTitle>
                    <CardDescription>
                        <template v-if="user.disabled">
                            The user will be able to sign in again immediately.
                        </template>
                        <template v-else>
                            The user will be signed out of this server and
                            refused at every sign-in. Their account and history
                            are kept. Tokens applications already hold keep
                            working until they expire &mdash; revoke those
                            above.
                        </template>
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <Button
                        v-if="user.disabled"
                        variant="outline"
                        @click="setEnabled(true)"
                    >
                        Enable user
                    </Button>

                    <DangerousAction
                        v-else
                        title="Disable this user?"
                        :description="`${user.name} will be refused at sign-in until re-enabled. Tokens applications already hold are not revoked.`"
                        confirm-label="Disable user"
                        @confirm="setEnabled(false)"
                    >
                        <Button variant="destructive">Disable user</Button>
                    </DangerousAction>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
