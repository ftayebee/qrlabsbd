<script setup lang="ts">
import type { QrDesign } from '~/types/qr'
import { renderQrSvg } from '~/utils/qr/render'

/**
 * Live preview. Rendering is debounced and runs only in the browser; the SVG
 * is produced by our own renderer (labels are XML-escaped), so v-html is safe.
 */
const props = withDefaults(defineProps<{ value: string; design: QrDesign; size?: number; dimmed?: boolean }>(), { size: 280 })

const svg = ref('')
const error = ref('')
let timer: ReturnType<typeof setTimeout> | undefined
let ticket = 0

async function render() {
  const mine = ++ticket
  try {
    const result = await renderQrSvg(props.value || ' ', { ...props.design }, 320)
    if (mine === ticket) { svg.value = result.svg; error.value = '' }
  } catch (e) {
    if (mine === ticket) error.value = e instanceof Error && /too long|overflow|code length/i.test(e.message) ? 'This content is too long for a QR code.' : 'Could not render this code.'
  }
}

watch(() => [props.value, JSON.stringify(props.design)], () => {
  clearTimeout(timer)
  timer = setTimeout(render, 120)
})
onMounted(render)
onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
  <div class="relative grid place-items-center" :style="{ width: `${size}px`, maxWidth: '100%', aspectRatio: design.frame === 'none' ? '1' : '0.85' }">
    <div v-if="!svg && !error" class="size-full animate-pulse rounded-2xl bg-canvas" />
    <p v-else-if="error" class="px-6 text-center text-sm font-medium text-danger" role="alert">{{ error }}</p>
    <Transition v-else name="fade" mode="out-in">
      <div
        :key="svg.length"
        :class="['size-full transition-[opacity,filter] duration-300 [&>svg]:h-full [&>svg]:w-full', dimmed ? 'opacity-35 blur-[1px]' : '']"
        role="img" :aria-label="`QR code preview`"
        v-html="svg"
      />
    </Transition>
  </div>
</template>
