<script setup lang="ts">
import { CheckCircle2, Info, X, XCircle } from 'lucide-vue-next'

const { toasts, dismiss } = useToast()
const icons = { success: CheckCircle2, error: XCircle, info: Info }
</script>

<template>
  <div class="pointer-events-none fixed inset-x-0 bottom-4 z-[60] flex flex-col items-center gap-2 px-4 sm:right-4 sm:left-auto sm:items-end" aria-live="polite">
    <TransitionGroup
      enter-active-class="transition duration-300 ease-out" enter-from-class="translate-y-3 opacity-0"
      leave-active-class="transition duration-200 ease-in" leave-to-class="opacity-0 scale-95"
    >
      <div v-for="t in toasts" :key="t.id" class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-2xl border border-line bg-white px-4 py-3 text-sm shadow-[var(--shadow-lift)]">
        <component :is="icons[t.tone]" :class="['mt-0.5 size-4 shrink-0', t.tone === 'error' ? 'text-danger' : t.tone === 'info' ? 'text-sky-600' : 'text-brand']" aria-hidden="true" />
        <p class="flex-1 text-ink">{{ t.message }}</p>
        <button type="button" class="text-muted hover:text-ink" aria-label="Dismiss" @click="dismiss(t.id)"><X class="size-4" /></button>
      </div>
    </TransitionGroup>
  </div>
</template>
