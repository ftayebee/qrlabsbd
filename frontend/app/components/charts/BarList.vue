<script setup lang="ts">
import { formatNumber, pct } from '~/utils/format'

const props = withDefaults(defineProps<{
  rows: { label: string; value: number }[]
  empty?: string
  max?: number
}>(), { empty: 'No data yet', max: 8 })

const total = computed(() => props.rows.reduce((s, r) => s + r.value, 0))
const top = computed(() => props.rows.slice(0, props.max))
const peak = computed(() => Math.max(1, ...top.value.map(r => r.value)))
const ready = ref(false)
onMounted(() => requestAnimationFrame(() => (ready.value = true)))
</script>

<template>
  <ul v-if="top.length" class="space-y-2.5">
    <li v-for="r in top" :key="r.label" class="relative">
      <div class="absolute inset-y-0 left-0 rounded-lg bg-soft transition-[width] duration-700 ease-[var(--ease-out-quart)]" :style="{ width: ready ? `${(r.value / peak) * 100}%` : '0%' }" aria-hidden="true" />
      <div class="relative flex items-center justify-between gap-3 px-3 py-2 text-sm">
        <span class="truncate font-medium text-ink">{{ r.label }}</span>
        <span class="shrink-0 tabular-nums text-muted">{{ formatNumber(r.value) }} <span class="text-xs">· {{ pct(r.value, total) }}%</span></span>
      </div>
    </li>
  </ul>
  <p v-else class="py-6 text-center text-sm text-muted">{{ empty }}</p>
</template>
