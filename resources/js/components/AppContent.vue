<script setup lang="ts">
import { SidebarInset } from '@/components/ui/sidebar';

defineProps<{ class?: string }>();
</script>

<template>
    <SidebarInset :class="$props.class">
        <slot />
    </SidebarInset>
</template>
