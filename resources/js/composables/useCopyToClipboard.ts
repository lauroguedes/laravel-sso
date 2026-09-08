import type { ComputedRef, Ref } from 'vue';
import { useClipboard } from '@vueuse/core';
import { computed } from 'vue';

export type UseCopyToClipboardReturn = {
    copy: (value: string) => Promise<void>;
    copied: Ref<boolean>;
    isSupported: Ref<boolean>;
    label: ComputedRef<string>;
};

/**
 * Copying a value, and saying so for a moment afterwards.
 *
 * The behaviour rather than the button: the same "Copied" window and the same
 * unsupported-browser guard serve a control that stands beside a value and one
 * that sits inside the field holding it, and those two should not be able to
 * disagree about how long the confirmation lasts.
 */
export function useCopyToClipboard(
    resting: () => string,
): UseCopyToClipboardReturn {
    const { copy, copied, isSupported } = useClipboard({ copiedDuring: 2000 });

    return {
        copy,
        copied,
        isSupported,
        label: computed(() => (copied.value ? 'Copied' : resting())),
    };
}
