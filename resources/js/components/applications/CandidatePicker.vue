<script setup lang="ts">
import { UserPlus } from '@lucide/vue';
import SearchInput from '@/components/SearchInput.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { UserCandidate } from '@/types/administration';

/**
 * Choosing a person to add to an application.
 *
 * The same shape serves granting access and assigning a manager: search, a
 * short list of people the server says are eligible, and one control each. The
 * server does the narrowing, so what is offered here is always something the
 * request will accept.
 */
defineProps<{
    title: string;
    description: string;
    /** Names the search box for a screen reader. */
    searchLabel: string;
    action: string;
    candidates: UserCandidate[];
    empty: string;
}>();

const search = defineModel<string>('search', { required: true });

const emit = defineEmits<{ select: [number] }>();
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>{{ title }}</CardTitle>
            <CardDescription>{{ description }}</CardDescription>
        </CardHeader>

        <CardContent class="space-y-4">
            <SearchInput
                v-model="search"
                placeholder="Search by name or email"
                :label="searchLabel"
            />

            <ul v-if="candidates.length > 0" class="divide-y rounded-lg border">
                <li
                    v-for="candidate in candidates"
                    :key="candidate.id"
                    class="flex items-center justify-between gap-3 px-3 py-2"
                >
                    <div class="min-w-0">
                        <div class="font-medium">{{ candidate.name }}</div>
                        <div class="text-muted-foreground truncate text-sm">
                            {{ candidate.email }}
                        </div>
                    </div>

                    <Button
                        variant="outline"
                        size="sm"
                        @click="emit('select', candidate.id)"
                    >
                        <UserPlus class="size-4" />
                        {{ action }}
                    </Button>
                </li>
            </ul>

            <p v-else class="text-muted-foreground text-sm">{{ empty }}</p>
        </CardContent>
    </Card>
</template>
