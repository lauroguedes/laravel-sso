<script setup lang="ts">
type Props = {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
};

withDefaults(defineProps<Props>(), {
    variant: 'default',
});
</script>

<template>
    <header :class="variant === 'small' ? '' : 'mb-8 space-y-0.5'">
        <!--
            The suffix sits with the title rather than beside the header, so a
            long description cannot push it across the page.
        -->
        <div class="flex items-center gap-2">
            <h2
                :class="
                    variant === 'small'
                        ? 'mb-0.5 text-base font-medium'
                        : 'text-xl font-semibold tracking-tight'
                "
            >
                {{ title }}
            </h2>

            <slot name="title-suffix" />
        </div>
        <p v-if="description" class="text-muted-foreground text-sm">
            {{ description }}
        </p>
    </header>
</template>
