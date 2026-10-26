<script setup lang="ts">
const model = defineModel<boolean>({ default: false })
defineProps<{ label: string; description?: string; disabled?: boolean }>()
const id = useId()
</script>

<template>
  <div class="flex items-start justify-between gap-4">
    <div>
      <label :for="id" class="text-sm font-medium text-ink">{{ label }}</label>
      <p v-if="description" class="text-xs text-muted">{{ description }}</p>
    </div>
    <button
      :id="id" type="button" role="switch" :aria-checked="model" :disabled="disabled"
      :class="['relative h-6 w-11 shrink-0 rounded-full transition-colors duration-200 disabled:opacity-50', model ? 'bg-brand' : 'bg-line-strong']"
      @click="model = !model"
    >
      <span :class="['absolute top-0.5 left-0.5 size-5 rounded-full bg-white shadow transition-transform duration-200 ease-out', model ? 'translate-x-5' : 'translate-x-0']" />
    </button>
  </div>
</template>
