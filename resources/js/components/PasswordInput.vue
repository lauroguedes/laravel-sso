<script setup lang="ts">
import { Eye, EyeOff } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';
import { computed, ref } from 'vue';
import InputGroupIconButton from '@/components/InputGroupIconButton.vue';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
} from '@/components/ui/input-group';

/**
 * A password field that can be read back.
 *
 * The reveal lives inside the field rather than floating over it: an input
 * group gives the control a place of its own, so the text can never run under
 * it however long the password is.
 *
 * Outside the tab order on purpose. Someone tabbing from the password to the
 * submit button is signing in, not inspecting what they typed, and a stop in
 * between is a stop on every sign-in for a control wanted on almost none.
 */
defineOptions({ inheritAttrs: false });

const props = defineProps<{
    class?: HTMLAttributes['class'];
}>();

const showPassword = ref(false);

const label = computed(() =>
    showPassword.value ? 'Hide password' : 'Show password',
);
</script>

<template>
    <InputGroup :class="props.class">
        <InputGroupInput
            :type="showPassword ? 'text' : 'password'"
            v-bind="$attrs"
        />

        <InputGroupAddon align="inline-end">
            <InputGroupIconButton
                :label="label"
                :icon="showPassword ? EyeOff : Eye"
                :tabindex="-1"
                @click="showPassword = !showPassword"
            />
        </InputGroupAddon>
    </InputGroup>
</template>
