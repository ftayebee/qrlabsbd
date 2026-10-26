<script setup lang="ts">
import { ArrowLeft } from 'lucide-vue-next'
import type { Invoice, Subscription } from '~/types/api'
import { formatDate, formatDateTime, formatMoney, limitLabel } from '~/utils/format'

definePageMeta({ layout: 'admin', middleware: 'admin' })
useSeoMeta({ title: 'Customer', robots: 'noindex' })

interface Detail {
  id: number; name: string; email: string; status: string; emailVerified: boolean; timezone: string; providerCustomerId: string | null
  roles: { id: number; slug: string; name: string }[]; createdAt: string; lastLoginAt: string | null
  plan: string; entitlements: Record<string, number | boolean | string>; usage: Record<string, number>
  overrides: { id: number; key: string; value: string; reason: string; expiresAt: string | null }[]
  subscriptions: (Subscription & { isManual: boolean; events?: { id: number; type: string; fromStatus: string | null; toStatus: string | null; reason: string | null; actor: string | null; createdAt: string }[] })[]
  invoices: Invoice[]
  payments: { id: number; status: string; amount: number; currency: string; providerPaymentId: string; createdAt: string }[]
}

const route = useRoute()
const id = Number(route.params.id)
const api = useApi()
const toast = useToast()
const { confirm } = useConfirm()
const { can, user: me } = useAuth()
const { data, refresh } = await useAsyncData(`admin-customer-${id}`, async () => (await api.get<{ data: Detail }>(`/admin/customers/${id}`)).data, { server: false })
const { data: plans } = await useAsyncData('admin-plan-options', async () => (await api.get<{ data: { id: number; slug: string; name: string }[] }>('/admin/plans')).data, { server: false, default: () => [] })
const { data: roles } = await useAsyncData('admin-roles', async () => can('roles.manage') || me.value?.roles.includes('super-admin') ? (await api.get<{ data: { roles: { id: number; slug: string; name: string }[] } }>('/admin/roles')).data.roles : [], { server: false, default: () => [] })

async function toggleSuspend() {
  if (!data.value) return
  const next = data.value.status === 'suspended' ? 'active' : 'suspended'
  const reason = await confirm({ title: next === 'suspended' ? 'Suspend this customer?' : 'Reactivate this customer?', message: next === 'suspended' ? 'They are signed out of the API and all of their dynamic codes stop resolving immediately.' : 'Their account and codes will work again.', confirmLabel: next === 'suspended' ? 'Suspend' : 'Reactivate', tone: next === 'suspended' ? 'danger' : 'primary', requireReason: true })
  if (!reason) return
  try { await api.patch(`/admin/customers/${id}`, { status: next, reason }); toast.success('Customer updated.'); await refresh() } catch (e) { toast.error((e as Error).message) }
}

const override = reactive({ key: 'dynamic_codes', value: '', reason: '', expires_at: '' })
async function addOverride() {
  try {
    await api.post(`/admin/customers/${id}/quota-overrides`, { ...override, expires_at: override.expires_at || null })
    toast.success('Override saved.')
    Object.assign(override, { value: '', reason: '', expires_at: '' })
    await refresh()
  } catch (e) { toast.error((e as Error).message) }
}
async function removeOverride(oid: number) {
  if (!(await confirm({ title: 'Remove override?', message: 'The customer returns to their plan limit.', confirmLabel: 'Remove', tone: 'danger' }))) return
  await api.del(`/admin/customers/${id}/quota-overrides/${oid}`)
  await refresh()
}

const grant = reactive({ plan_id: '', ends_at: '', reason: '' })
async function grantPlan() {
  try {
    await api.post(`/admin/customers/${id}/grant-plan`, { plan_id: Number(grant.plan_id), ends_at: grant.ends_at || null, reason: grant.reason })
    toast.success('Plan granted.')
    Object.assign(grant, { plan_id: '', ends_at: '', reason: '' })
    await refresh()
  } catch (e) { toast.error((e as Error).message) }
}

async function cancelSub(subId: number) {
  const reason = await confirm({ title: 'Cancel this subscription immediately?', message: 'Billing stops at the provider. The customer enters the grace period.', confirmLabel: 'Cancel now', tone: 'danger', requireReason: true })
  if (!reason) return
  try { await api.post(`/admin/subscriptions/${subId}/cancel`, { immediately: true, reason }); toast.success('Subscription cancelled.'); await refresh() } catch (e) { toast.error((e as Error).message) }
}

const selectedRoles = ref<number[]>([])
watch(data, d => { if (d) selectedRoles.value = d.roles.map(r => r.id) }, { immediate: true })
async function saveRoles() {
  const reason = await confirm({ title: 'Update roles?', message: 'Role changes take effect on the next request.', confirmLabel: 'Save roles', requireReason: true })
  if (!reason) return
  try { await api.put(`/admin/customers/${id}/roles`, { roles: selectedRoles.value, reason }); toast.success('Roles updated.'); await refresh() } catch (e) { toast.error((e as Error).message) }
}
const keys = ['dynamic_codes', 'saved_static_codes', 'scans_per_month', 'storage_mb', 'max_upload_mb', 'analytics_days', 'team_members', 'campaigns', 'custom_logo', 'vector_downloads', 'csv_export']
</script>

<template>
  <div>
    <NuxtLink to="/admin/customers" class="inline-flex items-center gap-1.5 text-sm font-semibold text-muted hover:text-ink"><ArrowLeft class="size-4" /> Customers</NuxtLink>
    <UiSpinner v-if="!data" />
    <template v-else>
      <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 class="text-2xl font-extrabold sm:text-3xl">{{ data.name }}</h1>
          <p class="mt-1 text-muted">{{ data.email }} · joined {{ formatDate(data.createdAt) }} · {{ data.plan }} plan</p>
          <div class="mt-2 flex gap-2"><DashboardStatusBadge :status="data.status" /><UiBadge :tone="data.emailVerified ? 'green' : 'amber'">{{ data.emailVerified ? 'Email verified' : 'Unverified' }}</UiBadge></div>
        </div>
        <UiButton v-if="data.id !== me?.id" :variant="data.status === 'suspended' ? 'primary' : 'danger'" @click="toggleSuspend">{{ data.status === 'suspended' ? 'Reactivate' : 'Suspend' }}</UiButton>
      </div>

      <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="card p-6">
          <h2 class="font-bold">Usage &amp; entitlements</h2>
          <table class="table-base mt-3">
            <thead><tr><th>Feature</th><th class="text-right">Used</th><th class="text-right">Limit</th></tr></thead>
            <tbody><tr v-for="k in keys" :key="k"><td>{{ k.replace(/_/g, ' ') }}</td><td class="text-right tabular-nums">{{ data.usage[k] ?? '—' }}</td><td class="text-right">{{ limitLabel(data.entitlements[k]) }}</td></tr></tbody>
          </table>
        </section>

        <section class="card p-6">
          <h2 class="font-bold">Quota overrides</h2>
          <p class="mt-1 text-sm text-muted">Overrides replace the plan value for this customer only. Use -1 for unlimited and true/false for features.</p>
          <ul class="mt-4 space-y-2">
            <li v-for="o in data.overrides" :key="o.id" class="flex items-center justify-between gap-3 rounded-xl bg-canvas px-3 py-2 text-sm">
              <span><strong>{{ o.key }}</strong> = {{ o.value }} <span class="text-muted">· {{ o.reason }}<template v-if="o.expiresAt"> · until {{ formatDate(o.expiresAt) }}</template></span></span>
              <button type="button" class="text-xs font-semibold text-danger hover:underline" @click="removeOverride(o.id)">Remove</button>
            </li>
          </ul>
          <form class="mt-4 grid gap-3 sm:grid-cols-2" @submit.prevent="addOverride">
            <select v-model="override.key" class="field-input" aria-label="Feature"><option v-for="k in keys" :key="k" :value="k">{{ k }}</option></select>
            <input v-model="override.value" class="field-input" placeholder="Value" aria-label="Value" required>
            <input v-model="override.reason" class="field-input sm:col-span-2" placeholder="Reason (required, audited)" aria-label="Reason" required>
            <input v-model="override.expires_at" type="date" class="field-input" aria-label="Expires">
            <UiButton type="submit" variant="secondary">Add override</UiButton>
          </form>
        </section>

        <section class="card p-6 xl:col-span-2">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-bold">Subscriptions</h2>
          </div>
          <div v-for="s in data.subscriptions" :key="s.id" class="mt-4 rounded-2xl border border-line p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <div class="flex flex-wrap items-center gap-2">
                <strong>{{ s.plan.name }}</strong><DashboardStatusBadge :status="s.status" /><UiBadge v-if="s.isManual" tone="blue">Manual</UiBadge>
                <span class="text-xs text-muted">{{ s.provider }} · since {{ formatDate(s.createdAt) }}<template v-if="s.currentPeriodEnd"> · period ends {{ formatDate(s.currentPeriodEnd) }}</template><template v-if="s.graceEndsAt"> · grace until {{ formatDate(s.graceEndsAt) }}</template></span>
              </div>
              <div class="flex gap-2">
                <UiButton :to="`/admin/subscriptions?open=${s.id}`" size="sm" variant="ghost">Details</UiButton>
                <UiButton v-if="['active', 'trialing', 'past_due'].includes(s.status)" size="sm" variant="ghost" class="!text-danger" @click="cancelSub(s.id)">Cancel</UiButton>
              </div>
            </div>
            <ol v-if="s.events?.length" class="mt-3 space-y-1 border-l-2 border-line pl-4 text-xs text-muted">
              <li v-for="e in s.events.slice(0, 6)" :key="e.id">{{ formatDateTime(e.createdAt) }} — <strong class="text-ink">{{ e.type.replace(/_/g, ' ') }}</strong><template v-if="e.toStatus"> → {{ e.toStatus }}</template><template v-if="e.actor"> by {{ e.actor }}</template><template v-if="e.reason">: “{{ e.reason }}”</template></li>
            </ol>
          </div>
          <form class="mt-5 grid gap-3 rounded-2xl bg-canvas p-4 sm:grid-cols-[1fr_auto_1.5fr_auto]" @submit.prevent="grantPlan">
            <select v-model="grant.plan_id" class="field-input" aria-label="Plan" required><option value="" disabled>Grant a plan…</option><option v-for="p in plans" :key="p.id" :value="String(p.id)">{{ p.name }}</option></select>
            <input v-model="grant.ends_at" type="date" class="field-input" aria-label="Ends on (optional)">
            <input v-model="grant.reason" class="field-input" placeholder="Reason (required, audited)" aria-label="Reason" required>
            <UiButton type="submit" variant="dark">Grant</UiButton>
          </form>
        </section>

        <section class="card overflow-x-auto p-6">
          <h2 class="font-bold">Payments</h2>
          <table v-if="data.payments.length" class="table-base mt-3"><tbody>
            <tr v-for="p in data.payments" :key="p.id"><td class="text-muted">{{ formatDate(p.createdAt) }}</td><td><DashboardStatusBadge :status="p.status" /></td><td class="text-right tabular-nums">{{ formatMoney(p.amount, p.currency) }}</td></tr>
          </tbody></table>
          <p v-else class="mt-3 text-sm text-muted">No payments.</p>
        </section>

        <section v-if="roles.length" class="card p-6">
          <h2 class="font-bold">Roles</h2>
          <div class="mt-4 grid gap-2 sm:grid-cols-2">
            <label v-for="r in roles" :key="r.id" class="flex items-center gap-2 text-sm"><input v-model="selectedRoles" type="checkbox" :value="r.id" class="size-4 accent-[var(--color-brand)]"> {{ r.name }}</label>
          </div>
          <UiButton class="mt-4" size="sm" variant="secondary" @click="saveRoles">Save roles</UiButton>
        </section>
      </div>
    </template>
  </div>
</template>
