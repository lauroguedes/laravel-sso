<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

/**
 * Shown when a user signs in successfully but has not been granted access to
 * the application that sent them here.
 */
defineProps<{
    application: { name: string };
}>();
</script>

<template>
    <AuthLayout
        title="You do not have access"
        :description="`Your account is not permitted to use ${application.name}.`"
    >
        <Head title="Access denied" />

        <div class="flex flex-col gap-6">
            <p class="text-muted-foreground text-sm">
                Ask an administrator to grant your account access to
                {{ application.name }}, then try again.
            </p>

            <Button as-child>
                <Link :href="dashboard()">Back to dashboard</Link>
            </Button>
        </div>
    </AuthLayout>
</template>
