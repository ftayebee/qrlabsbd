<script setup lang="ts">
/** Preset or custom date range. Emits ISO dates (YYYY-MM-DD). */
const from = defineModel<string>('from', { required: true })
const to = defineModel<string>('to', { required: true })

const iso = (d: Date) => d.toISOString().slice(0, 10)
const presets = [{ days: 7, label: '7 days' }, { days: 30, label: '30 days' }, { days: 90, label: '90 days' }, { days: 365, label: '12 months' }]
const active = computed(() => presets.find(p => from.value === iso(new Date(Date.now() - (p.days - 1) * 86400000)) && to.value === iso(new Date()))?.days ?? 0)

function pick(days: number) {
  to.value = iso(new Date())
  from.value = iso(new Date(Date.now() - (days - 1) * 86400000))
}
</script>

<template>
  <div class="flex flex-wrap items-center gap-2">
    <div class="flex rounded-xl border border-line bg-white p-0.5" role="group" aria-label="Date range">
      <button
        v-for="p in presets" :key="p.days" type="button" :aria-pressed="active === p.days"
        :class="['rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors', active === p.days ? 'bg-charcoal text-white' : 'text-muted hover:text-ink']"
        @click="pick(p.days)"
      >{{ p.label }}</button>
    </div>
    <label class="sr-only" for="dr-from">From</label>
    <input id="dr-from" v-model="from" type="date" :max="to" class="field-input !w-auto !py-1.5 text-xs">
    <span class="text-xs text-muted">to</span>
    <label class="sr-only" for="dr-to">To</label>
    <input id="dr-to" v-model="to" type="date" :min="from" class="field-input !w-auto !py-1.5 text-xs">
  </div>
</template>
