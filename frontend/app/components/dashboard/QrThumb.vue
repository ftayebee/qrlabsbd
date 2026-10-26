<script setup lang="ts">
import type { QrCodeRecord } from '~/types/qr'
import { DEFAULT_DESIGN } from '~/types/qr'

/** Small rendered preview of a saved code. Codes whose value is withheld (Wi-Fi in lists) show their type icon. */
const props = withDefaults(defineProps<{ qr: QrCodeRecord; size?: number }>(), { size: 72 })
const design = computed(() => ({ ...DEFAULT_DESIGN, ...(props.qr.design ?? {}), frame: 'none' as const, margin: 6 }))

const el = ref<HTMLElement | null>(null)
const visible = ref(false)
let observer: IntersectionObserver | undefined
onMounted(() => {
  if (!el.value) return
  // Render straight away when already on screen; otherwise wait until scrolled near.
  const rect = el.value.getBoundingClientRect()
  if (rect.top < window.innerHeight + 200 || typeof IntersectionObserver === 'undefined') { visible.value = true; return }
  observer = new IntersectionObserver(([e]) => { if (e?.isIntersecting) { visible.value = true; observer?.disconnect() } }, { rootMargin: '200px' })
  observer.observe(el.value)
})
onBeforeUnmount(() => observer?.disconnect())
</script>

<template>
  <div ref="el" class="grid shrink-0 place-items-center overflow-hidden rounded-xl border border-line bg-white" :style="{ width: `${size}px`, height: `${size}px` }">
    <QrPreview v-if="visible && qr.encodedValue" :value="qr.encodedValue" :design="design" :size="size - 6" />
    <QrTypeIcon v-else :type="qr.type" class="size-6 text-muted" />
  </div>
</template>
