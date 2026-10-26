<script setup lang="ts">
import type { Invoice, Paginated } from '~/types/api'
import { formatDate, formatDateTime, formatMoney, minorFactor } from '~/utils/format'

definePageMeta({ layout: 'admin', middleware: 'admin' })
useSeoMeta({ title: 'Payments', robots: 'noindex' })

interface PaymentRow { id: number; provider: string; providerPaymentId: string; status: string; currency: string; amount: number; refundedAmount: number; failureReason: string | null; attempt: number; paidAt: string | null; invoiceNumber?: string | null; user?: { id: number; name: string; email: string }; createdAt: string }
interface WebhookRow { id: number; provider: string; event_id: string; type: string; status: string; attempts: number; error: string | null; processed_at: string | null; created_at: string }

const route = useRoute()
const tab = ref(String(route.query.tab ?? 'payments'))
const api = useApi()
const toast = useToast()
const { confirm } = useConfirm()
const { can, user } = useAuth()
const canManage = computed(() => can('payments.manage') || user.value?.roles.includes('super-admin'))

const payments = useAdminList<PaymentRow>('admin-payments', '/admin/payments', { status: '' })
const { data: invoices } = await useAsyncData('admin-invoices', () => api.get<Paginated<Invoice>>('/admin/invoices'), { server: false })
const whStatus = ref('')
const { data: webhooks, refresh: refreshWebhooks } = await useAsyncData('admin-webhooks', () => api.get<Paginated<WebhookRow>>('/admin/webhook-events', whStatus.value ? { status: whStatus.value } : {}), { server: false, watch: [whStatus] })

const refundFor = ref<PaymentRow | null>(null)
const refundAmount = ref('')
async function refund() {
  const p = refundFor.value
  if (!p) return
  const reason = await confirm({ title: 'Issue refund?', message: `${refundAmount.value} ${p.currency} will be refunded through ${p.provider}.`, confirmLabel: 'Refund', tone: 'danger', requireReason: true })
  if (!reason) return
  try {
    await api.post(`/admin/payments/${p.id}/refund`, { amount: Math.round(Number(refundAmount.value) * minorFactor(p.currency)), reason })
    toast.success('Refund requested.')
    refundFor.value = null
    await payments.refresh()
  } catch (e) { toast.error((e as Error).message) }
}
async function retry(w: WebhookRow) {
  try { await api.post(`/admin/webhook-events/${w.id}/retry`); toast.success('Retry queued.'); await refreshWebhooks() } catch (e) { toast.error((e as Error).message) }
}
</script>

<template>
  <div>
    <h1 class="text-2xl font-extrabold sm:text-3xl">Payments</h1>
    <UiTabs v-model="tab" class="mt-6" :tabs="[{ value: 'payments', label: 'Payments' }, { value: 'invoices', label: 'Invoices' }, { value: 'webhooks', label: 'Webhooks' }]" />

    <template v-if="tab === 'payments'">
      <div class="card mt-4 flex flex-wrap gap-3 p-3">
        <input v-model="payments.search.value" type="search" class="field-input flex-1" placeholder="Provider payment ID or customer email" aria-label="Search payments">
        <select v-model="payments.filters.status" class="field-input !w-auto" aria-label="Status"><option value="">All statuses</option><option v-for="s in ['succeeded', 'failed', 'pending', 'refunded', 'partially_refunded']" :key="s" :value="s">{{ s.replace('_', ' ') }}</option></select>
      </div>
      <div v-if="payments.data.value" class="card mt-4 overflow-x-auto">
        <table class="table-base min-w-[900px]">
          <thead><tr><th>Date</th><th>Customer</th><th>Status</th><th class="text-right">Amount</th><th class="text-right">Refunded</th><th>Reference</th><th /></tr></thead>
          <tbody>
            <tr v-for="p in payments.data.value.data" :key="p.id">
              <td class="text-muted">{{ formatDateTime(p.paidAt ?? p.createdAt) }}</td>
              <td><NuxtLink v-if="p.user" :to="`/admin/customers/${p.user.id}`" class="font-medium hover:text-brand-700">{{ p.user.email }}</NuxtLink></td>
              <td><DashboardStatusBadge :status="p.status" /><p v-if="p.failureReason" class="mt-0.5 text-xs text-danger">{{ p.failureReason }} (attempt {{ p.attempt }})</p></td>
              <td class="text-right tabular-nums">{{ formatMoney(p.amount, p.currency) }}</td>
              <td class="text-right tabular-nums text-muted">{{ p.refundedAmount ? formatMoney(p.refundedAmount, p.currency) : '—' }}</td>
              <td class="font-mono text-xs text-muted">{{ p.providerPaymentId }}<span v-if="p.invoiceNumber"> · {{ p.invoiceNumber }}</span></td>
              <td><UiButton v-if="canManage && ['succeeded', 'partially_refunded'].includes(p.status)" size="sm" variant="ghost" @click="refundFor = p; refundAmount = String((p.amount - p.refundedAmount) / minorFactor(p.currency))">Refund</UiButton></td>
            </tr>
          </tbody>
        </table>
        <p v-if="!payments.data.value.data.length" class="p-6 text-center text-sm text-muted">No payments recorded yet. Payments appear when the provider confirms them via webhook.</p>
        <div class="px-4 pb-4"><UiPagination v-model="payments.page.value" :last-page="payments.data.value.meta.last_page" :total="payments.data.value.meta.total" /></div>
      </div>
    </template>

    <div v-else-if="tab === 'invoices'" class="card mt-4 overflow-x-auto">
      <table v-if="invoices?.data.length" class="table-base min-w-[700px]">
        <thead><tr><th>Number</th><th>Issued</th><th>Status</th><th class="text-right">Total</th><th>Link</th></tr></thead>
        <tbody><tr v-for="i in invoices.data" :key="i.id"><td class="font-medium">{{ i.number }}</td><td class="text-muted">{{ formatDate(i.issuedAt) }}</td><td><DashboardStatusBadge :status="i.status" /></td><td class="text-right tabular-nums">{{ formatMoney(i.total, i.currency) }}</td><td><a v-if="i.hostedUrl" :href="i.hostedUrl" target="_blank" rel="noopener" class="text-sm font-semibold text-brand-700 hover:underline">View</a></td></tr></tbody>
      </table>
      <p v-else class="p-6 text-center text-sm text-muted">No invoices yet.</p>
    </div>

    <template v-else>
      <div class="card mt-4 p-3"><select v-model="whStatus" class="field-input !w-auto" aria-label="Webhook status"><option value="">All events</option><option value="failed">Failed</option><option value="processed">Processed</option><option value="ignored">Ignored</option><option value="received">Received</option></select></div>
      <div class="card mt-4 overflow-x-auto">
        <table v-if="webhooks?.data.length" class="table-base min-w-[900px]">
          <thead><tr><th>Received</th><th>Type</th><th>Status</th><th>Attempts</th><th>Event ID</th><th /></tr></thead>
          <tbody>
            <tr v-for="w in webhooks.data" :key="w.id">
              <td class="text-muted">{{ formatDateTime(w.created_at) }}</td>
              <td class="font-mono text-xs">{{ w.type }}</td>
              <td><DashboardStatusBadge :status="w.status" /><p v-if="w.error" class="mt-0.5 max-w-xs truncate text-xs text-danger" :title="w.error">{{ w.error }}</p></td>
              <td class="tabular-nums">{{ w.attempts }}</td>
              <td class="font-mono text-xs text-muted">{{ w.event_id }}</td>
              <td><UiButton v-if="canManage && w.status === 'failed'" size="sm" variant="ghost" @click="retry(w)">Retry</UiButton></td>
            </tr>
          </tbody>
        </table>
        <p v-else class="p-6 text-center text-sm text-muted">No webhook events.</p>
      </div>
    </template>

    <UiModal :open="!!refundFor" title="Refund payment" size="sm" @update:open="v => { if (!v) refundFor = null }">
      <UiField v-if="refundFor" :label="`Amount (${refundFor.currency})`" id="rf-amount" :hint="`Up to ${formatMoney(refundFor.amount - refundFor.refundedAmount, refundFor.currency)}`">
        <input id="rf-amount" v-model="refundAmount" type="number" min="0.01" step="any" class="field-input">
      </UiField>
      <template #footer><UiButton variant="secondary" @click="refundFor = null">Cancel</UiButton><UiButton variant="danger" @click="refund">Continue</UiButton></template>
    </UiModal>
  </div>
</template>
