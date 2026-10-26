<script setup lang="ts">
import { Download } from 'lucide-vue-next'
import type { QrDesign } from '~/types/qr'
import { exportQr, type ExportFormat } from '~/utils/qr/render'

const props = withDefaults(defineProps<{ value: string; design: QrDesign; name?: string; disabled?: boolean; vectorAllowed?: boolean }>(), { vectorAllowed: true })
const busy = ref<ExportFormat | null>(null)
const toast = useToast()

async function download(format: ExportFormat) {
  busy.value = format
  try {
    // PDF has no transparency in most print workflows; export it on the chosen background.
    const design = format === 'pdf' ? { ...props.design, transparent: false } : { ...props.design }
    await exportQr(props.value, design, format, props.name)
  } catch (e) {
    toast.error((e as Error).message || 'Download failed.')
  } finally {
    busy.value = null
  }
}
</script>

<template>
  <div class="grid grid-cols-3 gap-2">
    <UiButton :disabled="disabled" :loading="busy === 'png'" @click="download('png')"><Download v-if="busy !== 'png'" class="size-4" /> PNG</UiButton>
    <UiButton variant="secondary" :disabled="disabled || !vectorAllowed" :loading="busy === 'svg'" :title="vectorAllowed ? undefined : 'Available on paid plans'" @click="download('svg')">SVG</UiButton>
    <UiButton variant="secondary" :disabled="disabled || !vectorAllowed" :loading="busy === 'pdf'" :title="vectorAllowed ? undefined : 'Available on paid plans'" @click="download('pdf')">PDF</UiButton>
  </div>
</template>
