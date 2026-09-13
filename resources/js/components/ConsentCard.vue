<script setup lang="ts">
import SignedInAccount from '@/components/SignedInAccount.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { ConsentWording } from '@/lib/consent';
import type { ScopeOption } from '@/types/administration';

/**
 * What the consent page shows under its heading, shared by the page itself and
 * the preview on the Consent tab.
 */
const { disabled = false, processing = false } = defineProps<{
    applicationName: string;
    scopes: ScopeOption[];
    consent: ConsentWording;
    disabled?: boolean;
    processing?: boolean;
}>();

const emit = defineEmits<{ approve: []; deny: [] }>();
</script>

<template>
    <div class="flex flex-col gap-6">
        <SignedInAccount v-if="consent.switchAccountUrl">
            <a
                :href="consent.switchAccountUrl"
                class="text-foreground mt-2 inline-block text-sm underline underline-offset-4"
                data-test="switch-account-link"
            >
                Use another account
            </a>
        </SignedInAccount>

        <div v-if="scopes.length > 0" class="grid gap-2">
            <p class="text-sm font-medium">
                {{ applicationName }} will be able to:
            </p>

            <ul class="grid gap-2">
                <li
                    v-for="scope in scopes"
                    :key="scope.id"
                    class="flex items-start gap-2 text-sm"
                >
                    <span
                        class="bg-muted-foreground mt-1.5 size-1.5 shrink-0 rounded-full"
                        aria-hidden="true"
                    />
                    <span>{{ scope.description }}</span>
                </li>
            </ul>
        </div>

        <p class="text-muted-foreground text-sm">
            You can withdraw this at any time by revoking the application's
            access.
        </p>

        <div class="flex flex-col gap-2 sm:flex-row-reverse">
            <Button
                class="sm:flex-1"
                :disabled="disabled || processing"
                data-test="approve-button"
                @click="emit('approve')"
            >
                <Spinner v-if="processing" />
                Allow
            </Button>

            <Button
                variant="outline"
                class="sm:flex-1"
                :disabled="disabled || processing"
                data-test="deny-button"
                @click="emit('deny')"
            >
                Cancel
            </Button>
        </div>

        <p
            v-if="consent.privacyUrl || consent.termsUrl"
            class="text-muted-foreground flex justify-center gap-4 text-xs"
        >
            <a
                v-if="consent.privacyUrl"
                :href="consent.privacyUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="underline underline-offset-4"
            >
                Privacy policy
            </a>
            <a
                v-if="consent.termsUrl"
                :href="consent.termsUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="underline underline-offset-4"
            >
                Terms
            </a>
        </p>
    </div>
</template>
