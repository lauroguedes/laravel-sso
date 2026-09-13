<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Check, Plus, RotateCcw, X } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import BrandMark from '@/components/BrandMark.vue';
import ConsentCard from '@/components/ConsentCard.vue';
import DangerousAction from '@/components/DangerousAction.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import DurationField from '@/components/settings/DurationField.vue';
import ImageUploadField from '@/components/settings/ImageUploadField.vue';
import RadioCards from '@/components/settings/RadioCards.vue';
import SettingsField from '@/components/settings/SettingsField.vue';
import SwitchField from '@/components/SwitchField.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { nameApplication } from '@/lib/consent';
import type { ScopeOption } from '@/types/administration';
import type { DurationUnit } from '@/lib/duration';
import { useTabInUrl } from '@/composables/useTabInUrl';
import { destroy, edit, update } from '@/routes/application-settings';

/**
 * How the whole installation presents itself.
 *
 * Separate from the settings beside it, which each reader owns: everything
 * here is one decision for everybody, which is why it needs a platform
 * permission to reach.
 *
 * Each tab saves on its own. An administrator who came to rename the server
 * should not also resubmit its token lifetimes, and a mistake in one section
 * should not block saving another.
 */
defineOptions({
    layout: {
        breadcrumbs: [{ title: 'App settings', href: edit() }],
    },
});

type Settings = {
    brand_name: string;
    brand_logo: string | null;
    base_color: string;
    accent: string;
    rows_per_page: number;
    sidebar_variant: string;
    auth_layout: string;
    auth_background: string | null;
    documentation_links: { label: string; url: string }[];
    allow_registration: boolean;
    require_email_verification: boolean;
    access_token_ttl: number;
    refresh_token_ttl: number;
    id_token_ttl: number;
    session_lifetime: number;
    logout_other_sessions_on_password_change: boolean;
    audit_retention_days: number;
    consent_heading: string;
    consent_message: string | null;
    consent_scope_descriptions: Record<string, string>;
    consent_show_account: boolean;
    consent_remember_approvals: boolean;
    consent_privacy_url: string | null;
    consent_terms_url: string | null;
};

type Palette = { value: string; label: string; swatch: string };
type Variant = { value: string; label: string; description: string };

const { settings, pinned, tab, options } = defineProps<{
    settings: Settings;
    pinned: string[];
    /** Which tab to open, so saving one returns to the one that was saved. */
    tab: string;
    options: {
        baseColors: Palette[];
        accents: Palette[];
        rowsPerPage: number[];
        sidebarVariants: Variant[];
        authLayouts: Variant[];
        /* Which unit each lifetime counts, and what the server will accept. */
        durations: {
            name: keyof Settings;
            unit: DurationUnit;
            min: number;
            max: number;
        }[];
        sections: { value: string; label: string }[];
        /* What the consent page falls back to, for the preview. */
        consent: { defaultMessage: string; scopes: ScopeOption[] };
    };
}>();

const isPinned = (key: string) => pinned.includes(key);

/*
 * Each section saves with a redirect, and the server sends the reader back to
 * the section they submitted; this keeps the URL in step as they browse.
 */
const { rememberTab } = useTabInUrl();

/*
 * Held locally because the interface reacts to them before anything is saved:
 * the palette shows which swatch is chosen, and the background upload only
 * makes sense once the split layout is.
 */
const baseColor = ref(settings.base_color);
const accent = ref(settings.accent);
const sidebarVariant = ref(settings.sidebar_variant);
const authLayout = ref(settings.auth_layout);

const showsBackground = computed(() => authLayout.value === 'split');

/* One blank row so there is always something to type into. */
const links = ref(
    settings.documentation_links.length > 0
        ? [...settings.documentation_links]
        : [{ label: '', url: '' }],
);

function addLink() {
    links.value.push({ label: '', url: '' });
}

function removeLink(position: number) {
    links.value.splice(position, 1);

    if (links.value.length === 0) {
        addLink();
    }
}

/*
 * The three access switches, which differ only in their words. An unchecked
 * switch posts nothing, so each one's absence is read as "off" by the server.
 */
const switches = computed(() => [
    {
        name: 'allow_registration',
        label: 'Allow people to register themselves',
        description:
            'Off by default. An Identity Provider is rarely open to the public; with this off the registration page does not exist at all, rather than being hidden.',
        defaultValue: settings.allow_registration,
    },
    {
        name: 'require_email_verification',
        label: 'Require a verified email address',
        description:
            'New accounts must confirm their address before they can sign in to anything.',
        defaultValue: settings.require_email_verification,
    },
    {
        name: 'logout_other_sessions_on_password_change',
        label: 'Sign other sessions out on a password change',
        description:
            'Somebody changing their password because it was exposed expects it to end whoever else was using it.',
        defaultValue: settings.logout_other_sessions_on_password_change,
    },
]);

/*
 * The lifetimes. What each is counted in, and what the server will accept,
 * come with the setting: the unit is decided by whichever consumer reads it,
 * and a field that guessed could offer a bound the request refuses.
 */
const explanations: Record<string, { label: string; description: string }> = {
    access_token_ttl: {
        label: 'Access token',
        description:
            'How long an issued access token stays valid. Short by design: a relying party refreshes rather than holding one for long.',
    },
    refresh_token_ttl: {
        label: 'Refresh token',
        description:
            'How long a signed-in application can keep renewing its access without the person coming back to this server.',
    },
    id_token_ttl: {
        label: 'ID token',
        description:
            'How long a relying party will accept the token as a fresh statement about who signed in.',
    },
    session_lifetime: {
        label: 'Session',
        description:
            'Inactivity before somebody has to sign in to this server again.',
    },
    audit_retention_days: {
        label: 'Audit retention',
        description:
            'How much of the audit trail is kept. Older entries are removed by the scheduled clean-up.',
    },
};

/*
 * The Consent tab previews the page as it is typed, for an example application.
 * A blank field falls back to what the server would show.
 */
const consentDraft = reactive({
    heading: settings.consent_heading,
    message: settings.consent_message ?? '',
    scopes: { ...settings.consent_scope_descriptions } as Record<
        string,
        string
    >,
    showAccount: settings.consent_show_account,
    privacyUrl: settings.consent_privacy_url ?? '',
    termsUrl: settings.consent_terms_url ?? '',
});

const previewApplication = 'Example application';

const consentPreview = computed(() => ({
    scopes: options.consent.scopes.map((scope) => ({
        id: scope.id,
        description: consentDraft.scopes[scope.id]?.trim() || scope.description,
    })),
    consent: {
        heading: nameApplication(consentDraft.heading, previewApplication),
        message:
            consentDraft.message.trim() === ''
                ? options.consent.defaultMessage
                : nameApplication(consentDraft.message, previewApplication),
        switchAccountUrl: consentDraft.showAccount ? '#' : null,
        privacyUrl: consentDraft.privacyUrl.trim() || null,
        termsUrl: consentDraft.termsUrl.trim() || null,
    },
}));

type SwitchOption = {
    name: string;
    label: string;
    description: string;
    defaultValue: boolean;
    onChange?: (checked: boolean) => void;
};

const consentSwitches = computed<SwitchOption[]>(() => [
    {
        name: 'consent_show_account',
        label: 'Show the signed-in account',
        description:
            'Who is about to approve, with a link to sign in as somebody else instead.',
        defaultValue: settings.consent_show_account,
        onChange: (checked) => {
            consentDraft.showAccount = checked;
        },
    },
    {
        name: 'consent_remember_approvals',
        label: 'Remember approvals',
        description:
            'An application somebody already approved does not ask again for the same scopes, for as long as the access token it was issued lasts. Turned off, every sign-in asks.',
        defaultValue: settings.consent_remember_approvals,
    },
]);

function resetToDefaults() {
    router.delete(destroy().url);
}
</script>

<template>
    <Head title="App settings" />

    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                variant="small"
                title="App settings"
                description="How this server names itself, how it looks, and what it allows"
            />

            <DangerousAction
                title="Restore the factory settings?"
                description="Every setting on this page goes back to what this server shipped with, including the brand name, the palette and any uploaded imagery. Nothing else is affected."
                confirm-label="Restore defaults"
                @confirm="resetToDefaults"
            >
                <Button variant="outline" size="sm">
                    <RotateCcw class="size-4" />
                    Reset
                </Button>
            </DangerousAction>
        </div>

        <Tabs
            :default-value="tab"
            class="gap-6"
            @update:model-value="rememberTab"
        >
            <TabsList>
                <TabsTrigger
                    v-for="section in options.sections"
                    :key="section.value"
                    :value="section.value"
                >
                    {{ section.label }}
                </TabsTrigger>
            </TabsList>

            <TabsContent value="brand">
                <Form
                    v-bind="update.form()"
                    class="max-w-2xl space-y-6"
                    enctype="multipart/form-data"
                    v-slot="{ errors, processing }"
                >
                    <input type="hidden" name="section" value="brand" />

                    <SettingsField
                        id="brand_name"
                        label="Name"
                        description="Shown in the interface, on the consent screen, and as the sender of every message this server sends."
                        :error="errors.brand_name"
                        :pinned="isPinned('brand_name')"
                    >
                        <Input
                            id="brand_name"
                            name="brand_name"
                            :default-value="settings.brand_name"
                            :disabled="isPinned('brand_name')"
                            maxlength="60"
                        />
                    </SettingsField>

                    <ImageUploadField
                        name="logo"
                        label="Logo"
                        description="Square works best, up to 512 KB. Shown on the sign-in page before anybody has signed in, so avoid anything confidential."
                        accept="image/png,image/jpeg,image/svg+xml,image/webp"
                        :stored="settings.brand_logo !== null"
                        :error="errors.logo"
                    >
                        <template #preview>
                            <BrandMark size="lg" />
                        </template>
                    </ImageUploadField>

                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        Save brand
                    </Button>
                </Form>
            </TabsContent>

            <TabsContent value="appearance">
                <Form
                    v-bind="update.form()"
                    class="max-w-2xl space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <input type="hidden" name="section" value="appearance" />

                    <SettingsField
                        label="Base colour"
                        description="The greys the interface is mostly made of. Light and dark are still each reader's own choice, in the header."
                        :error="errors.base_color"
                        :pinned="isPinned('base_color')"
                    >
                        <RadioCards
                            v-model="baseColor"
                            name="base_color"
                            inline
                            :options="options.baseColors"
                            :disabled="isPinned('base_color')"
                            v-slot="{ option, checked }"
                        >
                            <span
                                class="flex size-5 items-center justify-center rounded-full border"
                                :style="{
                                    backgroundColor: option.swatch,
                                }"
                            >
                                <Check
                                    v-if="checked"
                                    class="size-3 text-white drop-shadow"
                                />
                            </span>
                            {{ option.label }}
                        </RadioCards>
                    </SettingsField>

                    <SettingsField
                        label="Accent"
                        description="The one colour the interface draws attention with: buttons, links and focus rings."
                        :error="errors.accent"
                        :pinned="isPinned('accent')"
                    >
                        <RadioCards
                            v-model="accent"
                            name="accent"
                            inline
                            :options="options.accents"
                            :disabled="isPinned('accent')"
                            v-slot="{ option, checked }"
                        >
                            <span
                                class="flex size-5 items-center justify-center rounded-full border"
                                :style="{
                                    backgroundColor: option.swatch,
                                }"
                            >
                                <Check
                                    v-if="checked"
                                    class="size-3 text-white drop-shadow"
                                />
                            </span>
                            {{ option.label }}
                        </RadioCards>
                    </SettingsField>

                    <p class="text-muted-foreground text-sm">
                        Saving a colour reloads the page, because the palette is
                        a stylesheet in the document itself.
                    </p>

                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        Save appearance
                    </Button>
                </Form>
            </TabsContent>

            <TabsContent value="layout">
                <Form
                    v-bind="update.form()"
                    class="max-w-2xl space-y-6"
                    enctype="multipart/form-data"
                    v-slot="{ errors, processing }"
                >
                    <input type="hidden" name="section" value="layout" />

                    <SettingsField
                        label="Rows per table"
                        description="A reader can still change this for themselves at the foot of any table."
                        :error="errors.rows_per_page"
                        :pinned="isPinned('rows_per_page')"
                    >
                        <Select
                            name="rows_per_page"
                            :default-value="String(settings.rows_per_page)"
                            :disabled="isPinned('rows_per_page')"
                        >
                            <SelectTrigger class="w-40">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="size in options.rowsPerPage"
                                    :key="size"
                                    :value="String(size)"
                                >
                                    {{ size }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </SettingsField>

                    <SettingsField
                        label="Navigation"
                        :error="errors.sidebar_variant"
                        :pinned="isPinned('sidebar_variant')"
                    >
                        <RadioCards
                            v-model="sidebarVariant"
                            name="sidebar_variant"
                            :options="options.sidebarVariants"
                            :disabled="isPinned('sidebar_variant')"
                            v-slot="{ option }"
                        >
                            <span class="grid gap-0.5">
                                <span class="text-sm font-medium">
                                    {{ option.label }}
                                </span>
                                <span class="text-muted-foreground text-sm">
                                    {{ option.description }}
                                </span>
                            </span>
                        </RadioCards>
                    </SettingsField>

                    <SettingsField
                        label="Sign-in page"
                        :error="errors.auth_layout"
                        :pinned="isPinned('auth_layout')"
                    >
                        <RadioCards
                            v-model="authLayout"
                            name="auth_layout"
                            :options="options.authLayouts"
                            :disabled="isPinned('auth_layout')"
                            v-slot="{ option }"
                        >
                            <span class="grid gap-0.5">
                                <span class="text-sm font-medium">
                                    {{ option.label }}
                                </span>
                                <span class="text-muted-foreground text-sm">
                                    {{ option.description }}
                                </span>
                            </span>
                        </RadioCards>
                    </SettingsField>

                    <!--
                        Only the split layout has a panel to put a picture in,
                        so the field appears with the layout that uses it.
                    -->
                    <ImageUploadField
                        v-if="showsBackground"
                        name="auth_background"
                        label="Sign-in background"
                        description="Fills the panel beside the sign-in form, up to 2 MB. Publicly readable, like the logo."
                        accept="image/png,image/jpeg,image/webp"
                        :stored="settings.auth_background !== null"
                        :error="errors.auth_background"
                    >
                        <template #preview>
                            <img
                                :src="settings.auth_background ?? ''"
                                alt=""
                                class="h-12 w-20 rounded border object-cover"
                            />
                        </template>
                    </ImageUploadField>

                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        Save layout
                    </Button>
                </Form>
            </TabsContent>

            <TabsContent value="links">
                <Form
                    v-bind="update.form()"
                    class="max-w-2xl space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <input type="hidden" name="section" value="links" />

                    <p class="text-muted-foreground text-sm">
                        Shown at the foot of the menu, for whoever integrates
                        applications. The protocol reference starts here rather
                        than being built in, so it can be reworded, moved below
                        your own runbook, or removed.
                    </p>

                    <div
                        v-for="(link, position) in links"
                        :key="position"
                        class="flex flex-wrap items-start gap-2"
                    >
                        <Input
                            v-model="link.label"
                            :name="`documentation_links[${position}][label]`"
                            placeholder="Runbook"
                            class="max-w-40"
                        />
                        <Input
                            v-model="link.url"
                            :name="`documentation_links[${position}][url]`"
                            type="url"
                            inputmode="url"
                            placeholder="https://wiki.example.com/sso"
                            class="max-w-sm"
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            :aria-label="`Remove link ${position + 1}`"
                            @click="removeLink(position)"
                        >
                            <X class="size-4" />
                        </Button>

                        <InputError
                            class="w-full"
                            :message="
                                errors[
                                    `documentation_links.${position}.label`
                                ] ??
                                errors[`documentation_links.${position}.url`]
                            "
                        />
                    </div>

                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="addLink"
                    >
                        <Plus class="size-4" />
                        Add link
                    </Button>

                    <div>
                        <Button type="submit" :disabled="processing">
                            <Spinner v-if="processing" />
                            Save links
                        </Button>
                    </div>
                </Form>
            </TabsContent>

            <TabsContent value="access">
                <Form
                    v-bind="update.form()"
                    class="max-w-2xl space-y-6"
                    v-slot="{ errors, processing }"
                >
                    <input type="hidden" name="section" value="access" />

                    <div class="grid gap-4">
                        <div
                            v-for="option in switches"
                            :key="option.name"
                            class="rounded-lg border p-3"
                        >
                            <SwitchField
                                :name="option.name"
                                :label="option.label"
                                :description="option.description"
                                :default-value="option.defaultValue"
                                :errors="errors"
                            />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <DurationField
                            v-for="duration in options.durations"
                            :key="duration.name"
                            :name="duration.name"
                            :label="explanations[duration.name].label"
                            :description="
                                explanations[duration.name].description
                            "
                            :default-value="settings[duration.name] as number"
                            :unit="duration.unit"
                            :min="duration.min"
                            :max="duration.max"
                            :error="errors[duration.name]"
                            :pinned="isPinned(duration.name)"
                        />
                    </div>

                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        Save access
                    </Button>
                </Form>
            </TabsContent>
            <TabsContent value="consent">
                <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <Form
                        v-bind="update.form()"
                        class="space-y-6"
                        v-slot="{ errors, processing }"
                    >
                        <input type="hidden" name="section" value="consent" />

                        <SettingsField
                            id="consent_heading"
                            label="Heading"
                            description="The title of the consent page. {application} is replaced with the name of the application asking."
                            :error="errors.consent_heading"
                        >
                            <Input
                                id="consent_heading"
                                v-model="consentDraft.heading"
                                name="consent_heading"
                                maxlength="120"
                            />
                        </SettingsField>

                        <SettingsField
                            id="consent_message"
                            label="Message"
                            description="The line under the heading. Left blank, the page shows the application's own description."
                            :error="errors.consent_message"
                        >
                            <Textarea
                                id="consent_message"
                                v-model="consentDraft.message"
                                name="consent_message"
                                maxlength="300"
                                rows="2"
                            />
                        </SettingsField>

                        <div class="grid gap-3">
                            <div class="grid gap-1">
                                <p class="text-sm font-medium">
                                    What each scope says
                                </p>
                                <p class="text-muted-foreground text-sm">
                                    Leave a scope blank to keep its standard
                                    wording.
                                </p>
                            </div>

                            <SettingsField
                                v-for="scope in options.consent.scopes"
                                :id="`consent_scope_${scope.id}`"
                                :key="scope.id"
                                :label="scope.id"
                                :error="
                                    errors[
                                        `consent_scope_descriptions.${scope.id}`
                                    ]
                                "
                            >
                                <Input
                                    :id="`consent_scope_${scope.id}`"
                                    v-model="consentDraft.scopes[scope.id]"
                                    :name="`consent_scope_descriptions[${scope.id}]`"
                                    :placeholder="scope.description"
                                    maxlength="160"
                                />
                            </SettingsField>
                        </div>

                        <div class="grid gap-4">
                            <div
                                v-for="option in consentSwitches"
                                :key="option.name"
                                class="rounded-lg border p-3"
                            >
                                <SwitchField
                                    :name="option.name"
                                    :label="option.label"
                                    :description="option.description"
                                    :default-value="option.defaultValue"
                                    :errors="errors"
                                    @change="option.onChange?.($event)"
                                />
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <SettingsField
                                id="consent_privacy_url"
                                label="Privacy policy"
                                description="Your organisation's policy, linked under the buttons when set."
                                :error="errors.consent_privacy_url"
                            >
                                <Input
                                    id="consent_privacy_url"
                                    v-model="consentDraft.privacyUrl"
                                    name="consent_privacy_url"
                                    type="url"
                                    inputmode="url"
                                    placeholder="https://example.com/privacy"
                                />
                            </SettingsField>

                            <SettingsField
                                id="consent_terms_url"
                                label="Terms"
                                description="Your organisation's terms, linked under the buttons when set."
                                :error="errors.consent_terms_url"
                            >
                                <Input
                                    id="consent_terms_url"
                                    v-model="consentDraft.termsUrl"
                                    name="consent_terms_url"
                                    type="url"
                                    inputmode="url"
                                    placeholder="https://example.com/terms"
                                />
                            </SettingsField>
                        </div>

                        <Button type="submit" :disabled="processing">
                            <Spinner v-if="processing" />
                            Save consent
                        </Button>
                    </Form>

                    <div class="grid content-start gap-2">
                        <p class="text-sm font-medium">Preview</p>

                        <div class="rounded-xl border p-6" inert>
                            <div class="mb-6 space-y-2 text-center">
                                <p class="text-xl font-medium">
                                    {{ consentPreview.consent.heading }}
                                </p>
                                <p class="text-muted-foreground text-sm">
                                    {{ consentPreview.consent.message }}
                                </p>
                            </div>

                            <ConsentCard
                                :application-name="previewApplication"
                                :scopes="consentPreview.scopes"
                                :consent="consentPreview.consent"
                                disabled
                            />
                        </div>
                    </div>
                </div>
            </TabsContent>
        </Tabs>
    </div>
</template>
