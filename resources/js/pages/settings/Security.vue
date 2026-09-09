<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useTabInUrl } from '@/composables/useTabInUrl';
import { edit } from '@/routes/security';
import type { Props as ManagePasskeysProps } from '@/components/ManagePasskeys.vue';
import ManagePasskeys from '@/components/ManagePasskeys.vue';
import type { Props as ManageTwoFactorProps } from '@/components/ManageTwoFactor.vue';
import ManageTwoFactor from '@/components/ManageTwoFactor.vue';

// oxfmt-ignore
type Props = {
    passwordRules: string;
    /** Which tab to open, so changing a password returns to the one it came from. */
    tab: string;
} & ManagePasskeysProps &
    ManageTwoFactorProps;

const props = defineProps<Props>();

/*
 * Changing a password is a redirect, so the tab has to survive it. The server
 * names the one to open and this keeps the URL in step.
 */
const { rememberTab } = useTabInUrl();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Security settings',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Security settings" />

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Security"
            description="Your password, and the second factors that stand behind it"
        />

        <Tabs
            :default-value="props.tab"
            class="gap-6"
            @update:model-value="rememberTab"
        >
            <TabsList>
                <TabsTrigger value="password">Password</TabsTrigger>
                <TabsTrigger v-if="canManageTwoFactor" value="two-factor">
                    Two-factor
                </TabsTrigger>
                <TabsTrigger v-if="canManagePasskeys" value="passkeys">
                    Passkeys
                </TabsTrigger>
            </TabsList>

            <TabsContent value="password" class="space-y-6">
                <p class="text-muted-foreground text-sm">
                    Use a long, random password, and change it if you have any
                    reason to think somebody else knows it.
                </p>

                <Form
                    v-bind="SecurityController.update.form()"
                    :options="{
                        preserveScroll: true,
                    }"
                    reset-on-success
                    :reset-on-error="[
                        'password',
                        'password_confirmation',
                        'current_password',
                    ]"
                    class="space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="current_password">Current password</Label>
                        <PasswordInput
                            id="current_password"
                            name="current_password"
                            autocomplete="current-password"
                            placeholder="Current password"
                        />
                        <InputError :message="errors.current_password" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password">New password</Label>
                        <PasswordInput
                            id="password"
                            name="password"
                            autocomplete="new-password"
                            placeholder="New password"
                            :passwordrules="props.passwordRules"
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
                            autocomplete="new-password"
                            placeholder="Confirm password"
                            :passwordrules="props.passwordRules"
                        />
                        <InputError :message="errors.password_confirmation" />
                    </div>

                    <div class="flex items-center gap-4">
                        <Button
                            :disabled="processing"
                            data-test="update-password-button"
                        >
                            Save
                        </Button>
                    </div>
                </Form>
            </TabsContent>

            <TabsContent value="two-factor" class="space-y-6">
                <p class="text-muted-foreground text-sm">
                    A code from an app on your phone, asked for after your
                    password.
                </p>

                <ManageTwoFactor
                    :canManageTwoFactor="canManageTwoFactor"
                    :requiresConfirmation="requiresConfirmation"
                    :twoFactorEnabled="twoFactorEnabled"
                />
            </TabsContent>

            <TabsContent value="passkeys" class="space-y-6">
                <p class="text-muted-foreground text-sm">
                    Sign in with your device instead of a password. A passkey
                    cannot be phished or reused anywhere else.
                </p>

                <ManagePasskeys
                    :canManagePasskeys="canManagePasskeys"
                    :passkeys="passkeys"
                />
            </TabsContent>
        </Tabs>
    </div>
</template>
