<script setup lang="ts">
import { AlertTriangle, CreditCard, QrCode, Users, Wallet } from 'lucide-vue-next'
import type { Item } from '~/types/api'
import { formatMoney, formatNumber } from '~/utils/format'

definePageMeta({ layout: 'admin', middleware: 'admin' })
useSeoMeta({ title: 'Admin overview', robots: 'noindex' })

interface Overview {
  range: { days: number; since: string }
  customers: { total: number; new: number; suspended: number }
  subscriptions: { active: number; trialing: number; pastDue: number; new: number; cancellations: number; byPlan: { plan: string; count: number }[] }
  revenue: { currency: string; gross: number; refunded: number; net: number; payments: number }[]
  revenueSeries: { date: string; currency: string; net: number }[]
  refunds: number
  failedPayments: number
  qr: { total: number; created: number; dynamic: number; scans: number; scanSeries: { date: string; scans: number }[] }
  attention: { openReports: number; failedWebhooks: number }
}

const api = useApi()
const range = ref('30')
const days = computed(() => Number(range.value))
const { data, status, error } = await useAsyncData('admin-overview', async () => (await api.get<Item<Overview>>('/admin/overview', { days: days.value })).data, { server: false, watch: [days] })

const primaryCurrency = computed(() => data.value?.revenue[0]?.currency)
const revenuePoints = computed(() => {
  if (!data.value || !primaryCurrency.value) return []
  const map = new Map(data.value.revenueSeries.filter(r => r.currency === primaryCurrency.value).map(r => [r.date, r.net]))
  const out = []
  const start = new Date(data.value.range.since)
  for (let i = 0; i < days.value; i++) {
    const d = new Date(start.getTime() + i * 86400000).toISOString().slice(0, 10)
    out.push({ date: d, value: map.get(d) ?? 0 })
  }
  return out
})
const scanPoints = computed(() => data.value?.qr.scanSeries.map(s => ({ date: s.date, value: s.scans })) ?? [])
</script>

<template>
  <div>
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 class="text-2xl font-extrabold sm:text-3xl">Overview</h1>
        <p class="mt-1 text-muted">Revenue figures use reconciled payments confirmed by the payment provider.</p>
      </div>
      <UiTabs v-model="range" :tabs="[{ value: '7', label: '7d' }, { value: '30', label: '30d' }, { value: '90', label: '90d' }, { value: '365', label: '1y' }]" size="sm" />
    </div>

    <UiSpinner v-if="status === 'pending' && !data" />
    <UiAlert v-else-if="error" tone="danger" class="mt-6">{{ error.message }}</UiAlert>
    <template v-else-if="data">
      <div v-if="data.attention.openReports || data.attention.failedWebhooks || data.subscriptions.pastDue" class="mt-6 flex flex-wrap gap-3">
        <NuxtLink v-if="data.attention.openReports" to="/admin/moderation" class="flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-900"><AlertTriangle class="size-4" /> {{ data.attention.openReports }} open abuse reports</NuxtLink>
        <NuxtLink v-if="data.attention.failedWebhooks" to="/admin/payments?tab=webhooks" class="flex items-center gap-2 rounded-xl border border-orange-200 bg-orange-50 px-4 py-2 text-sm font-semibold text-orange-900"><AlertTriangle class="size-4" /> {{ data.attention.failedWebhooks }} failed webhooks</NuxtLink>
        <NuxtLink v-if="data.subscriptions.pastDue" to="/admin/subscriptions?status=past_due" class="flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-900"><AlertTriangle class="size-4" /> {{ data.subscriptions.pastDue }} past-due subscriptions</NuxtLink>
      </div>

      <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <DashboardStatCard label="Customers" :value="data.customers.total" :icon="Users" :hint="`${data.customers.new} new in period`" />
        <DashboardStatCard label="Active subscriptions" :value="data.subscriptions.active" :icon="Wallet" :hint="`${data.subscriptions.new} new · ${data.subscriptions.cancellations} cancellations`" />
        <div class="card p-5">
          <div class="flex items-center justify-between"><p class="text-sm font-medium text-muted">Net revenue</p><span class="grid size-8 place-items-center rounded-lg bg-soft text-brand-700"><CreditCard class="size-4" /></span></div>
          <p v-if="!data.revenue.length" class="mt-3 font-display text-3xl font-extrabold">—</p>
          <p v-for="r in data.revenue" :key="r.currency" class="mt-3 font-display text-2xl font-extrabold">{{ formatMoney(r.net, r.currency) }}</p>
          <p class="mt-1 text-xs text-muted">{{ data.failedPayments }} failed payments · {{ data.refunds }} refunds</p>
        </div>
        <DashboardStatCard label="QR codes" :value="data.qr.total" :icon="QrCode" :hint="`${data.qr.created} created · ${formatNumber(data.qr.scans)} scans`" />
      </div>

      <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="card p-6">
          <h2 class="mb-4 font-bold">Net revenue <span v-if="primaryCurrency" class="font-normal text-muted">({{ primaryCurrency }})</span></h2>
          <ChartsAreaChart v-if="revenuePoints.length" :points="revenuePoints.map(p => ({ ...p, value: p.value / 100 }))" label="Revenue" />
          <p v-else class="py-10 text-center text-sm text-muted">No reconciled payments in this period.</p>
        </section>
        <section class="card p-6">
          <h2 class="mb-4 font-bold">Scan activity</h2>
          <ChartsAreaChart :points="scanPoints" />
        </section>
      </div>
      <section class="card mt-6 p-6">
        <h2 class="mb-4 font-bold">Active subscriptions by plan</h2>
        <ChartsBarList :rows="data.subscriptions.byPlan.map(p => ({ label: p.plan, value: p.count }))" empty="No active subscriptions" />
      </section>
    </template>
  </div>
</template>
