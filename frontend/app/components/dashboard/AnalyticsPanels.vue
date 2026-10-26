<script setup lang="ts">
import { Info } from 'lucide-vue-next'
import type { AnalyticsReport } from '~/types/api'
import { countryName, formatNumber } from '~/utils/format'

const props = defineProps<{ report: AnalyticsReport & { definitions?: Record<string, string>; historyDays?: number } }>()
const points = computed(() => props.report.series.map(s => ({ date: s.date, value: s.scans, secondary: s.uniqueScans })))
const cap = (s: string) => s.charAt(0).toUpperCase() + s.slice(1)
const rows = (list: { value: string; scans: number }[], fmt: (v: string) => string = cap) => list.map(r => ({ label: fmt(r.value), value: r.scans }))
</script>

<template>
  <div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-3">
      <div class="card p-5">
        <p class="text-sm text-muted">Scans</p>
        <p class="mt-2 font-display text-3xl font-extrabold"><UiCountUp :value="report.totals.scans" /></p>
      </div>
      <div class="card p-5">
        <p class="flex items-center gap-1 text-sm text-muted">Unique scans <span class="text-xs">(estimate)</span></p>
        <p class="mt-2 font-display text-3xl font-extrabold"><UiCountUp :value="report.totals.uniqueScans" /></p>
      </div>
      <div class="card p-5">
        <p class="text-sm text-muted">Bots &amp; previews filtered</p>
        <p class="mt-2 font-display text-3xl font-extrabold"><UiCountUp :value="report.totals.botScansExcluded" /></p>
      </div>
    </div>

    <section class="card p-6">
      <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h2 class="font-bold">Scans over time</h2>
        <div class="flex items-center gap-4 text-xs text-muted">
          <span class="flex items-center gap-1.5"><span class="h-0.5 w-4 rounded bg-brand" /> Scans</span>
          <span class="flex items-center gap-1.5"><span class="h-0.5 w-4 border-t border-dashed border-charcoal/50" /> Unique (est.)</span>
        </div>
      </div>
      <ChartsAreaChart :points="points" :height="260" />
    </section>

    <div class="grid gap-6 lg:grid-cols-2 2xl:grid-cols-3">
      <section class="card p-6"><h2 class="mb-4 font-bold">Devices</h2><ChartsBarList :rows="rows(report.devices)" /></section>
      <section class="card p-6"><h2 class="mb-4 font-bold">Operating systems</h2><ChartsBarList :rows="rows(report.os, v => v)" /></section>
      <section class="card p-6"><h2 class="mb-4 font-bold">Browsers</h2><ChartsBarList :rows="rows(report.browsers, v => v)" /></section>
      <section class="card p-6"><h2 class="mb-4 font-bold">Countries</h2><ChartsBarList :rows="rows(report.countries, countryName)" :max="10" /></section>
      <section class="card p-6"><h2 class="mb-4 font-bold">Referrers</h2><ChartsBarList :rows="rows(report.referrers, v => (v === 'direct' ? 'Direct scan' : v))" /></section>
      <section class="card p-6 text-sm text-muted">
        <h2 class="mb-3 flex items-center gap-2 font-bold text-ink"><Info class="size-4 text-brand-700" /> How we count</h2>
        <p v-if="report.definitions">{{ report.definitions.scans }}</p>
        <p v-if="report.definitions" class="mt-2">{{ report.definitions.uniqueScans }}</p>
        <p v-if="report.historyDays && report.historyDays > 0" class="mt-2">Your plan shows the last {{ formatNumber(report.historyDays) }} days of history.</p>
      </section>
    </div>
  </div>
</template>
