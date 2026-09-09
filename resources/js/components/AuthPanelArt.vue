<script setup lang="ts">
/**
 * The pattern behind the sign-in panel when nobody has uploaded a picture.
 *
 * Drawn rather than photographed: it is the same shape single sign-on is — one
 * identity in the middle, several applications around it, each reaching the
 * centre rather than each other — and being a drawing it takes the palette's
 * colours instead of bringing its own. An administrator who uploads an image
 * replaces it.
 *
 * currentColor throughout and faint on purpose. It sits under the brand, and
 * anything with more contrast would compete with the words on top of it.
 */
</script>

<template>
    <svg
        class="size-full"
        viewBox="0 0 600 800"
        preserveAspectRatio="xMidYMid slice"
        aria-hidden="true"
        fill="none"
        stroke="currentColor"
    >
        <g opacity="0.16">
            <!-- The orbits an application sits on. -->
            <circle cx="300" cy="400" r="80" stroke-width="1.25" />
            <circle cx="300" cy="400" r="150" stroke-width="1" />
            <circle cx="300" cy="400" r="220" stroke-width="0.75" />
            <circle cx="300" cy="400" r="290" stroke-width="0.5" />

            <!-- Each reaches the centre, and none reaches another. -->
            <g stroke-width="0.75">
                <path d="M300 400 300 110" />
                <path d="M300 400 551 545" />
                <path d="M300 400 49 545" />
                <path d="M300 400 449 141" />
                <path d="M300 400 151 659" />
            </g>

            <g fill="currentColor" stroke="none">
                <circle cx="300" cy="110" r="5" />
                <circle cx="551" cy="545" r="5" />
                <circle cx="49" cy="545" r="5" />
                <circle cx="449" cy="141" r="3.5" />
                <circle cx="151" cy="659" r="3.5" />
                <circle cx="450" cy="400" r="3.5" />
                <circle cx="150" cy="400" r="3.5" />
            </g>

            <!-- The identity everything else is asking about. -->
            <circle cx="300" cy="400" r="26" stroke-width="1.5" />
            <circle cx="300" cy="400" r="9" fill="currentColor" stroke="none" />
        </g>
    </svg>
</template>
