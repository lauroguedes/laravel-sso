<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppCredit from '@/components/AppCredit.vue';
import BrandMark from '@/components/BrandMark.vue';
import { home } from '@/routes';

const page = usePage();
const name = page.props.branding.name;
const background = computed(() => page.props.branding.authBackground);

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0"
    >
        <!--
            An uploaded photograph brings its own colours, so the text over one
            is white rather than the panel's foreground. Chosen rather than
            layered: two colour utilities of equal specificity are settled by
            the order Tailwind emits them, not the order they are written.
        -->
        <div
            class="bg-auth-panel relative hidden h-full flex-col p-10 lg:flex dark:border-r"
            :class="background ? 'text-white' : 'text-auth-panel-foreground'"
        >
            <div class="absolute inset-0">
                <template v-if="background">
                    <img
                        :src="background"
                        alt=""
                        class="size-full object-cover"
                    />

                    <!--
                        A photograph is somebody else's choice of colours, so
                        the brand on top of it needs a ground of its own rather
                        than trusting the image to be dark where the words
                        fall.
                    -->
                    <div class="absolute inset-0 bg-black/45" />
                </template>

                <div
                    v-else
                    class="flex size-full items-center justify-center"
                    aria-hidden="true"
                >
                    <!--
                        The server's own mark, large and faint, so it never
                        competes with the brand name on top of it.
                    -->
                    <BrandMark
                        variant="bare"
                        class="h-auto w-1/2 max-w-sm opacity-10"
                    />
                </div>
            </div>
            <Link
                :href="home()"
                class="relative z-20 flex items-center text-lg font-medium"
            >
                <BrandMark size="sm" class="mr-2" />
                {{ name }}
            </Link>
        </div>
        <!--
            The credit belongs under the form rather than under the window: it
            would otherwise sit centred across both halves, which reads as
            belonging to neither.
        -->
        <div class="flex h-full flex-col justify-center lg:p-8">
            <div
                class="mx-auto flex w-full flex-1 flex-col justify-center space-y-6 sm:w-[350px]"
            >
                <div class="flex flex-col space-y-2 text-center">
                    <h1 class="text-xl font-medium tracking-tight" v-if="title">
                        {{ title }}
                    </h1>
                    <p class="text-muted-foreground text-sm" v-if="description">
                        {{ description }}
                    </p>
                </div>
                <slot />
            </div>

            <AppCredit />
        </div>
    </div>
</template>
