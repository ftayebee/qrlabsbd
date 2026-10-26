<script setup lang="ts">
import { ArrowDown, ArrowLeft, ArrowUp, Plus, Trash2 } from 'lucide-vue-next'
import type { Plan } from '~/types/api'
import { ApiError } from '~/composables/useApi'
import { minorFactor } from '~/utils/format'

definePageMeta({ layout: 'admin', middleware: 'admin' })
useSeoMeta({ title: 'Edit plan', robots: 'noindex' })

type AdminPlan = Plan & { status: string; isPublic: boolean; isDefault: boolean }
interface PriceForm { id?: number; interval: 'month' | 'year'; currency: string; amount: string; promo_amount: string; promo_ends_at: string; is_active: boolean }
interface FeatureForm { key: string; label: string; type: 'boolean' | 'limit' | 'text'; value: string; is_highlighted: boolean }

const route = useRoute()
const isNew = route.params.id === 'new'
const api = useApi()
const toast = useToast()
const { confirm } = useConfirm()

const form = reactive({
  name: '', slug: '', description: '', status: 'draft', is_public: true, is_custom: false, highlight: false,
  cta_label: '', trial_days: 0, sort_order: 10,
  prices: [] as PriceForm[],
  features: [] as FeatureForm[],
})
const errors = ref<Record<string, string>>({})
const saving = ref(false)
const isDefault = ref(false)

// Amounts are edited in major units and sent as integer minor units.
const toMajor = (minor: number | null, currency: string) => (minor === null ? '' : String(minor / minorFactor(currency)))
const toMinor = (major: string, currency: string) => Math.round(Number(major) * minorFactor(currency))

if (!isNew) {
  const res = await api.get<{ data: AdminPlan }>(`/admin/plans/${route.params.id}`).catch(() => null)
  if (res) {
    const p = res.data
    isDefault.value = p.isDefault
    Object.assign(form, {
      name: p.name, slug: p.slug, description: p.description ?? '', status: p.status, is_public: p.isPublic, is_custom: p.isCustom,
      highlight: p.highlight, cta_label: p.ctaLabel ?? '', trial_days: p.trialDays, sort_order: p.sortOrder,
      prices: p.prices.map(pr => ({ id: pr.id, interval: pr.interval, currency: pr.currency, amount: toMajor(pr.amount, pr.currency), promo_amount: toMajor(pr.promoAmount, pr.currency), promo_ends_at: pr.promoEndsAt?.slice(0, 10) ?? '', is_active: (pr as unknown as { isActive: boolean }).isActive ?? true })),
      features: p.features.map(f => ({ key: f.key, label: f.label, type: f.type, value: f.value, is_highlighted: f.highlighted })),
    })
  }
}

watch(() => form.name, (n) => { if (isNew && !form.slug) form.slug = n.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') })

const knownFeatures: Record<string, [string, FeatureForm['type']]> = {
  dynamic_codes: ['Dynamic QR codes', 'limit'], saved_static_codes: ['Saved static codes', 'limit'], scans_per_month: ['Tracked scans per month', 'limit'],
  analytics_days: ['Analytics history (days)', 'limit'], storage_mb: ['File storage (MB)', 'limit'], max_upload_mb: ['Max PDF size (MB)', 'limit'],
  team_members: ['Team members', 'limit'], campaigns: ['Campaigns', 'boolean'], custom_logo: ['Logo in QR code', 'boolean'],
  vector_downloads: ['SVG and PDF downloads', 'boolean'], csv_export: ['CSV exports', 'boolean'],
}
const missingFeatures = computed(() => Object.keys(knownFeatures).filter(k => !form.features.some(f => f.key === k)))
function addFeature(key?: string) {
  if (key && knownFeatures[key]) form.features.push({ key, label: knownFeatures[key][0], type: knownFeatures[key][1], value: knownFeatures[key][1] === 'boolean' ? 'true' : '0', is_highlighted: false })
  else form.features.push({ key: '', label: '', type: 'text', value: '', is_highlighted: false })
}
function moveFeature(i: number, d: -1 | 1) {
  const j = i + d
  if (j < 0 || j >= form.features.length) return
  const [f] = form.features.splice(i, 1)
  form.features.splice(j, 0, f!)
}

async function save() {
  const reason = isNew ? null : await confirm({ title: 'Save plan changes?', message: 'Price changes create new provider prices for new subscribers only. This is recorded in the audit log.', confirmLabel: 'Save', requireReason: true })
  if (!isNew && !reason) return
  saving.value = true
  errors.value = {}
  try {
    const body = {
      ...form,
      reason: typeof reason === 'string' ? reason : undefined,
      prices: form.prices.map(p => ({ id: p.id, interval: p.interval, currency: p.currency.toUpperCase(), amount: toMinor(p.amount, p.currency), promo_amount: p.promo_amount === '' ? null : toMinor(p.promo_amount, p.currency), promo_ends_at: p.promo_ends_at || null, is_active: p.is_active })),
    }
    const res = isNew ? await api.post<{ data: { id: number } }>('/admin/plans', body) : await api.put<{ data: { id: number } }>(`/admin/plans/${route.params.id}`, body)
    toast.success('Plan saved.')
    if (isNew) await navigateTo(`/admin/plans/${res.data.id}`)
  } catch (e) {
    if (e instanceof ApiError) errors.value = e.fieldErrors
    toast.error((e as Error).message)
  } finally { saving.value = false }
}

async function archive() {
  if (!(await confirm({ title: 'Archive this plan?', message: 'It disappears from pricing. Existing subscribers keep it until they change plan.', confirmLabel: 'Archive', tone: 'danger' }))) return
  try { await api.del(`/admin/plans/${route.params.id}`); toast.success('Plan archived.'); await navigateTo('/admin/plans') } catch (e) { toast.error((e as Error).message) }
}
</script>

<template>
  <div class="max-w-5xl">
    <NuxtLink to="/admin/plans" class="inline-flex items-center gap-1.5 text-sm font-semibold text-muted hover:text-ink"><ArrowLeft class="size-4" /> Plans</NuxtLink>
    <h1 class="mt-3 text-2xl font-extrabold sm:text-3xl">{{ isNew ? 'New plan' : `Edit ${form.name}` }}</h1>

    <form class="mt-6 space-y-6" @submit.prevent="save">
      <section class="card grid gap-4 p-6 sm:grid-cols-2">
        <UiField label="Name" id="pl-name" :error="errors.name" required><input id="pl-name" v-model="form.name" class="field-input" required maxlength="100"></UiField>
        <UiField label="Slug" id="pl-slug" :error="errors.slug" required><input id="pl-slug" v-model="form.slug" class="field-input font-mono" required maxlength="64" :disabled="isDefault"></UiField>
        <UiField label="Description" id="pl-desc" class="sm:col-span-2"><textarea id="pl-desc" v-model="form.description" rows="2" class="field-input" maxlength="500" /></UiField>
        <UiField label="Status" id="pl-status"><select id="pl-status" v-model="form.status" class="field-input"><option value="draft">Draft</option><option value="published">Published</option><option value="archived">Archived</option></select></UiField>
        <UiField label="Button label" id="pl-cta"><input id="pl-cta" v-model="form.cta_label" class="field-input" maxlength="40" placeholder="Get started"></UiField>
        <UiField label="Trial days" id="pl-trial"><input id="pl-trial" v-model.number="form.trial_days" type="number" min="0" max="90" class="field-input"></UiField>
        <UiField label="Display order" id="pl-sort"><input id="pl-sort" v-model.number="form.sort_order" type="number" min="0" class="field-input"></UiField>
        <div class="grid gap-4 sm:col-span-2 sm:grid-cols-3">
          <UiSwitch v-model="form.is_public" label="Public" description="Shown on pricing pages" />
          <UiSwitch v-model="form.highlight" label="Highlight" description="“Most popular” styling" />
          <UiSwitch v-model="form.is_custom" label="Custom / sales-led" description="No online checkout" />
        </div>
      </section>

      <section class="card p-6">
        <div class="flex items-center justify-between">
          <h2 class="font-bold">Prices</h2>
          <UiButton size="sm" variant="soft" @click="form.prices.push({ interval: 'month', currency: 'USD', amount: '0.00', promo_amount: '', promo_ends_at: '', is_active: true })"><Plus class="size-4" /> Add price</UiButton>
        </div>
        <p class="mt-1 text-xs text-muted">Enter amounts in major units (e.g. 12.00). A promotional amount is charged to new subscribers until the end date.</p>
        <div v-for="(p, i) in form.prices" :key="i" class="mt-4 grid gap-3 rounded-2xl bg-canvas p-4 sm:grid-cols-[110px_90px_1fr_1fr_150px_auto_auto] sm:items-end">
          <UiField label="Interval" :id="`pr-int-${i}`"><select :id="`pr-int-${i}`" v-model="p.interval" class="field-input"><option value="month">Monthly</option><option value="year">Yearly</option></select></UiField>
          <UiField label="Currency" :id="`pr-cur-${i}`"><input :id="`pr-cur-${i}`" v-model="p.currency" class="field-input uppercase" maxlength="3"></UiField>
          <UiField label="Amount" :id="`pr-amt-${i}`" :error="errors[`prices.${i}.amount`]"><input :id="`pr-amt-${i}`" v-model="p.amount" type="number" min="0" step="0.01" class="field-input"></UiField>
          <UiField label="Promo amount" :id="`pr-promo-${i}`" :error="errors[`prices.${i}.promo_amount`]"><input :id="`pr-promo-${i}`" v-model="p.promo_amount" type="number" min="0" step="0.01" class="field-input" placeholder="None"></UiField>
          <UiField label="Promo ends" :id="`pr-pe-${i}`"><input :id="`pr-pe-${i}`" v-model="p.promo_ends_at" type="date" class="field-input"></UiField>
          <label class="flex items-center gap-2 pb-2.5 text-sm"><input v-model="p.is_active" type="checkbox" class="size-4 accent-[var(--color-brand)]"> Active</label>
          <button type="button" class="mb-1 rounded-lg p-2 text-muted hover:text-danger" :aria-label="`Remove price ${i + 1}`" @click="form.prices.splice(i, 1)"><Trash2 class="size-4" /></button>
        </div>
      </section>

      <section class="card p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h2 class="font-bold">Features &amp; entitlements</h2>
          <div class="flex flex-wrap gap-2">
            <select v-if="missingFeatures.length" class="field-input !w-auto !py-1.5 text-sm" aria-label="Add entitlement" @change="e => { addFeature((e.target as HTMLSelectElement).value); (e.target as HTMLSelectElement).value = '' }">
              <option value="">Add entitlement…</option>
              <option v-for="k in missingFeatures" :key="k" :value="k">{{ knownFeatures[k]![0] }}</option>
            </select>
            <UiButton size="sm" variant="soft" @click="addFeature()"><Plus class="size-4" /> Custom feature</UiButton>
          </div>
        </div>
        <p class="mt-1 text-xs text-muted">Known keys are enforced by the API. Limits use -1 for unlimited. Highlighted features appear on pricing cards.</p>
        <div v-for="(f, i) in form.features" :key="i" class="mt-3 grid gap-2 rounded-2xl bg-canvas p-3 sm:grid-cols-[1fr_1.4fr_110px_120px_auto_auto] sm:items-center">
          <input v-model="f.key" class="field-input font-mono text-xs" placeholder="key" :aria-label="`Feature ${i + 1} key`" :readonly="!!knownFeatures[f.key]">
          <input v-model="f.label" class="field-input" placeholder="Label" :aria-label="`Feature ${i + 1} label`">
          <select v-model="f.type" class="field-input" :aria-label="`Feature ${i + 1} type`" :disabled="!!knownFeatures[f.key]"><option value="limit">Limit</option><option value="boolean">Yes/No</option><option value="text">Text</option></select>
          <select v-if="f.type === 'boolean'" v-model="f.value" class="field-input" :aria-label="`Feature ${i + 1} value`"><option value="true">Included</option><option value="false">Not included</option></select>
          <input v-else v-model="f.value" class="field-input" :type="f.type === 'limit' ? 'number' : 'text'" :aria-label="`Feature ${i + 1} value`">
          <label class="flex items-center gap-1.5 text-xs"><input v-model="f.is_highlighted" type="checkbox" class="size-4 accent-[var(--color-brand)]"> Show</label>
          <div class="flex">
            <button type="button" class="rounded p-1.5 text-muted hover:text-ink" :aria-label="`Move feature ${i + 1} up`" @click="moveFeature(i, -1)"><ArrowUp class="size-4" /></button>
            <button type="button" class="rounded p-1.5 text-muted hover:text-ink" :aria-label="`Move feature ${i + 1} down`" @click="moveFeature(i, 1)"><ArrowDown class="size-4" /></button>
            <button type="button" class="rounded p-1.5 text-muted hover:text-danger" :aria-label="`Remove feature ${i + 1}`" @click="form.features.splice(i, 1)"><Trash2 class="size-4" /></button>
          </div>
        </div>
      </section>

      <div class="flex flex-wrap justify-between gap-3">
        <UiButton v-if="!isNew && !isDefault" variant="ghost" class="!text-danger" @click="archive">Archive plan</UiButton>
        <span v-else />
        <UiButton type="submit" :loading="saving">{{ isNew ? 'Create plan' : 'Save changes' }}</UiButton>
      </div>
    </form>
  </div>
</template>
