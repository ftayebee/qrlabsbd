<script setup lang="ts">
import { Loader2 } from 'lucide-vue-next'
import type { RouteLocationRaw } from 'vue-router'

const props = withDefaults(defineProps<{
  variant?: 'primary' | 'secondary' | 'ghost' | 'dark' | 'danger' | 'soft'
  size?: 'sm' | 'md' | 'lg'
  to?: RouteLocationRaw
  href?: string
  type?: 'button' | 'submit' | 'reset'
  loading?: boolean
  disabled?: boolean
  block?: boolean
}>(), { variant: 'primary', size: 'md', type: 'button' })

const variants = {
  // Charcoal on brand green meets WCAG AA (about 5.9:1); white text would not.
  primary: 'bg-brand text-charcoal shadow-[0_1px_0_rgb(255_255_255/0.3)_inset,0_6px_16px_-6px_rgb(29_180_90/0.55)] hover:bg-brand-400 active:bg-brand',
  secondary: 'bg-white text-ink border border-line-strong hover:border-ink/30 hover:bg-canvas',
  ghost: 'text-ink hover:bg-ink/5',
  dark: 'bg-charcoal text-white hover:bg-ink',
  danger: 'bg-danger text-white hover:bg-danger/90',
  soft: 'bg-soft text-brand-700 hover:bg-brand/15',
}
const sizes = {
  sm: 'h-9 px-3.5 text-sm gap-1.5 rounded-[10px]',
  md: 'h-11 px-5 text-sm gap-2 rounded-[var(--radius-control)]',
  lg: 'h-13 px-7 text-base gap-2.5 rounded-[14px]',
}

const classes = computed(() => [
  'inline-flex items-center justify-center font-semibold whitespace-nowrap select-none',
  'transition-[background-color,border-color,transform,box-shadow] duration-150 ease-out active:scale-[0.98]',
  'disabled:pointer-events-none disabled:opacity-55',
  variants[props.variant], sizes[props.size], props.block ? 'w-full' : '',
])

const component = computed(() => (props.to ? resolveComponent('NuxtLink') : props.href ? 'a' : 'button'))
</script>

<template>
  <component
    :is="component"
    :to="to"
    :href="href"
    :type="!to && !href ? type : undefined"
    :disabled="!to && !href ? disabled || loading : undefined"
    :aria-busy="loading || undefined"
    :class="classes"
  >
    <Loader2 v-if="loading" class="size-4 animate-spin" aria-hidden="true" />
    <slot />
  </component>
</template>
