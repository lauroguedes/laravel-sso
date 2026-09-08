<script setup lang="ts">
import { FolderGit2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CopyButton from '@/components/CopyButton.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
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
 */
const { url } = defineProps<{ url: string }>();

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

const open = ref(false);
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

watch(open, (isOpen) => {
    if (isOpen) {
        void load();
    }
});
</script>

<template>
    <Dialog v-model:open="open">
        <SidebarMenuItem>
            <DialogTrigger as-child>
                <SidebarMenuButton
                    class="text-neutral-600 hover:text-neutral-800 dark:text-neutral-300 dark:hover:text-neutral-100"
                >
                    <FolderGit2 />
                    <span>Discovery document</span>
                </SidebarMenuButton>
            </DialogTrigger>
        </SidebarMenuItem>

        <DialogContent class="max-h-[80vh] gap-4 sm:max-w-3xl">
            <DialogHeader>
                <DialogTitle>Discovery document</DialogTitle>
                <DialogDescription>
                    What this server tells relying parties about itself. Point a
                    client library at the issuer and it reads this.
                </DialogDescription>
            </DialogHeader>

            <div class="flex flex-wrap items-center gap-2">
                <code
                    class="bg-muted min-w-0 flex-1 overflow-x-auto rounded px-3 py-2 font-mono text-xs"
                >
                    {{ url }}
                </code>

                <CopyButton :value="url" label="Copy URL" />

                <Button variant="outline" size="sm" as-child>
                    <a :href="url" target="_blank" rel="noopener noreferrer">
                        Open raw
                    </a>
                </Button>
            </div>

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

            <pre
                v-else
                class="bg-muted max-h-[50vh] overflow-auto rounded-lg p-4 font-mono text-xs leading-relaxed"
            ><code v-html="highlighted" /></pre>
        </DialogContent>
    </Dialog>
</template>
