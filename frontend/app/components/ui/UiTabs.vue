<script setup lang="ts">
/** Accessible tab list with an animated indicator. Content is rendered by the parent via v-model. */
const model = defineModel<string>({ required: true })
const props = defineProps<{ tabs: { value: string; label: string }[]; size?: 'sm' | 'md' }>()

const refs = ref<HTMLElement[]>([])
const indicator = reactive({ left: 0, width: 0 })

function measure() {
  const i = props.tabs.findIndex(t => t.value === model.value)
  const el = refs.value[i]
  if (el) { indicator.left = el.offsetLeft; indicator.width = el.offsetWidth }
}

function onKey(e: KeyboardEvent, i: number) {
  const dir = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0
  if (!dir) return
  e.preventDefault()
  const next = (i + dir + props.tabs.length) % props.tabs.length
  model.value = props.tabs[next]!.value
  refs.value[next]?.focus()
}

watch(model, () => nextTick(measure))
onMounted(() => {
  measure()
  window.addEventListener('resize', measure, { passive: true })
})
onBeforeUnmount(() => window.removeEventListener('resize', measure))
</script>

<template>
  <div class="relative inline-flex max-w-full overflow-x-auto rounded-[14px] border border-line bg-canvas p-1" role="tablist">
    <span
      class="absolute top-1 bottom-1 rounded-[10px] bg-white shadow-sm transition-[left,width] duration-300 ease-[var(--ease-out-quart)]"
      :style="{ left: `${indicator.left}px`, width: `${indicator.width}px` }"
      aria-hidden="true"
    />
    <button
      v-for="(tab, i) in tabs" :key="tab.value"
      :ref="el => { if (el) refs[i] = el as HTMLElement }"
      type="button" role="tab" :aria-selected="model === tab.value" :tabindex="model === tab.value ? 0 : -1"
      :class="['relative z-10 rounded-[10px] font-semibold whitespace-nowrap transition-colors', size === 'sm' ? 'px-3 py-1.5 text-xs' : 'px-4 py-2 text-sm', model === tab.value ? 'text-ink' : 'text-muted hover:text-ink']"
      @click="model = tab.value" @keydown="onKey($event, i)"
    >
      {{ tab.label }}
    </button>
  </div>
</template>
