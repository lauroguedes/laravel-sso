<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SignedInAccount from '@/components/SignedInAccount.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import { confirm } from '@/routes/oidc/logout';

/**
 * Asks before signing out when a logout request cannot show it comes from an
 * application this user signed in to. The request's parameters travel with the
 * confirmation, so the server can still send the browser where it registered.
 */
const { parameters } = defineProps<{
    application: { name: string } | null;
    parameters: Record<string, string>;
}>();

const form = useForm({ ...parameters });

function signOut() {
    form.post(confirm().url);
}
</script>

<template>
    <AuthLayout
        title="Sign out?"
        :description="
            application
                ? `${application.name} is asking to sign you out.`
                : 'A link is asking to sign you out.'
        "
    >
        <Head title="Sign out" />

        <div class="flex flex-col gap-6">
            <SignedInAccount />

            <p class="text-muted-foreground text-sm">
                Signing out ends your session on this server. Applications you
                use keep their own sessions until you sign out of them.
            </p>

            <div class="flex flex-col gap-2 sm:flex-row-reverse">
                <Button
                    class="sm:flex-1"
                    :disabled="form.processing"
                    data-test="sign-out-button"
                    @click="signOut"
                >
                    <Spinner v-if="form.processing" />
                    Sign out
                </Button>

                <Button variant="outline" class="sm:flex-1" as-child>
                    <Link :href="dashboard()">Stay signed in</Link>
                </Button>
            </div>
        </div>
    </AuthLayout>
</template>
