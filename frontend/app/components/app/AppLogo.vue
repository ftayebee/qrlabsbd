<script setup lang="ts">
import { brand } from '~/brand.config'

withDefaults(defineProps<{ inverted?: boolean; compact?: boolean }>(), { inverted: false, compact: false })
</script>

<template>
  <span class="inline-flex items-center gap-2.5">
    <svg viewBox="0 0 32 32" class="size-8 shrink-0" aria-hidden="true">
      <rect width="32" height="32" rx="9" :fill="inverted ? '#FFFFFF' : brand.colors.charcoal" />
      <rect x="7" y="7" width="7.5" height="7.5" rx="2" :fill="brand.colors.primary" />
      <rect x="17.5" y="7" width="7.5" height="7.5" rx="2" :fill="inverted ? brand.colors.charcoal : '#FFFFFF'" />
      <rect x="7" y="17.5" width="7.5" height="7.5" rx="2" :fill="inverted ? brand.colors.charcoal : '#FFFFFF'" />
      <circle cx="21.25" cy="21.25" r="3.75" :fill="brand.colors.primary" />
    </svg>
    <span v-if="!compact" :class="['font-display text-xl font-extrabold tracking-tight', inverted ? 'text-white' : 'text-ink']">{{ brand.name }}</span>
  </span>
</template>
