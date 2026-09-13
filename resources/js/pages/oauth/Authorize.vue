<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import ConsentCard from '@/components/ConsentCard.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import type { ConsentWording } from '@/lib/consent';
import {
    approve as approveUrl,
    deny as denyUrl,
} from '@/routes/passport/authorizations';
import type { ScopeOption } from '@/types/administration';

/**
 * The consent screen an application sends a user to, worded as the Consent tab
 * of App settings says.
 *
 * Approve and deny reach the same endpoint with different verbs, which is what
 * Passport expects, so both go through useForm rather than a native submit.
 */
const { authToken } = defineProps<{
    application: { name: string; description: string | null };
    scopes: ScopeOption[];
    authToken: string;
    consent: ConsentWording;
}>();

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
    <AuthLayout :title="consent.heading" :description="consent.message">
        <Head :title="`Authorize ${application.name}`" />

        <ConsentCard
            :application-name="application.name"
            :scopes="scopes"
            :consent="consent"
            :processing="form.processing"
            @approve="approve"
            @deny="deny"
        />
    </AuthLayout>
</template>
