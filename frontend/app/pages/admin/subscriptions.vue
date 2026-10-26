<script setup lang="ts">
import { Search } from 'lucide-vue-next'
import type { Item, Subscription } from '~/types/api'
import { formatDate, formatDateTime, formatMoney } from '~/utils/format'

definePageMeta({ layout: 'admin', middleware: 'admin' })
useSeoMeta({ title: 'Subscriptions', robots: 'noindex' })

type AdminSub = Subscription & { isManual: boolean; cancellationReason?: string | null; user?: { id: number; name: string; email: string }; events?: { id: number; type: string; fromStatus: string | null; toStatus: string | null; reason: string | null; actor: string | null; createdAt: string }[] }

const route = useRoute()
const api = useApi()
const toast = useToast()
const { confirm } = useConfirm()
const { data, status, filters, page, search, refresh } = useAdminList<AdminSub>('admin-subs', '/admin/subscriptions', { status: String(route.query.status ?? ''), renewing_before: '' })

const openId = ref<number | null>(route.query.open ? Number(route.query.open) : null)
const { data: detail, refresh: refreshDetail } = await useAsyncData('admin-sub-detail', async () => openId.value ? (await api.get<Item<AdminSub>>(`/admin/subscriptions/${openId.value}`)).data : null, { server: false, watch: [openId] })
const trialUntil = ref('')
const open = computed({ get: () => openId.value !== null, set: (v) => { if (!v) openId.value = null } })

async function act(kind: 'cancel' | 'cancel-end' | 'sync' | 'trial') {
  if (!detail.value) return
  const id = detail.value.id
  try {
    if (kind === 'sync') {
      await api.post(`/admin/subscriptions/${id}/sync`)
    } else if (kind === 'trial') {
      if (!trialUntil.value) { toast.error('Choose the new trial end date first.'); return }
      const reason = await confirm({ title: 'Extend trial?', message: `New trial end: ${trialUntil.value}`, confirmLabel: 'Extend', requireReason: true })
      if (!reason) return
      await api.post(`/admin/subscriptions/${id}/extend-trial`, { until: trialUntil.value, reason })
    } else {
      const reason = await confirm({ title: kind === 'cancel' ? 'Cancel immediately?' : 'Cancel at period end?', message: 'This is sent to the payment provider and recorded in the audit log.', confirmLabel: 'Cancel subscription', tone: 'danger', requireReason: true })
      if (!reason) return
      await api.post(`/admin/subscriptions/${id}/cancel`, { immediately: kind === 'cancel', reason })
    }
    toast.success('Done.')
    await Promise.all([refreshDetail(), refresh()])
  } catch (e) { toast.error((e as Error).message) }
}
</script>

<template>
  <div>
    <h1 class="text-2xl font-extrabold sm:text-3xl">Subscriptions</h1>
    <div class="card mt-6 flex flex-wrap gap-3 p-3">
      <div class="relative min-w-56 flex-1">
        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" />
        <input v-model="search" type="search" class="field-input !pl-9" placeholder="Customer email, name or provider ID" aria-label="Search">
      </div>
      <select v-model="filters.status" class="field-input !w-auto" aria-label="Status">
        <option value="">All (excluding incomplete)</option>
        <option v-for="s in ['trialing', 'active', 'past_due', 'canceled', 'expired', 'incomplete']" :key="s" :value="s">{{ s.replace('_', ' ') }}</option>
      </select>
      <label class="flex items-center gap-2 text-sm text-muted">Renewing before <input v-model="filters.renewing_before" type="date" class="field-input !w-auto"></label>
    </div>
    <UiSpinner v-if="status === 'pending' && !data" />
    <div v-else-if="data" class="card mt-4 overflow-x-auto">
      <table class="table-base min-w-[880px]">
        <thead><tr><th>Customer</th><th>Plan</th><th>Status</th><th>Price</th><th>Period end</th><th>Trial end</th><th /></tr></thead>
        <tbody>
          <tr v-for="s in data.data" :key="s.id">
            <td><NuxtLink v-if="s.user" :to="`/admin/customers/${s.user.id}`" class="font-semibold hover:text-brand-700">{{ s.user.name }}</NuxtLink><p class="text-xs text-muted">{{ s.user?.email }}</p></td>
            <td>{{ s.plan.name }} <UiBadge v-if="s.isManual" tone="blue">Manual</UiBadge></td>
            <td><DashboardStatusBadge :status="s.status" /> <UiBadge v-if="s.cancelAtPeriodEnd" tone="amber">Cancels</UiBadge></td>
            <td class="tabular-nums">{{ s.price ? `${formatMoney(s.price.amount, s.price.currency)}/${s.price.interval}` : '—' }}</td>
            <td class="text-muted">{{ formatDate(s.currentPeriodEnd) }}</td>
            <td class="text-muted">{{ formatDate(s.trialEndsAt) }}</td>
            <td><UiButton size="sm" variant="ghost" @click="openId = s.id">Manage</UiButton></td>
          </tr>
        </tbody>
      </table>
      <p v-if="!data.data.length" class="p-6 text-center text-sm text-muted">No subscriptions match.</p>
      <div class="px-4 pb-4"><UiPagination v-model="page" :last-page="data.meta.last_page" :total="data.meta.total" /></div>
    </div>

    <UiModal v-model:open="open" :title="detail ? `${detail.plan.name} · ${detail.user?.email}` : 'Subscription'" size="lg">
      <UiSpinner v-if="!detail" />
      <template v-else>
        <dl class="grid gap-3 text-sm sm:grid-cols-3">
          <div><dt class="text-muted">Status</dt><dd class="mt-1"><DashboardStatusBadge :status="detail.status" /></dd></div>
          <div><dt class="text-muted">Provider</dt><dd class="mt-1 font-medium">{{ detail.provider }}</dd></div>
          <div><dt class="text-muted">Renews / ends</dt><dd class="mt-1 font-medium">{{ formatDate(detail.currentPeriodEnd) }}</dd></div>
          <div><dt class="text-muted">Trial ends</dt><dd class="mt-1 font-medium">{{ formatDate(detail.trialEndsAt) }}</dd></div>
          <div><dt class="text-muted">Grace until</dt><dd class="mt-1 font-medium">{{ formatDate(detail.graceEndsAt) }}</dd></div>
          <div><dt class="text-muted">Cancellation reason</dt><dd class="mt-1 font-medium">{{ detail.cancellationReason ?? '—' }}</dd></div>
        </dl>
        <h3 class="mt-6 text-sm font-bold">History</h3>
        <ol class="mt-2 max-h-64 space-y-2 overflow-y-auto border-l-2 border-line pl-4 text-sm">
          <li v-for="e in detail.events" :key="e.id">
            <span class="text-xs text-muted">{{ formatDateTime(e.createdAt) }}</span>
            <p><strong>{{ e.type.replace(/_/g, ' ') }}</strong><template v-if="e.fromStatus || e.toStatus"> {{ e.fromStatus ?? '∅' }} → {{ e.toStatus }}</template><template v-if="e.actor"> · {{ e.actor }}</template></p>
            <p v-if="e.reason" class="text-xs text-muted">“{{ e.reason }}”</p>
          </li>
        </ol>
      </template>
      <template #footer>
        <UiButton v-if="detail && !detail.isManual" variant="secondary" @click="act('sync')">Sync from provider</UiButton>
        <div v-if="detail && (detail.isManual || detail.status === 'trialing')" class="flex gap-2">
          <input v-model="trialUntil" type="date" class="field-input !w-auto" aria-label="New trial end date">
          <UiButton variant="secondary" @click="act('trial')">Extend trial</UiButton>
        </div>
        <UiButton v-if="detail && ['active', 'trialing', 'past_due'].includes(detail.status) && !detail.cancelAtPeriodEnd" variant="secondary" @click="act('cancel-end')">Cancel at period end</UiButton>
        <UiButton v-if="detail && ['active', 'trialing', 'past_due'].includes(detail.status)" variant="danger" @click="act('cancel')">Cancel now</UiButton>
      </template>
    </UiModal>
  </div>
</template>
