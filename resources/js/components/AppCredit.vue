<script setup lang="ts">
/**
 * Who made this.
 *
 * Placed by each layout rather than by the page shell, because where it
 * belongs depends on the shape of the page: centred under a single column,
 * but under the form rather than the whole window when the sign-in page is
 * split in two.
 *
 * In the flow rather than fixed, so that a page taller than the window pushes
 * it below the content instead of laying it over the last line.
 */
</script>

<template>
    <p class="text-muted-foreground/70 py-3 text-center text-[11px]">
        Crafted by an Artisan &hearts;
        <a
            href="https://lauroguedes.dev"
            target="_blank"
            rel="noopener noreferrer"
            class="hover:text-foreground underline-offset-2 transition-colors hover:underline"
            >Lauro Guedes</a
        >
    </p>
</template>
