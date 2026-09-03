<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
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
import { update as updateStatus } from '@/routes/users/status';
import type { UserDetail } from '@/types/administration';

const { user } = defineProps<{
    user: UserDetail;
    availableRoles: string[];
    canManage: boolean;
    canChangeStatus: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Users', href: index() }],
    },
});

function setEnabled(enabled: boolean) {
    router.put(updateStatus(user.id).url, { enabled });
}

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString() : 'Never';
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
                        <div>{{ formatDate(user.last_login_at) }}</div>
                    </div>
                    <div>
                        <div class="text-muted-foreground">Created</div>
                        <div>{{ formatDate(user.created_at) }}</div>
                    </div>
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
                                :value="1"
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
                            <InputError :message="errors.password_confirmation" />
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

                    <CardContent class="space-y-3">
                        <div
                            v-for="role in availableRoles"
                            :key="role"
                            class="flex items-center gap-3"
                        >
                            <Checkbox
                                :id="`role-${role}`"
                                name="roles[]"
                                :value="role"
                                :default-value="user.roles.includes(role)"
                                :disabled="!canManage"
                            />
                            <Label :for="`role-${role}`" class="font-normal">
                                {{ role }}
                            </Label>
                        </div>
                        <InputError :message="errors.roles" />
                    </CardContent>
                </Card>

                <Button v-if="canManage" type="submit" :disabled="processing">
                    <Spinner v-if="processing" />
                    Save changes
                </Button>
            </Form>

            <Card v-if="canChangeStatus" class="border-destructive/40">
                <CardHeader>
                    <CardTitle>{{ user.disabled ? 'Restore access' : 'Disable access' }}</CardTitle>
                    <CardDescription>
                        <template v-if="user.disabled">
                            The user will be able to sign in again immediately.
                        </template>
                        <template v-else>
                            The user will be signed out and refused at every
                            sign-in. Their account and history are kept.
                        </template>
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <Button v-if="user.disabled" variant="outline" @click="setEnabled(true)">
                        Enable user
                    </Button>

                    <DangerousAction
                        v-else
                        title="Disable this user?"
                        :description="`${user.name} will be signed out of every session and refused at sign-in until re-enabled.`"
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
