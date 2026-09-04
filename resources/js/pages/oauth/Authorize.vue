<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import {
    approve as approveUrl,
    deny as denyUrl,
} from '@/routes/passport/authorizations';
import type { ScopeOption } from '@/types/administration';

/**
 * The consent screen an application sends a user to.
 *
 * Approve and deny reach the same endpoint with different verbs, which is what
 * Passport expects, so both go through useForm rather than a native submit.
 */
const { authToken } = defineProps<{
    application: { name: string; description: string | null };
    scopes: ScopeOption[];
    authToken: string;
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);

/*
 * The state a client sent travels with the serialized authorization request in
 * the session, so it is not carried through the form.
 */
const form = useForm({ auth_token: authToken });

function approve() {
    form.post(approveUrl().url, { preserveScroll: true });
}

function deny() {
    form.delete(denyUrl().url, { preserveScroll: true });
}
</script>

<template>
    <AuthLayout
        :title="`Continue to ${application.name}`"
        :description="
            application.description ??
            'This application is asking to use your account.'
        "
    >
        <Head :title="`Authorize ${application.name}`" />

        <div class="flex flex-col gap-6">
            <div class="rounded-lg border p-4 text-sm">
                <p class="text-muted-foreground">Signed in as</p>
                <p class="font-medium">{{ user.name }}</p>
                <p class="text-muted-foreground">{{ user.email }}</p>
            </div>

            <div v-if="scopes.length > 0" class="grid gap-2">
                <p class="text-sm font-medium">
                    {{ application.name }} will be able to:
                </p>

                <ul class="grid gap-2">
                    <li
                        v-for="scope in scopes"
                        :key="scope.id"
                        class="flex items-start gap-2 text-sm"
                    >
                        <span
                            class="bg-muted-foreground mt-1.5 size-1.5 shrink-0 rounded-full"
                            aria-hidden="true"
                        />
                        <span>{{ scope.description }}</span>
                    </li>
                </ul>
            </div>

            <p class="text-muted-foreground text-sm">
                You can withdraw this at any time by revoking the application's
                access.
            </p>

            <div class="flex flex-col gap-2 sm:flex-row-reverse">
                <Button
                    class="sm:flex-1"
                    :disabled="form.processing"
                    data-test="approve-button"
                    @click="approve"
                >
                    <Spinner v-if="form.processing" />
                    Allow
                </Button>

                <Button
                    variant="outline"
                    class="sm:flex-1"
                    :disabled="form.processing"
                    data-test="deny-button"
                    @click="deny"
                >
                    Cancel
                </Button>
            </div>
        </div>
    </AuthLayout>
</template>
