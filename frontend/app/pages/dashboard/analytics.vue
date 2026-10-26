<script setup lang="ts">
import { Download } from 'lucide-vue-next'
import type { AnalyticsReport, Campaign, Item } from '~/types/api'

definePageMeta({ layout: 'dashboard', middleware: 'auth' })
useSeoMeta({ title: 'Analytics', robots: 'noindex' })

const api = useApi()
const toast = useToast()
const iso = (d: Date) => d.toISOString().slice(0, 10)
const from = ref(iso(new Date(Date.now() - 29 * 86400000)))
const to = ref(iso(new Date()))
const campaignId = ref('')

const { data: campaigns } = await useAsyncData('campaigns-list', async () => (await api.get<{ data: Campaign[] }>('/campaigns')).data, { server: false, default: () => [] })
const { data: report, status, error, refresh } = await useAsyncData('analytics-overview', async () =>
  (await api.get<Item<AnalyticsReport>>('/analytics/overview', { from: from.value, to: to.value, ...(campaignId.value ? { campaign_id: campaignId.value } : {}) })).data,
{ server: false, watch: [from, to, campaignId] })

const exporting = ref(false)
async function exportCsv() {
  exporting.value = true
  try {
    const res = await fetch(`/api/v1/analytics/export?from=${from.value}&to=${to.value}`, { credentials: 'include' })
    if (!res.ok) throw new Error(res.status === 402 ? 'CSV export is available on paid plans.' : 'Export failed.')
    const url = URL.createObjectURL(await res.blob())
    Object.assign(document.createElement('a'), { href: url, download: `scans-${from.value}-${to.value}.csv` }).click()
    URL.revokeObjectURL(url)
  } catch (e) { toast.error((e as Error).message) } finally { exporting.value = false }
}
</script>

<template>
  <div>
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 class="text-2xl font-extrabold sm:text-3xl">Analytics</h1>
        <p class="mt-1 text-muted">Scans across all of your dynamic codes.</p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <select v-if="campaigns.length" v-model="campaignId" class="field-input !w-auto !py-1.5 text-sm" aria-label="Campaign">
          <option value="">All codes</option>
          <option v-for="c in campaigns" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
        </select>
        <DashboardDateRange v-model:from="from" v-model:to="to" />
        <UiButton size="sm" variant="secondary" :loading="exporting" @click="exportCsv"><Download class="size-4" /> CSV</UiButton>
      </div>
    </div>
    <div class="mt-6">
      <UiSpinner v-if="status === 'pending' && !report" />
      <UiAlert v-else-if="error" tone="danger">Could not load analytics. <button class="font-semibold underline" @click="refresh()">Retry</button></UiAlert>
      <DashboardAnalyticsPanels v-else-if="report" :report="report" />
    </div>
  </div>
</template>
