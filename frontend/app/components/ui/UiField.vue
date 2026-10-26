<script setup lang="ts">
defineProps<{ label?: string; error?: string; hint?: string; id?: string; required?: boolean }>()
</script>

<template>
  <div>
    <label v-if="label" :for="id" class="field-label">
      {{ label }}<span v-if="required" class="text-danger" aria-hidden="true"> *</span>
    </label>
    <slot />
    <Transition name="fade">
      <p v-if="error" :id="id ? `${id}-error` : undefined" class="field-error" role="alert">{{ error }}</p>
      <p v-else-if="hint" class="mt-1.5 text-xs text-muted">{{ hint }}</p>
    </Transition>
  </div>
</template>
