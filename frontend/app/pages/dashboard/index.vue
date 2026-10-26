<script setup lang="ts">
import { Activity, Fingerprint, QrCode, Sparkles } from 'lucide-vue-next'
import type { AnalyticsBreakdownRow, Item } from '~/types/api'
import type { QrCodeRecord } from '~/types/qr'
import { QR_CATALOG } from '~/utils/qr/catalog'
import { formatNumber, formatRelative, limitLabel } from '~/utils/format'

definePageMeta({ layout: 'dashboard', middleware: 'auth' })
useSeoMeta({ title: 'Dashboard', robots: 'noindex' })

interface Summary {
  codes: { total: number; dynamic: number; static: number; active: number; paused: number }
  scans: { last30: number; unique30: number; previous30: number; series: { date: string; scans: number; uniqueScans: number }[]; devices: AnalyticsBreakdownRow[] }
  usage: Record<string, number>
  entitlements: Record<string, number | boolean>
  plan: string
  topCodes: QrCodeRecord[]
  recentCodes: QrCodeRecord[]
}

const api = useApi()
const { user } = useAuth()
const { data, status, error, refresh } = await useAsyncData('dashboard-summary', async () => (await api.get<Item<Summary>>('/dashboard/summary')).data, { server: false })
const points = computed(() => data.value?.scans.series.map(s => ({ date: s.date, value: s.scans, secondary: s.uniqueScans })) ?? [])
const firstName = computed(() => user.value?.name.split(' ')[0] ?? '')
</script>

<template>
  <div>
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 class="text-2xl font-extrabold sm:text-3xl">Welcome back{{ firstName ? `, ${firstName}` : '' }}</h1>
        <p class="mt-1 text-muted">Here’s how your codes performed over the last 30 days.</p>
      </div>
    </div>

    <UiAlert v-if="user && !user.emailVerified" tone="info" class="mt-6">
      Please verify your email address. <NuxtLink to="/verify-email" class="font-semibold underline">Resend link</NuxtLink>
    </UiAlert>

    <UiSpinner v-if="status === 'pending'" />
    <UiAlert v-else-if="error" tone="danger" class="mt-6">Could not load your dashboard. <button class="font-semibold underline" @click="refresh()">Retry</button></UiAlert>

    <template v-else-if="data">
      <UiEmpty v-if="data.codes.total === 0" class="mt-8" title="Create your first QR code" description="Start with a static code in seconds, or a dynamic one you can edit and track.">
        <UiButton to="/dashboard/qr-codes/new"><Sparkles class="size-4" /> Create a QR code</UiButton>
      </UiEmpty>

      <template v-else>
        <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <DashboardStatCard label="Scans (30 days)" :value="data.scans.last30" :previous="data.scans.previous30" :icon="Activity" />
          <DashboardStatCard label="Unique scans (est.)" :value="data.scans.unique30" :icon="Fingerprint" hint="Distinct visitors per day, estimated" />
          <DashboardStatCard label="Dynamic codes" :value="data.codes.dynamic" :icon="QrCode" :hint="`${data.usage.dynamic_codes} of ${limitLabel(Number(data.entitlements.dynamic_codes))} on ${data.plan}`" />
          <DashboardStatCard label="All codes" :value="data.codes.total" :icon="QrCode" :hint="`${data.codes.active} active · ${data.codes.paused} paused`" />
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-[2fr_1fr]">
          <section class="card p-6" aria-labelledby="scan-chart">
            <div class="mb-4 flex items-center justify-between">
              <h2 id="scan-chart" class="font-bold">Scans over time</h2>
              <NuxtLink to="/dashboard/analytics" class="text-sm font-semibold text-brand-700 hover:underline">Full analytics</NuxtLink>
            </div>
            <ChartsAreaChart :points="points" :height="240" />
            <p v-if="data.scans.last30 === 0" class="mt-2 text-center text-sm text-muted">No scans yet. Scans appear here as soon as someone scans a dynamic code.</p>
          </section>
          <section class="card p-6" aria-labelledby="devices">
            <h2 id="devices" class="mb-4 font-bold">Devices</h2>
            <ChartsBarList :rows="data.scans.devices.map(d => ({ label: d.value.charAt(0).toUpperCase() + d.value.slice(1), value: d.scans }))" empty="No scans in this period" />
          </section>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
          <section v-for="block in [{ title: 'Top codes', items: data.topCodes, metric: true }, { title: 'Recently created', items: data.recentCodes, metric: false }]" :key="block.title" class="card p-6">
            <div class="mb-4 flex items-center justify-between">
              <h2 class="font-bold">{{ block.title }}</h2>
              <NuxtLink to="/dashboard/qr-codes" class="text-sm font-semibold text-brand-700 hover:underline">View all</NuxtLink>
            </div>
            <ul class="divide-y divide-line">
              <li v-for="qr in block.items" :key="qr.id">
                <NuxtLink :to="`/dashboard/qr-codes/${qr.id}`" class="flex items-center gap-3 py-3 transition-colors hover:text-brand-700">
                  <DashboardQrThumb :qr="qr" :size="44" />
                  <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold">{{ qr.name }}</p>
                    <p class="text-xs text-muted">{{ QR_CATALOG[qr.type].label }} · {{ qr.mode }}</p>
                  </div>
                  <p v-if="block.metric" class="text-sm font-semibold tabular-nums">{{ formatNumber(qr.totalScans) }}</p>
                  <p v-else class="text-xs text-muted">{{ formatRelative(qr.createdAt) }}</p>
                </NuxtLink>
              </li>
            </ul>
          </section>
        </div>
      </template>
    </template>
  </div>
</template>
