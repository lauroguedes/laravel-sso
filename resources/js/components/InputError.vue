<script setup lang="ts">
/**
 * What is wrong with a field.
 *
 * The destructive token rather than a fixed red: an administrator can change
 * the palette, and a message that stayed the shipped red would be the one
 * thing on the page ignoring their choice.
 */
defineProps<{
    message?: string;
}>();
</script>

<template>
    <div v-show="message">
        <p class="text-destructive text-sm">
            {{ message }}
        </p>
    </div>
</template>
