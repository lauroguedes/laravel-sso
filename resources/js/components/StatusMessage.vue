<script setup lang="ts">
/**
 * A sentence saying something worked: a link sent, an address confirmed.
 *
 * InputError's counterpart, and shaped the same way — it renders nothing
 * without a message, so a caller can leave it in place unconditionally.
 *
 * The success token rather than a fixed green, so a message here answers to
 * the palette like everything else on the page.
 */
defineProps<{
    message?: string | null;
    /** Left-aligned under a field, rather than centred above a form. */
    align?: 'center' | 'start';
}>();
</script>

<template>
    <p
        v-if="message"
        class="text-success text-sm font-medium"
        :class="align === 'start' ? 'mt-2' : 'mb-4 text-center'"
    >
        {{ message }}
    </p>
</template>
