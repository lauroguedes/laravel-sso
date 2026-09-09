<script setup lang="ts">
import AppCredit from '@/components/AppCredit.vue';
import { SidebarInset } from '@/components/ui/sidebar';

defineProps<{ class?: string }>();
</script>

<template>
    <SidebarInset :class="$props.class">
        <!--
            The page takes the height it needs and the credit follows it, so a
            short page leaves it at the foot of the window and a long one puts
            it below the content rather than over it.
        -->
        <div class="flex min-h-0 flex-1 flex-col">
            <div class="flex-1">
                <slot />
            </div>

            <AppCredit />
        </div>
    </SidebarInset>
</template>
