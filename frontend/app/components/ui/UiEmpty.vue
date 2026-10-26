<script setup lang="ts">
defineProps<{ title: string; description?: string }>()
</script>

<template>
  <div class="flex flex-col items-center justify-center rounded-[var(--radius-card)] border border-dashed border-line-strong bg-white/60 px-6 py-14 text-center">
    <div class="mb-4 grid size-12 place-items-center rounded-2xl bg-soft text-brand-700">
      <slot name="icon"><svg viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1.5" /><rect x="14" y="3" width="7" height="7" rx="1.5" /><rect x="3" y="14" width="7" height="7" rx="1.5" /><path d="M14 14h3v3h-3zM20 20h1M17 20h1" /></svg></slot>
    </div>
    <h3 class="text-base font-bold">{{ title }}</h3>
    <p v-if="description" class="mt-1 max-w-sm text-sm text-muted">{{ description }}</p>
    <div v-if="$slots.default" class="mt-5"><slot /></div>
  </div>
</template>
