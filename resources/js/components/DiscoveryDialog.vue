<script setup lang="ts">
import { ExternalLink } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CodeBlock from '@/components/CodeBlock.vue';
import InputGroupIconButton from '@/components/InputGroupIconButton.vue';
import ReadOnlyField from '@/components/ReadOnlyField.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

/**
 * Shows this server's OpenID Connect discovery document, formatted.
 *
 * The document is the thing an integrator reads most often and the raw
 * response is a single unbroken line, so it is fetched and pretty-printed
 * here rather than handing the browser a JSON URL to render however it likes.
 *
 * A link to the raw document is kept alongside, because that is the URL a
 * client library is actually pointed at.
 *
 * Opened from outside rather than by a trigger of its own: it is reached from
 * the account menu, and a menu item closes the menu it is in — taking a
 * trigger rendered inside that menu, and the dialog with it.
 */
const { url, open } = defineProps<{ url: string; open: boolean }>();

const emit = defineEmits<{ 'update:open': [boolean] }>();

/*
 * Displayed and fetched from different places on purpose. The displayed URL is
 * the canonical one built from the issuer, which is what a relying party is
 * pointed at; behind a proxy it is a different host from the one this browser
 * is on, so fetching it would be a cross-origin request that the server has no
 * reason to allow. The path is identical either way, and same-origin serves
 * the very same document.
 */
const source = computed(() => {
    try {
        return new URL(url).pathname;
    } catch {
        return url;
    }
});

const raw = ref<string | null>(null);
const failure = ref<string | null>(null);
const loading = ref(false);

/**
 * Colour the document's structure.
 *
 * Escaping happens first and the result is inserted as HTML, so a value in the
 * document can never introduce markup of its own.
 */
function highlight(json: string): string {
    const escaped = json
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    return escaped.replace(
        /("(?:\\.|[^\\"])*"\s*:)|("(?:\\.|[^\\"])*")|(\b(?:true|false)\b)|(\bnull\b)|(-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?)/g,
        (match, key, string, boolean, nul, number) => {
            if (key) {
                return `<span class="text-sky-700 dark:text-sky-300">${key}</span>`;
            }

            if (string) {
                return `<span class="text-emerald-700 dark:text-emerald-300">${string}</span>`;
            }

            if (boolean) {
                return `<span class="text-purple-700 dark:text-purple-300">${boolean}</span>`;
            }

            if (nul) {
                return `<span class="text-muted-foreground">${nul}</span>`;
            }

            return `<span class="text-amber-700 dark:text-amber-300">${number}</span>`;
        },
    );
}

const highlighted = computed(() =>
    raw.value === null ? '' : highlight(raw.value),
);

async function load() {
    if (raw.value !== null || loading.value) {
        return;
    }

    loading.value = true;
    failure.value = null;

    try {
        const response = await fetch(source.value, {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error(`The server answered ${response.status}.`);
        }

        raw.value = JSON.stringify(await response.json(), null, 2);
    } catch (error) {
        failure.value =
            error instanceof Error
                ? error.message
                : 'The discovery document could not be read.';
    } finally {
        loading.value = false;
    }
}

function openRaw() {
    window.open(url, '_blank', 'noopener,noreferrer');
}

watch(
    () => open,
    (isOpen) => {
        if (isOpen) {
            void load();
        }
    },
);
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[80vh] gap-4 sm:max-w-3xl">
            <DialogHeader>
                <DialogTitle>Discovery document</DialogTitle>
                <DialogDescription>
                    What this server tells relying parties about itself. Point a
                    client library at the issuer and it reads this.
                </DialogDescription>
            </DialogHeader>

            <ReadOnlyField
                label="Discovery URL"
                label-hidden
                :value="url"
                copy-label="Copy discovery URL"
            >
                <InputGroupIconButton
                    label="Open the raw document"
                    :icon="ExternalLink"
                    @click="openRaw"
                />
            </ReadOnlyField>

            <div
                v-if="loading"
                class="text-muted-foreground flex items-center gap-2 py-8 text-sm"
            >
                <Spinner />
                Reading the discovery document…
            </div>

            <p v-else-if="failure" class="text-destructive py-8 text-sm">
                {{ failure }}
            </p>

            <CodeBlock
                v-else
                label="openid-configuration.json"
                :code="raw ?? ''"
                :highlighted="highlighted"
            />
        </DialogContent>
    </Dialog>
</template>
