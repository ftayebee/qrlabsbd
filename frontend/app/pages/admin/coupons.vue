<script setup lang="ts">
import { Plus } from 'lucide-vue-next'
import { ApiError } from '~/composables/useApi'
import { formatDate, formatMoney, minorFactor } from '~/utils/format'

definePageMeta({ layout: 'admin', middleware: 'admin' })
useSeoMeta({ title: 'Coupons', robots: 'noindex' })

interface Coupon { id: number; code: string; name: string; type: 'percent' | 'fixed'; value: number; currency: string | null; duration: string; durationMonths: number | null; maxRedemptions: number | null; redeemedCount: number; startsAt: string | null; expiresAt: string | null; isActive: boolean; plans: { id: number; name: string }[] }

const api = useApi()
const toast = useToast()
const { confirm } = useConfirm()
const { data, page, search, refresh } = useAdminList<Coupon>('admin-coupons', '/admin/coupons')
const { data: plans } = await useAsyncData('admin-plan-options', async () => (await api.get<{ data: { id: number; name: string; slug: string }[] }>('/admin/plans')).data, { server: false, default: () => [] })

const open = ref(false)
const form = reactive({ code: '', name: '', type: 'percent' as 'percent' | 'fixed', value: '', currency: 'USD', duration: 'once', duration_months: '', max_redemptions: '', starts_at: '', expires_at: '', is_active: true, plan_ids: [] as number[] })
const errors = ref<Record<string, string>>({})

async function create() {
  errors.value = {}
  try {
    await api.post('/admin/coupons', {
      ...form,
      code: form.code.toUpperCase(),
      value: form.type === 'fixed' ? Math.round(Number(form.value) * minorFactor(form.currency)) : Number(form.value),
      currency: form.type === 'fixed' ? form.currency.toUpperCase() : null,
      duration_months: form.duration === 'repeating' ? Number(form.duration_months) : null,
      max_redemptions: form.max_redemptions ? Number(form.max_redemptions) : null,
      starts_at: form.starts_at || null, expires_at: form.expires_at || null,
    })
    toast.success('Coupon created.')
    open.value = false
    await refresh()
  } catch (e) {
    if (e instanceof ApiError) errors.value = e.fieldErrors
    toast.error((e as Error).message)
  }
}

async function toggle(c: Coupon) {
  try { await api.put(`/admin/coupons/${c.id}`, { is_active: !c.isActive }); await refresh() } catch (e) { toast.error((e as Error).message) }
}
async function remove(c: Coupon) {
  if (!(await confirm({ title: `Remove ${c.code}?`, message: c.redeemedCount ? 'It has been redeemed, so it will be deactivated instead of deleted.' : 'This coupon will be deleted.', confirmLabel: 'Remove', tone: 'danger' }))) return
  const res = await api.del<{ message: string }>(`/admin/coupons/${c.id}`)
  toast.success(res.message)
  await refresh()
}
const describe = (c: Coupon) => (c.type === 'percent' ? `${c.value}% off` : `${formatMoney(c.value, c.currency ?? 'USD')} off`) + ` · ${c.duration === 'repeating' ? `${c.durationMonths} months` : c.duration}`
</script>

<template>
  <div>
    <div class="flex flex-wrap items-end justify-between gap-4">
      <h1 class="text-2xl font-extrabold sm:text-3xl">Coupons</h1>
      <UiButton @click="open = true"><Plus class="size-4" /> New coupon</UiButton>
    </div>
    <div class="card mt-6 p-3"><input v-model="search" type="search" class="field-input" placeholder="Search code or name" aria-label="Search coupons"></div>
    <div v-if="data" class="card mt-4 overflow-x-auto">
      <table class="table-base min-w-[860px]">
        <thead><tr><th>Code</th><th>Discount</th><th>Plans</th><th>Redeemed</th><th>Valid</th><th>Status</th><th /></tr></thead>
        <tbody>
          <tr v-for="c in data.data" :key="c.id">
            <td><p class="font-mono font-semibold">{{ c.code }}</p><p class="text-xs text-muted">{{ c.name }}</p></td>
            <td>{{ describe(c) }}</td>
            <td class="text-muted">{{ c.plans.length ? c.plans.map(p => p.name).join(', ') : 'All plans' }}</td>
            <td class="tabular-nums">{{ c.redeemedCount }}<span v-if="c.maxRedemptions" class="text-muted"> / {{ c.maxRedemptions }}</span></td>
            <td class="text-xs text-muted">{{ c.startsAt ? formatDate(c.startsAt) : 'Now' }} → {{ c.expiresAt ? formatDate(c.expiresAt) : 'No end' }}</td>
            <td><button type="button" @click="toggle(c)"><DashboardStatusBadge :status="c.isActive ? 'active' : 'paused'" /></button></td>
            <td><button type="button" class="text-xs font-semibold text-danger hover:underline" @click="remove(c)">Remove</button></td>
          </tr>
        </tbody>
      </table>
      <p v-if="!data.data.length" class="p-6 text-center text-sm text-muted">No coupons yet.</p>
      <div class="px-4 pb-4"><UiPagination v-model="page" :last-page="data.meta.last_page" /></div>
    </div>

    <UiModal v-model:open="open" title="New coupon" description="Discount terms cannot change after creation; only availability can." size="lg">
      <form id="coupon-form" class="grid gap-4 sm:grid-cols-2" @submit.prevent="create">
        <UiField label="Code" id="cc-code" :error="errors.code" required><input id="cc-code" v-model="form.code" class="field-input font-mono uppercase" maxlength="40" required></UiField>
        <UiField label="Internal name" id="cc-name" :error="errors.name" required><input id="cc-name" v-model="form.name" class="field-input" maxlength="100" required></UiField>
        <UiField label="Type" id="cc-type"><select id="cc-type" v-model="form.type" class="field-input"><option value="percent">Percentage</option><option value="fixed">Fixed amount</option></select></UiField>
        <div class="grid grid-cols-[1fr_90px] gap-2">
          <UiField :label="form.type === 'percent' ? 'Percent off' : 'Amount off'" id="cc-value" :error="errors.value" required><input id="cc-value" v-model="form.value" type="number" min="1" :max="form.type === 'percent' ? 100 : undefined" step="any" class="field-input" required></UiField>
          <UiField v-if="form.type === 'fixed'" label="Currency" id="cc-cur" :error="errors.currency"><input id="cc-cur" v-model="form.currency" class="field-input uppercase" maxlength="3"></UiField>
        </div>
        <UiField label="Duration" id="cc-dur"><select id="cc-dur" v-model="form.duration" class="field-input"><option value="once">First payment</option><option value="repeating">Several months</option><option value="forever">Forever</option></select></UiField>
        <UiField v-if="form.duration === 'repeating'" label="Months" id="cc-months" :error="errors.duration_months"><input id="cc-months" v-model="form.duration_months" type="number" min="1" max="36" class="field-input"></UiField>
        <UiField label="Max redemptions" id="cc-max" hint="Leave blank for unlimited"><input id="cc-max" v-model="form.max_redemptions" type="number" min="1" class="field-input"></UiField>
        <UiField label="Starts" id="cc-start"><input id="cc-start" v-model="form.starts_at" type="date" class="field-input"></UiField>
        <UiField label="Expires" id="cc-exp" :error="errors.expires_at"><input id="cc-exp" v-model="form.expires_at" type="date" class="field-input"></UiField>
        <fieldset class="sm:col-span-2">
          <legend class="field-label">Limit to plans (optional)</legend>
          <div class="flex flex-wrap gap-3"><label v-for="p in plans" :key="p.id" class="flex items-center gap-2 text-sm"><input v-model="form.plan_ids" type="checkbox" :value="p.id" class="size-4 accent-[var(--color-brand)]"> {{ p.name }}</label></div>
        </fieldset>
      </form>
      <template #footer><UiButton variant="secondary" @click="open = false">Cancel</UiButton><UiButton type="submit" form="coupon-form">Create coupon</UiButton></template>
    </UiModal>
  </div>
</template>
