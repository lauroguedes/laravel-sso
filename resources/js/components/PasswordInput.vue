<script setup lang="ts">
import { Eye, EyeOff } from '@lucide/vue';
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
 *
 * Everything a caller passes, "class" included, reaches the input rather than
 * the group around it. The group is a flex row and full width already; a
 * layout class landing on it — "block", say — stops the reveal sitting inside
 * the field at all, which is a way of breaking this that nothing type-checks.
 */
defineOptions({ inheritAttrs: false });

const showPassword = ref(false);

const label = computed(() =>
    showPassword.value ? 'Hide password' : 'Show password',
);
</script>

<template>
    <InputGroup>
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
