<script setup lang="ts">
import { AlertTriangle, CheckCircle2, Info, XCircle } from 'lucide-vue-next'
const props = withDefaults(defineProps<{ tone?: 'info' | 'success' | 'warning' | 'danger'; title?: string }>(), { tone: 'info' })
const map = {
  info: { icon: Info, cls: 'border-sky-200 bg-sky-50 text-sky-900' },
  success: { icon: CheckCircle2, cls: 'border-brand/25 bg-soft text-charcoal' },
  warning: { icon: AlertTriangle, cls: 'border-amber-200 bg-amber-50 text-amber-900' },
  danger: { icon: XCircle, cls: 'border-orange-200 bg-orange-50 text-orange-900' },
}
</script>

<template>
  <div :class="['flex gap-3 rounded-2xl border px-4 py-3 text-sm', map[props.tone].cls]" :role="props.tone === 'danger' ? 'alert' : 'status'">
    <component :is="map[props.tone].icon" class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
    <div class="min-w-0">
      <p v-if="title" class="font-semibold">{{ title }}</p>
      <div :class="title ? 'mt-0.5 opacity-90' : ''"><slot /></div>
    </div>
  </div>
</template>
