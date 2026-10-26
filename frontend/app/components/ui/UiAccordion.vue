<script setup lang="ts">
import { Plus } from 'lucide-vue-next'

defineProps<{ items: { id: string | number; question: string; answer: string }[] }>()
const open = ref<string | number | null>(null)
const toggle = (id: string | number) => { open.value = open.value === id ? null : id }
</script>

<template>
  <div class="divide-y divide-line rounded-[var(--radius-card)] border border-line bg-white">
    <div v-for="item in items" :key="item.id">
      <h3>
        <button
          :id="`acc-btn-${item.id}`" type="button" :aria-expanded="open === item.id" :aria-controls="`acc-panel-${item.id}`"
          class="flex w-full items-center justify-between gap-4 px-5 py-5 text-left text-base font-semibold text-ink transition-colors hover:text-brand-700 sm:px-6"
          @click="toggle(item.id)"
        >
          {{ item.question }}
          <Plus :class="['size-5 shrink-0 text-muted transition-transform duration-300', open === item.id ? 'rotate-45 text-brand' : '']" aria-hidden="true" />
        </button>
      </h3>
      <div
        :id="`acc-panel-${item.id}`" role="region" :aria-labelledby="`acc-btn-${item.id}`"
        class="grid transition-[grid-template-rows] duration-300 ease-[var(--ease-out-quart)]"
        :style="{ gridTemplateRows: open === item.id ? '1fr' : '0fr' }"
      >
        <div class="overflow-hidden">
          <p class="px-5 pb-5 text-sm leading-relaxed text-muted sm:px-6">{{ item.answer }}</p>
        </div>
      </div>
    </div>
  </div>
</template>
