<script setup lang="ts">
import { Bot, CalendarRange, Download, Globe2 } from 'lucide-vue-next'

// Illustrative series for the marketing preview only. It is labelled as an example
// in the UI; real dashboards always load data from the API.
const today = new Date()
const points = Array.from({ length: 30 }, (_, i) => {
  const d = new Date(today)
  d.setDate(d.getDate() - (29 - i))
  const v = Math.round(60 + 30 * Math.sin(i / 3.2) + i * 2.4 + (i % 7 === 5 ? 28 : 0))
  return { date: d.toISOString().slice(0, 10), value: v, secondary: Math.round(v * 0.72) }
})
const devices = [{ label: 'Mobile', value: 72 }, { label: 'Tablet', value: 9 }, { label: 'Desktop', value: 19 }]

const items = [
  { icon: CalendarRange, title: 'Scans over time', body: 'Daily totals and unique-visitor estimates for any date range.' },
  { icon: Globe2, title: 'Where and how', body: 'Country, device, operating system, browser and referrer breakdowns.' },
  { icon: Bot, title: 'Bots filtered out', body: 'Link previews and crawlers are detected and excluded from totals.' },
  { icon: Download, title: 'Export anything', body: 'Download daily data as CSV for your own reporting.' },
]
</script>

<template>
  <section class="section bg-white" aria-labelledby="analytics-title">
    <div class="container-page">
      <div class="grid items-end gap-6 lg:grid-cols-2">
        <div>
          <AppReveal><p class="eyebrow">Scan analytics</p></AppReveal>
          <AppReveal :delay="0.05"><h2 id="analytics-title" class="section-title mt-4">Know which print is pulling its weight</h2></AppReveal>
        </div>
        <AppReveal :delay="0.1"><p class="section-lead lg:mt-0">Every dynamic scan is aggregated into daily reports, so dashboards stay fast even when a code is scanned millions of times.</p></AppReveal>
      </div>

      <AppReveal :delay="0.1" class="mt-12">
        <div class="card overflow-hidden">
          <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-6 py-4">
            <div class="flex items-center gap-3">
              <h3 class="font-bold">Spring menu · last 30 days</h3>
              <UiBadge>Example data</UiBadge>
            </div>
            <div class="flex items-center gap-4 text-xs text-muted">
              <span class="flex items-center gap-1.5"><span class="h-0.5 w-4 rounded bg-brand" /> Scans</span>
              <span class="flex items-center gap-1.5"><span class="h-0.5 w-4 border-t border-dashed border-charcoal/50" /> Unique (est.)</span>
            </div>
          </div>
          <div class="grid gap-6 p-6 lg:grid-cols-[2fr_1fr]">
            <ChartsAreaChart :points="points" :height="240" />
            <div>
              <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-muted">Devices</p>
              <ChartsBarList :rows="devices" />
            </div>
          </div>
        </div>
      </AppReveal>

      <div class="mt-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
        <AppReveal v-for="(item, i) in items" :key="item.title" :delay="i * 0.05">
          <component :is="item.icon" class="size-5 text-brand" aria-hidden="true" />
          <h3 class="mt-3 font-bold">{{ item.title }}</h3>
          <p class="mt-1 text-sm text-muted">{{ item.body }}</p>
        </AppReveal>
      </div>
    </div>
  </section>
</template>
