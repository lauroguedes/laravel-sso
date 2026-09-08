<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
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
        <div
            class="bg-muted relative hidden h-full flex-col p-10 text-white lg:flex dark:border-r"
        >
            <!--
                The panel keeps its dark ground behind any uploaded image, so
                the brand stays legible while a large photograph is still
                loading and if it fails to load at all.
            -->
            <div class="absolute inset-0 bg-zinc-900">
                <img
                    v-if="background"
                    :src="background"
                    alt=""
                    class="size-full object-cover"
                />
            </div>
            <Link
                :href="home()"
                class="relative z-20 flex items-center text-lg font-medium"
            >
                <BrandMark size="sm" class="mr-2" />
                {{ name }}
            </Link>
        </div>
        <div class="lg:p-8">
            <div
                class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]"
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
        </div>
    </div>
</template>
