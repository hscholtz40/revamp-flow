<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'
import { router } from '@inertiajs/vue3'
import { onBeforeUnmount, onMounted, ref } from 'vue'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()

const contentRef = ref<HTMLElement | null>(null)
let savedScrollTop = 0

onMounted(() => {
  const removeBefore = router.on('before', () => {
    if (contentRef.value) {
      savedScrollTop = contentRef.value.scrollTop
    }
  })

  const removeFinish = router.on('finish', () => {
    requestAnimationFrame(() => {
      if (contentRef.value) {
        contentRef.value.scrollTop = savedScrollTop
      }
    })
  })

  onBeforeUnmount(() => {
    removeBefore()
    removeFinish()
  })
})
</script>

<template>
  <div
    ref="contentRef"
    data-slot="sidebar-content"
    data-sidebar="content"
    :class="cn('flex min-h-0 flex-1 flex-col gap-2 overflow-auto overscroll-contain [overflow-anchor:none] group-data-[collapsible=icon]:overflow-hidden', props.class)"
  >
    <slot />
  </div>
</template>
