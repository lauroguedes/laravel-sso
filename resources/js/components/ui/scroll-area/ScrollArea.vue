<script setup lang="ts">
import type { ScrollAreaRootProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import {
  ScrollAreaCorner,
  ScrollAreaRoot,
  ScrollAreaViewport,
} from "reka-ui"
import { cn } from "@/lib/utils"
import ScrollBar from "./ScrollBar.vue"

/**
 * Two additions to the upstream component.
 *
 * `viewportClass` bounds the scrolling region rather than the frame around it.
 * The viewport is `height: 100%`, which against an auto-height root resolves to
 * the content's own height — so a `max-h` on the root clips what it cannot
 * show instead of letting it scroll. Callers that want "grow to fit, then
 * scroll" have to constrain the viewport.
 *
 * `horizontal` adds the sideways scrollbar in its proper place. It belongs
 * beside the viewport, not inside it, so it cannot simply be passed as slot
 * content.
 */
const props = defineProps<ScrollAreaRootProps & {
  class?: HTMLAttributes["class"]
  viewportClass?: HTMLAttributes["class"]
  horizontal?: boolean
}>()

const delegatedProps = reactiveOmit(props, "class", "viewportClass", "horizontal")
</script>

<template>
  <ScrollAreaRoot
    data-slot="scroll-area"
    v-bind="delegatedProps"
    :class="cn('relative', props.class)"
  >
    <ScrollAreaViewport
      data-slot="scroll-area-viewport"
      :class="cn(
        'focus-visible:ring-ring/50 size-full rounded-[inherit] transition-[color,box-shadow] outline-none focus-visible:ring-3 focus-visible:outline-1',
        props.viewportClass,
      )"
    >
      <slot />
    </ScrollAreaViewport>
    <ScrollBar />
    <ScrollBar v-if="horizontal" orientation="horizontal" />
    <ScrollAreaCorner />
  </ScrollAreaRoot>
</template>
