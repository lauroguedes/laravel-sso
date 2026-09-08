<script setup lang="ts">
import { Clock } from '@lucide/vue';
import { computed, ref } from 'vue';
import SettingsField from '@/components/settings/SettingsField.vue';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
    InputGroupText,
} from '@/components/ui/input-group';
import type { DurationUnit } from '@/lib/duration';
import { humanizeDuration } from '@/lib/duration';

/**
 * A length of time, in whatever unit the server stores it in.
 *
 * Token lifetimes are configured in seconds because that is what the protocol
 * counts, and 1209600 is not a number anybody reads as a fortnight. The
 * reading sits inside the field and follows what is typed, so the unit stays
 * honest and the meaning is never a mental calculation.
 */
const { defaultValue, unit } = defineProps<{
    name: string;
    label: string;
    description: string;
    /** Seeds the field; the value reaches the server through `name`. */
    defaultValue: number;
    unit: DurationUnit;
    min: number;
    max: number;
    error?: string;
    pinned?: boolean;
}>();

const entered = ref(defaultValue);

const reading = computed(() => humanizeDuration(entered.value, unit));
</script>

<template>
    <SettingsField
        :id="name"
        :label="label"
        :description="description"
        explain="tooltip"
        :error="error"
        :pinned="pinned"
    >
        <InputGroup>
            <InputGroupAddon>
                <Clock />
            </InputGroupAddon>

            <!--
                Without the spinner the reading beside it has room; with it,
                the two controls sit on top of each other the moment the field
                is focused.
            -->
            <InputGroupInput
                :id="name"
                v-model.number="entered"
                :name="name"
                type="number"
                inputmode="numeric"
                :min="min"
                :max="max"
                :disabled="pinned"
                class="[appearance:textfield] tabular-nums [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
            />

            <!--
                Reserved even when empty, so typing a digit does not shift the
                field's own width as the reading appears and disappears.
            -->
            <InputGroupAddon align="inline-end">
                <InputGroupText class="tabular-nums">
                    {{ reading ?? unit }}
                </InputGroupText>
            </InputGroupAddon>
        </InputGroup>
    </SettingsField>
</template>
