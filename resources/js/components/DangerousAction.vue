<script setup lang="ts">
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';

/**
 * Wraps a destructive control in an explicit confirmation.
 *
 * Actions that revoke access or invalidate credentials are not undoable from
 * the interface, so they always state what will happen before proceeding.
 */
const {
    title,
    description,
    confirmLabel = 'Confirm',
    cancelLabel = 'Cancel',
} = defineProps<{
    title: string;
    description: string;
    confirmLabel?: string;
    cancelLabel?: string;
}>();

const emit = defineEmits<{ confirm: [] }>();
</script>

<template>
    <AlertDialog>
        <AlertDialogTrigger as-child>
            <slot />
        </AlertDialogTrigger>

        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ title }}</AlertDialogTitle>
                <AlertDialogDescription>
                    {{ description }}
                </AlertDialogDescription>
            </AlertDialogHeader>

            <AlertDialogFooter>
                <AlertDialogCancel>{{ cancelLabel }}</AlertDialogCancel>
                <AlertDialogAction
                    class="bg-destructive text-white hover:bg-destructive/90"
                    @click="emit('confirm')"
                >
                    {{ confirmLabel }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
