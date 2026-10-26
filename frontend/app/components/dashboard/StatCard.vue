<script setup lang="ts">
import { TrendingDown, TrendingUp } from 'lucide-vue-next'
import type { Component } from 'vue'

const props = defineProps<{ label: string; value: number; icon?: Component; hint?: string; previous?: number; format?: (n: number) => string }>()
const delta = computed(() => {
  if (props.previous === undefined) return null
  if (props.previous === 0) return props.value > 0 ? 100 : 0
  return Math.round(((props.value - props.previous) / props.previous) * 100)
})
</script>

<template>
  <div class="card p-5">
    <div class="flex items-center justify-between">
      <p class="text-sm font-medium text-muted">{{ label }}</p>
      <span v-if="icon" class="grid size-8 place-items-center rounded-lg bg-soft text-brand-700"><component :is="icon" class="size-4" /></span>
    </div>
    <p class="mt-3 font-display text-3xl font-extrabold tracking-tight"><UiCountUp :value="value" :format="format" /></p>
    <p class="mt-1 flex items-center gap-1.5 text-xs text-muted">
      <template v-if="delta !== null">
        <span :class="['inline-flex items-center gap-0.5 font-semibold', delta >= 0 ? 'text-brand-700' : 'text-danger']">
          <TrendingUp v-if="delta >= 0" class="size-3.5" /><TrendingDown v-else class="size-3.5" />{{ Math.abs(delta) }}%
        </span>
        vs previous 30 days
      </template>
      <template v-else-if="hint">{{ hint }}</template>
    </p>
  </div>
</template>
