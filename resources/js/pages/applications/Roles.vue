<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Plus, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import ApplicationLayout from '@/layouts/applications/Layout.vue';
import CheckboxFields from '@/components/CheckboxFields.vue';
import DangerousAction from '@/components/DangerousAction.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
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
import { index } from '@/routes/applications';
import {
    destroy as destroyPermission,
    store as storePermission,
} from '@/routes/applications/permissions';
import {
    destroy as destroyRole,
    store as storeRole,
    update as updateRole,
} from '@/routes/applications/roles';
import type {
    ApplicationHeader,
    ApplicationSection,
    ApplicationPermissionSummary,
    ApplicationRoleSummary,
} from '@/types/administration';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Applications', href: index() }],
    },
});

const { application, roles, permissions } = defineProps<{
    application: ApplicationHeader;
    sections: ApplicationSection[];
    canManageApplication: boolean;
    roles: ApplicationRoleSummary[];
    permissions: ApplicationPermissionSummary[];
    canManage: boolean;
}>();

const permissionOptions = computed(() =>
    permissions.map((permission) => ({
        value: String(permission.id),
        label: permission.name,
        description: permission.description,
    })),
);

/** The role currently open for editing, if any. */
const editing = ref<number | null>(null);

function removeRole(role: ApplicationRoleSummary) {
    router.delete(destroyRole([application.id, role.id]).url);
}

function removePermission(permission: ApplicationPermissionSummary) {
    router.delete(destroyPermission([application.id, permission.id]).url);
}
</script>

<template>
    <Head :title="`${application.name} roles`" />

    <ApplicationLayout
        :application="application"
        :sections="sections"
        description="Roles and permissions this application recognises. They are reported to the application in a token; the application enforces them."
        :can-manage="canManageApplication"
    >
        <Card>
            <CardHeader>
                <CardTitle>Permissions</CardTitle>
                <CardDescription>
                    The capabilities this application recognises, such as
                    <code class="font-mono">reports.view</code>.
                </CardDescription>
            </CardHeader>

            <CardContent class="space-y-4">
                <ul
                    v-if="permissions.length > 0"
                    class="divide-y rounded-lg border"
                >
                    <li
                        v-for="permission in permissions"
                        :key="permission.id"
                        class="flex items-center justify-between gap-3 px-3 py-2"
                    >
                        <div class="min-w-0">
                            <code class="font-mono text-sm">
                                {{ permission.name }}
                            </code>
                            <p
                                v-if="permission.description"
                                class="text-muted-foreground truncate text-sm"
                            >
                                {{ permission.description }}
                            </p>
                        </div>

                        <DangerousAction
                            v-if="canManage"
                            title="Remove this permission?"
                            :description="`${permission.name} will be removed from every role that grants it, and will stop appearing in tokens.`"
                            confirm-label="Remove permission"
                            @confirm="removePermission(permission)"
                        >
                            <Button
                                variant="ghost"
                                size="icon"
                                :aria-label="`Remove ${permission.name}`"
                            >
                                <X class="size-4" />
                            </Button>
                        </DangerousAction>
                    </li>
                </ul>

                <p v-else class="text-muted-foreground text-sm">
                    No permissions defined yet.
                </p>

                <Form
                    v-if="canManage"
                    v-bind="storePermission.form(application.id)"
                    reset-on-success
                    class="flex flex-wrap items-start gap-2"
                    v-slot="{ errors, processing }"
                >
                    <div class="min-w-48 flex-1">
                        <Label for="permission-name" class="sr-only">
                            Permission name
                        </Label>
                        <Input
                            id="permission-name"
                            name="name"
                            placeholder="reports.view"
                            spellcheck="false"
                            required
                        />
                        <InputError class="mt-1" :message="errors.name" />
                    </div>

                    <div class="min-w-48 flex-1">
                        <Label for="permission-description" class="sr-only">
                            Description
                        </Label>
                        <Input
                            id="permission-description"
                            name="description"
                            placeholder="What it allows (optional)"
                        />
                        <InputError
                            class="mt-1"
                            :message="errors.description"
                        />
                    </div>

                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        <Plus v-else class="size-4" />
                        Add
                    </Button>
                </Form>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Roles</CardTitle>
                <CardDescription>
                    A user holds at most one role in this application.
                </CardDescription>
            </CardHeader>

            <CardContent class="space-y-4">
                <div
                    v-for="role in roles"
                    :key="role.id"
                    class="rounded-lg border p-3"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-medium">{{ role.name }}</span>
                                <Badge variant="secondary">
                                    {{ role.users_count }}
                                    {{
                                        role.users_count === 1
                                            ? 'user'
                                            : 'users'
                                    }}
                                </Badge>
                            </div>
                            <p
                                v-if="role.description"
                                class="text-muted-foreground text-sm"
                            >
                                {{ role.description }}
                            </p>
                        </div>

                        <div v-if="canManage" class="flex items-center gap-1">
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="
                                    editing =
                                        editing === role.id ? null : role.id
                                "
                            >
                                {{ editing === role.id ? 'Close' : 'Edit' }}
                            </Button>

                            <DangerousAction
                                title="Remove this role?"
                                :description="`Users who hold ${role.name} keep their access to ${application.name} but lose the role and its permissions.`"
                                confirm-label="Remove role"
                                @confirm="removeRole(role)"
                            >
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    :aria-label="`Remove ${role.name}`"
                                >
                                    <X class="size-4" />
                                </Button>
                            </DangerousAction>
                        </div>
                    </div>

                    <div
                        v-if="editing !== role.id"
                        class="mt-2 flex flex-wrap gap-1"
                    >
                        <Badge
                            v-for="permission in role.permissions"
                            :key="permission.id"
                            variant="outline"
                            class="font-mono"
                        >
                            {{ permission.name }}
                        </Badge>
                        <span
                            v-if="role.permissions.length === 0"
                            class="text-muted-foreground text-sm"
                        >
                            Grants no permissions.
                        </span>
                    </div>

                    <Form
                        v-if="editing === role.id"
                        v-bind="updateRole.form([application.id, role.id])"
                        class="mt-4 space-y-4"
                        v-slot="{ errors, processing }"
                        @success="editing = null"
                    >
                        <div class="grid gap-2">
                            <Label :for="`role-name-${role.id}`">Name</Label>
                            <Input
                                :id="`role-name-${role.id}`"
                                name="name"
                                :default-value="role.name"
                                required
                            />
                            <InputError :message="errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label :for="`role-description-${role.id}`">
                                Description
                            </Label>
                            <Input
                                :id="`role-description-${role.id}`"
                                name="description"
                                :default-value="role.description ?? ''"
                            />
                            <InputError :message="errors.description" />
                        </div>

                        <fieldset class="grid gap-2">
                            <legend class="mb-2 text-sm font-medium">
                                Permissions
                            </legend>

                            <CheckboxFields
                                :name="`permissions`"
                                :options="permissionOptions"
                                :selected="
                                    role.permissions.map((p) => String(p.id))
                                "
                                :errors="errors"
                                error-key="permissions"
                                mono
                                empty-message="Add a permission above first."
                            />
                        </fieldset>

                        <Button type="submit" size="sm" :disabled="processing">
                            <Spinner v-if="processing" />
                            Save role
                        </Button>
                    </Form>
                </div>

                <p
                    v-if="roles.length === 0"
                    class="text-muted-foreground text-sm"
                >
                    No roles defined yet.
                </p>

                <Form
                    v-if="canManage"
                    v-bind="storeRole.form(application.id)"
                    reset-on-success
                    class="flex flex-wrap items-start gap-2 border-t pt-4"
                    v-slot="{ errors, processing }"
                >
                    <div class="min-w-48 flex-1">
                        <Label for="role-name" class="sr-only">Role name</Label>
                        <Input
                            id="role-name"
                            name="name"
                            placeholder="Analyst"
                            required
                        />
                        <InputError class="mt-1" :message="errors.name" />
                    </div>

                    <div class="min-w-48 flex-1">
                        <Label for="role-description" class="sr-only">
                            Description
                        </Label>
                        <Input
                            id="role-description"
                            name="description"
                            placeholder="What it is for (optional)"
                        />
                        <InputError
                            class="mt-1"
                            :message="errors.description"
                        />
                    </div>

                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        <Plus v-else class="size-4" />
                        Add role
                    </Button>
                </Form>
            </CardContent>
        </Card>
    </ApplicationLayout>
</template>
