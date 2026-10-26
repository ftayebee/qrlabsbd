<script setup lang="ts">
import { Loader2 } from 'lucide-vue-next'
defineProps<{ label?: string }>()
</script>

<template>
  <div class="flex items-center justify-center gap-2 py-10 text-sm text-muted" role="status">
    <Loader2 class="size-5 animate-spin text-brand" aria-hidden="true" />
    <span>{{ label ?? 'Loading…' }}</span>
  </div>
</template>
