<script setup lang="ts">
const props = withDefaults(defineProps<{ tone?: 'neutral' | 'green' | 'amber' | 'red' | 'blue' | 'dark' }>(), { tone: 'neutral' })
const tones = {
  neutral: 'bg-canvas text-muted border-line',
  green: 'bg-soft text-brand-700 border-brand/20',
  amber: 'bg-amber-50 text-amber-800 border-amber-200',
  red: 'bg-orange-50 text-orange-800 border-orange-200',
  blue: 'bg-sky-50 text-sky-800 border-sky-200',
  dark: 'bg-charcoal text-white border-charcoal',
}
</script>

<template>
  <span :class="['inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap', tones[props.tone]]">
    <slot />
  </span>
</template>
