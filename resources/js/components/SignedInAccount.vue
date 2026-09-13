<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * The account a sign-in flow page acts for, so nobody approves an application
 * or signs out as somebody else by mistake.
 */
const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <div class="rounded-lg border p-4 text-sm">
        <p class="text-muted-foreground">Signed in as</p>
        <p class="font-medium">{{ user.name }}</p>
        <p class="text-muted-foreground">{{ user.email }}</p>
    </div>
</template>
