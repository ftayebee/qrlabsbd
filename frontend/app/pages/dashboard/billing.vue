<script setup lang="ts">
import { ExternalLink, FileText } from 'lucide-vue-next'
import type { Invoice, Paginated, Plan, Subscription } from '~/types/api'
import { formatBytes, formatDate, formatMoney, limitLabel } from '~/utils/format'

definePageMeta({ layout: 'dashboard', middleware: 'auth' })
useSeoMeta({ title: 'Billing', robots: 'noindex' })

const api = useApi()
const toast = useToast()
const { confirm } = useConfirm()
const { usage, refresh: refreshUsage } = useUsage()

const { data: sub, refresh } = await useAsyncData('billing-sub', async () => (await api.get<{ data: { current: Subscription | null; history: Subscription[] } }>('/billing/subscription')).data, { server: false })
const { data: invoices } = await useAsyncData('billing-invoices', () => api.get<Paginated<Invoice>>('/billing/invoices'), { server: false })
const { data: plans } = usePlans()
onMounted(() => refreshUsage().catch(() => {}))

const current = computed(() => sub.value?.current ?? null)
const interval = ref<'month' | 'year'>('month')
watch(current, (c) => { if (c?.price) interval.value = c.price.interval }, { immediate: true })
const currency = computed(() => current.value?.price?.currency ?? plans.value.flatMap(p => p.prices)[0]?.currency ?? 'USD')
const priceFor = (p: Plan) => p.prices.find(x => x.interval === interval.value && x.currency === currency.value)

const busy = ref<string | null>(null)
async function changeTo(plan: Plan) {
  const price = priceFor(plan)
  if (plan.isCustom) return navigateTo({ path: '/contact', query: { topic: 'sales', plan: plan.slug } })
  if (!price) return
  if (!current.value || current.value.provider === 'manual') {
    busy.value = `plan-${plan.id}`
    try { window.location.assign((await api.post<{ data: { url: string } }>('/billing/checkout', { price_id: price.id })).data.url) } catch (e) { toast.error((e as Error).message) } finally { busy.value = null }
    return
  }
  const ok = await confirm({ title: `Switch to ${plan.name}?`, message: 'The change applies now. Your payment provider prorates the difference on your next invoice.', confirmLabel: 'Switch plan' })
  if (!ok) return
  busy.value = `plan-${plan.id}`
  try {
    await api.post('/billing/change-plan', { price_id: price.id })
    toast.success('Plan updated.')
    await Promise.all([refresh(), refreshUsage()])
  } catch (e) { toast.error((e as Error).message) } finally { busy.value = null }
}

async function cancel() {
  const reason = await confirm({ title: 'Cancel subscription?', message: 'You keep your plan until the end of the billing period. Dynamic codes keep working afterwards.', confirmLabel: 'Cancel at period end', tone: 'danger', requireReason: true })
  if (!reason) return
  busy.value = 'cancel'
  try {
    await api.post('/billing/cancel', { reason: typeof reason === 'string' ? reason : undefined })
    toast.success('Your subscription will end at the end of the billing period.')
    await refresh()
  } catch (e) { toast.error((e as Error).message) } finally { busy.value = null }
}

async function resume() {
  busy.value = 'resume'
  try { await api.post('/billing/resume'); toast.success('Subscription resumed.'); await refresh() } catch (e) { toast.error((e as Error).message) } finally { busy.value = null }
}

async function portal() {
  busy.value = 'portal'
  try { window.location.assign((await api.get<{ data: { url: string } }>('/billing/portal')).data.url) } catch (e) { toast.error((e as Error).message) } finally { busy.value = null }
}

const meters = computed(() => {
  const u = usage.value
  if (!u) return []
  return [
    { label: 'Dynamic codes', used: u.usage.dynamic_codes ?? 0, limit: Number(u.entitlements.dynamic_codes) },
    { label: 'Tracked scans this month', used: u.usage.scans_per_month ?? 0, limit: Number(u.entitlements.scans_per_month) },
    { label: 'Saved static codes', used: u.usage.saved_static_codes ?? 0, limit: Number(u.entitlements.saved_static_codes) },
    { label: 'Storage', used: u.usage.storage_bytes ?? 0, limit: Number(u.entitlements.storage_mb) * 1048576, bytes: true },
  ]
})
</script>

<template>
  <div>
    <h1 class="text-2xl font-extrabold sm:text-3xl">Billing</h1>
    <p class="mt-1 text-muted">Manage your plan, payment details and invoices.</p>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1.3fr_1fr]">
      <section class="card p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <p class="text-sm text-muted">Current plan</p>
            <p class="mt-1 font-display text-3xl font-extrabold">{{ usage?.plan.name ?? '…' }}</p>
            <div v-if="current" class="mt-2 flex flex-wrap items-center gap-2 text-sm text-muted">
              <DashboardStatusBadge :status="current.status" />
              <span v-if="current.price">{{ formatMoney(current.price.amount, current.price.currency) }} / {{ current.price.interval }}</span>
              <span v-if="current.trialEndsAt && current.status === 'trialing'">· Trial ends {{ formatDate(current.trialEndsAt) }}</span>
              <span v-else-if="current.cancelAtPeriodEnd">· Ends {{ formatDate(current.currentPeriodEnd) }}</span>
              <span v-else-if="current.currentPeriodEnd">· Renews {{ formatDate(current.currentPeriodEnd) }}</span>
            </div>
          </div>
          <div class="flex flex-wrap gap-2">
            <UiButton v-if="current && current.provider !== 'manual'" variant="secondary" :loading="busy === 'portal'" @click="portal">Payment methods <ExternalLink class="size-4" /></UiButton>
            <UiButton v-if="current?.cancelAtPeriodEnd" :loading="busy === 'resume'" @click="resume">Resume</UiButton>
            <UiButton v-else-if="current && current.provider !== 'manual'" variant="ghost" class="!text-danger" :loading="busy === 'cancel'" @click="cancel">Cancel</UiButton>
          </div>
        </div>
        <UiAlert v-if="current?.status === 'past_due'" tone="warning" class="mt-5" title="Payment failed">We’ll retry automatically. Update your payment method to avoid losing premium features. Your codes keep working.</UiAlert>
        <UiAlert v-if="current?.provider === 'manual'" tone="info" class="mt-5">This plan is managed by our team{{ current.endsAt ? ` until ${formatDate(current.endsAt)}` : '' }}. Contact support to make changes.</UiAlert>

        <div class="mt-8 flex items-center justify-between gap-3">
          <h2 class="font-bold">Change plan</h2>
          <UiTabs v-model="interval" size="sm" :tabs="[{ value: 'month', label: 'Monthly' }, { value: 'year', label: 'Yearly' }]" />
        </div>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
          <div v-for="p in plans" :key="p.id" :class="['rounded-2xl border p-4', usage?.plan.id === p.id ? 'border-brand bg-soft/50' : 'border-line']">
            <div class="flex items-center justify-between">
              <p class="font-bold">{{ p.name }}</p>
              <p v-if="priceFor(p)" class="text-sm font-semibold">{{ formatMoney(priceFor(p)!.effectiveAmount, currency) }}<span class="font-normal text-muted">/{{ interval === 'year' ? 'yr' : 'mo' }}</span></p>
              <p v-else-if="p.isCustom" class="text-sm font-semibold">Custom</p>
            </div>
            <p class="mt-1 text-xs text-muted">{{ p.description }}</p>
            <UiButton v-if="usage?.plan.id === p.id" size="sm" variant="secondary" class="mt-3" disabled>Current plan</UiButton>
            <UiButton v-else-if="p.isCustom || priceFor(p)" size="sm" class="mt-3" :variant="p.highlight ? 'primary' : 'secondary'" :loading="busy === `plan-${p.id}`" @click="changeTo(p)">
              {{ p.isCustom ? 'Contact sales' : current ? 'Switch' : p.trialDays ? `Start ${p.trialDays}-day trial` : 'Upgrade' }}
            </UiButton>
            <p v-else-if="current" class="mt-3 text-xs text-muted">Cancel your subscription to return to Free at the end of the period.</p>
          </div>
        </div>
      </section>

      <section class="card p-6">
        <h2 class="font-bold">Usage</h2>
        <ul class="mt-5 space-y-5">
          <li v-for="m in meters" :key="m.label">
            <div class="flex justify-between text-sm"><span>{{ m.label }}</span><span class="tabular-nums text-muted">{{ m.bytes ? formatBytes(m.used) : m.used.toLocaleString() }} / {{ m.limit < 0 ? 'Unlimited' : m.bytes ? formatBytes(m.limit) : limitLabel(m.limit) }}</span></div>
            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-canvas"><div :class="['h-full rounded-full transition-[width] duration-700', m.limit > 0 && m.used / m.limit >= 0.9 ? 'bg-warning' : 'bg-brand']" :style="{ width: m.limit < 0 ? '6%' : `${Math.min(100, m.limit ? (m.used / m.limit) * 100 : 0)}%` }" /></div>
          </li>
        </ul>
        <p class="mt-6 text-xs text-muted">Usage limits are enforced by our servers. Scans are never blocked when you exceed the tracked-scan allowance; we will contact you about upgrading.</p>
      </section>
    </div>

    <section class="card mt-6 overflow-x-auto">
      <div class="px-6 pt-5"><h2 class="font-bold">Invoices</h2></div>
      <table v-if="invoices?.data.length" class="table-base mt-3 min-w-[640px]">
        <thead><tr><th>Number</th><th>Date</th><th>Status</th><th class="text-right">Total</th><th class="w-24"><span class="sr-only">Download</span></th></tr></thead>
        <tbody>
          <tr v-for="inv in invoices.data" :key="inv.id">
            <td class="font-medium">{{ inv.number }}</td>
            <td class="text-muted">{{ formatDate(inv.issuedAt) }}</td>
            <td><DashboardStatusBadge :status="inv.status" /></td>
            <td class="text-right tabular-nums">{{ formatMoney(inv.total, inv.currency) }}</td>
            <td><a v-if="inv.pdfUrl || inv.hostedUrl" :href="(inv.pdfUrl || inv.hostedUrl)!" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-sm font-semibold text-brand-700 hover:underline"><FileText class="size-4" /> PDF</a></td>
          </tr>
        </tbody>
      </table>
      <p v-else class="px-6 pt-3 pb-6 text-sm text-muted">No invoices yet.</p>
    </section>

    <section v-if="sub?.history.length" class="card mt-6 overflow-x-auto">
      <div class="px-6 pt-5"><h2 class="font-bold">Subscription history</h2></div>
      <table class="table-base mt-3 min-w-[560px]">
        <thead><tr><th>Plan</th><th>Status</th><th>Started</th><th>Ended</th></tr></thead>
        <tbody>
          <tr v-for="s in sub.history" :key="s.id"><td class="font-medium">{{ s.plan.name }}</td><td><DashboardStatusBadge :status="s.status" /></td><td class="text-muted">{{ formatDate(s.createdAt) }}</td><td class="text-muted">{{ formatDate(s.endsAt) }}</td></tr>
        </tbody>
      </table>
    </section>
  </div>
</template>
