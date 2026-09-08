<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'
import {
  applySidebarScrollToDom,
  captureSidebarScrollFromDom,
  ensureSidebarScrollPersistence,
  setSidebarScrollTop,
} from '@/lib/sidebarScroll'
import { onBeforeUnmount, onMounted, ref } from 'vue'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()

const contentRef = ref<HTMLElement | null>(null)

function onScroll() {
  if (contentRef.value) {
    setSidebarScrollTop(contentRef.value.scrollTop)
  }
}

onMounted(() => {
  ensureSidebarScrollPersistence()
  applySidebarScrollToDom()
})

onBeforeUnmount(() => {
  captureSidebarScrollFromDom()
})
</script>

<template>
  <div
    ref="contentRef"
    data-slot="sidebar-content"
    data-sidebar="content"
    :class="cn('flex min-h-0 flex-1 flex-col gap-2 overflow-auto overscroll-contain [overflow-anchor:none] group-data-[collapsible=icon]:overflow-hidden', props.class)"
    @scroll.passive="onScroll"
  >
    <slot />
  </div>
</template>
