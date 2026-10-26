<script setup lang="ts">
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'

const page = defineModel<number>({ required: true })
defineProps<{ lastPage: number; total?: number; from?: number | null; to?: number | null }>()
</script>

<template>
  <nav v-if="lastPage > 1 || total" class="flex items-center justify-between gap-4 pt-4 text-sm" aria-label="Pagination">
    <p class="text-muted">
      <template v-if="total !== undefined && from">Showing {{ from }}–{{ to }} of {{ total.toLocaleString() }}</template>
    </p>
    <div v-if="lastPage > 1" class="flex items-center gap-2">
      <UiButton variant="secondary" size="sm" :disabled="page <= 1" aria-label="Previous page" @click="page--"><ChevronLeft class="size-4" /></UiButton>
      <span class="tabular-nums text-muted">{{ page }} / {{ lastPage }}</span>
      <UiButton variant="secondary" size="sm" :disabled="page >= lastPage" aria-label="Next page" @click="page++"><ChevronRight class="size-4" /></UiButton>
    </div>
  </nav>
</template>
