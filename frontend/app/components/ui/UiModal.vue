<script setup lang="ts">
import { X } from 'lucide-vue-next'

const open = defineModel<boolean>('open', { default: false })
const props = withDefaults(defineProps<{ title: string; description?: string; size?: 'sm' | 'md' | 'lg' }>(), { size: 'md' })

const panel = ref<HTMLElement | null>(null)
let previouslyFocused: HTMLElement | null = null
const titleId = useId()

function close() { open.value = false }

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape') close()
  if (e.key !== 'Tab' || !panel.value) return
  const focusable = panel.value.querySelectorAll<HTMLElement>('a[href],button:not([disabled]),input:not([disabled]),select,textarea,[tabindex]:not([tabindex="-1"])')
  if (!focusable.length) return
  const first = focusable[0]!
  const last = focusable[focusable.length - 1]!
  if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus() }
  else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus() }
}

watch(open, async (v) => {
  if (import.meta.server) return
  if (v) {
    previouslyFocused = document.activeElement as HTMLElement
    document.addEventListener('keydown', onKeydown)
    document.body.style.overflow = 'hidden'
    await nextTick()
    panel.value?.querySelector<HTMLElement>('input,select,textarea,button')?.focus()
  } else {
    document.removeEventListener('keydown', onKeydown)
    document.body.style.overflow = ''
    previouslyFocused?.focus()
  }
})

onBeforeUnmount(() => {
  if (import.meta.server) return
  document.removeEventListener('keydown', onKeydown)
  document.body.style.overflow = ''
})

const widths = { sm: 'max-w-md', md: 'max-w-lg', lg: 'max-w-2xl' }
</script>

<template>
  <Teleport to="body">
    <AnimatePresence>
      <div v-if="open" class="fixed inset-0 z-50 flex items-end justify-center p-0 sm:items-center sm:p-6">
        <Motion
          as="div"
          class="absolute inset-0 bg-charcoal/40 backdrop-blur-[2px]"
          :initial="{ opacity: 0 }" :animate="{ opacity: 1 }" :exit="{ opacity: 0 }" :transition="{ duration: 0.18 }"
          @click="close"
        />
        <Motion
          as="div"
          role="dialog" aria-modal="true" :aria-labelledby="titleId"
          :class="['relative w-full rounded-t-3xl bg-white shadow-[var(--shadow-lift)] sm:rounded-3xl', widths[props.size]]"
          :initial="{ opacity: 0, y: 24, scale: 0.98 }" :animate="{ opacity: 1, y: 0, scale: 1 }" :exit="{ opacity: 0, y: 12, scale: 0.98 }"
          :transition="{ type: 'spring', duration: 0.35, bounce: 0.12 }"
        >
          <div ref="panel" class="max-h-[85vh] overflow-y-auto p-6 sm:p-7">
            <div class="mb-5 flex items-start justify-between gap-4">
              <div>
                <h2 :id="titleId" class="text-lg font-bold">{{ title }}</h2>
                <p v-if="description" class="mt-1 text-sm text-muted">{{ description }}</p>
              </div>
              <button type="button" class="-mr-2 -mt-1 rounded-lg p-2 text-muted hover:bg-canvas hover:text-ink" aria-label="Close dialog" @click="close">
                <X class="size-5" />
              </button>
            </div>
            <slot />
            <div v-if="$slots.footer" class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
              <slot name="footer" />
            </div>
          </div>
        </Motion>
      </div>
    </AnimatePresence>
  </Teleport>
</template>
