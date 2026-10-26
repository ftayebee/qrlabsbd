<script setup lang="ts">
import { Lock, Pencil, Plus, Trash2 } from 'lucide-vue-next'
import type { Campaign } from '~/types/api'
import { ApiError } from '~/composables/useApi'
import { formatNumber } from '~/utils/format'

definePageMeta({ layout: 'dashboard', middleware: 'auth' })
useSeoMeta({ title: 'Campaigns', robots: 'noindex' })

const api = useApi()
const toast = useToast()
const { confirm } = useConfirm()
const { allows, usage, refresh: refreshUsage } = useUsage()
onMounted(() => { if (!usage.value) refreshUsage() })

const iso = (d: Date) => d.toISOString().slice(0, 10)
const from = ref(iso(new Date(Date.now() - 29 * 86400000)))
const to = ref(iso(new Date()))

const { data: campaigns, refresh } = await useAsyncData('campaigns-list', async () => (await api.get<{ data: Campaign[] }>('/campaigns')).data, { server: false, default: () => [] })
const { data: comparison } = await useAsyncData('campaign-comparison', async () => (await api.get<{ data: { campaignId: number | null; name: string; color: string | null; scans: number; uniqueScans: number; codes: number }[] }>('/analytics/campaigns', { from: from.value, to: to.value })).data, { server: false, default: () => [], watch: [from, to] })

const editing = ref<Partial<Campaign> & { starts_on?: string; ends_on?: string } | null>(null)
const errors = ref<Record<string, string>>({})
const saving = ref(false)
function openNew() { errors.value = {}; editing.value = { name: '', description: '', color: '#1DB45A' } }
function openEdit(c: Campaign) { errors.value = {}; editing.value = { ...c } }

async function save() {
  if (!editing.value) return
  saving.value = true
  try {
    const body = { name: editing.value.name, description: editing.value.description, color: editing.value.color }
    if (editing.value.id) await api.put(`/campaigns/${editing.value.id}`, body)
    else await api.post('/campaigns', body)
    toast.success('Campaign saved.')
    editing.value = null
    await refresh()
  } catch (e) {
    if (e instanceof ApiError) errors.value = e.fieldErrors
    toast.error((e as Error).message)
  } finally { saving.value = false }
}

async function remove(c: Campaign) {
  if (!(await confirm({ title: `Delete “${c.name}”?`, message: 'Its QR codes are kept and simply removed from the campaign.', confirmLabel: 'Delete', tone: 'danger' }))) return
  await api.del(`/campaigns/${c.id}`)
  await refresh()
}
const maxScans = computed(() => Math.max(1, ...comparison.value.map(c => c.scans)))
</script>

<template>
  <div>
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 class="text-2xl font-extrabold sm:text-3xl">Campaigns</h1>
        <p class="mt-1 text-muted">Group codes by placement or promotion and compare their performance.</p>
      </div>
      <UiButton v-if="allows('campaigns')" @click="openNew"><Plus class="size-4" /> New campaign</UiButton>
    </div>

    <UiEmpty v-if="usage && !allows('campaigns')" class="mt-8" title="Campaigns are a paid feature" description="Upgrade to group codes and compare how placements perform.">
      <template #icon><Lock class="size-6" /></template>
      <UiButton to="/pricing">See plans</UiButton>
    </UiEmpty>

    <template v-else>
      <section class="card mt-6 p-6">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
          <h2 class="font-bold">Comparison</h2>
          <DashboardDateRange v-model:from="from" v-model:to="to" />
        </div>
        <ul v-if="comparison.length" class="space-y-3">
          <li v-for="c in comparison" :key="c.campaignId ?? 0">
            <div class="mb-1 flex items-center justify-between text-sm">
              <span class="flex items-center gap-2 font-semibold"><span class="size-2.5 rounded-full" :style="{ background: c.color ?? '#D3D8CA' }" />{{ c.name }} <span class="font-normal text-muted">· {{ c.codes }} codes</span></span>
              <span class="tabular-nums text-muted"><strong class="text-ink">{{ formatNumber(c.scans) }}</strong> scans · {{ formatNumber(c.uniqueScans) }} unique</span>
            </div>
            <div class="h-2.5 overflow-hidden rounded-full bg-canvas"><div class="h-full rounded-full transition-[width] duration-700" :style="{ width: `${(c.scans / maxScans) * 100}%`, background: c.color ?? '#1DB45A' }" /></div>
          </li>
        </ul>
        <p v-else class="py-6 text-center text-sm text-muted">No scans in this period.</p>
      </section>

      <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <div v-for="c in campaigns" :key="c.id" class="card p-5">
          <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-2.5">
              <span class="size-3 rounded-full" :style="{ background: c.color ?? '#1DB45A' }" />
              <h3 class="font-bold">{{ c.name }}</h3>
            </div>
            <div class="flex gap-1">
              <button type="button" class="rounded-lg p-1.5 text-muted hover:bg-canvas hover:text-ink" :aria-label="`Edit ${c.name}`" @click="openEdit(c)"><Pencil class="size-4" /></button>
              <button type="button" class="rounded-lg p-1.5 text-muted hover:bg-canvas hover:text-danger" :aria-label="`Delete ${c.name}`" @click="remove(c)"><Trash2 class="size-4" /></button>
            </div>
          </div>
          <p v-if="c.description" class="mt-2 text-sm text-muted">{{ c.description }}</p>
          <div class="mt-4 flex gap-6 text-sm">
            <p><strong>{{ c.qrCount }}</strong> <span class="text-muted">codes</span></p>
            <p><strong>{{ formatNumber(c.totalScans) }}</strong> <span class="text-muted">all-time scans</span></p>
          </div>
          <NuxtLink :to="`/dashboard/qr-codes?campaign_id=${c.id}`" class="mt-4 inline-block text-sm font-semibold text-brand-700 hover:underline">View codes →</NuxtLink>
        </div>
      </div>
      <UiEmpty v-if="!campaigns.length" class="mt-6" title="No campaigns yet" description="Create a campaign, then assign codes to it from each code’s page." />
    </template>

    <UiModal :open="!!editing" :title="editing?.id ? 'Edit campaign' : 'New campaign'" @update:open="v => { if (!v) editing = null }">
      <form v-if="editing" id="campaign-form" class="space-y-4" @submit.prevent="save">
        <UiField label="Name" id="cp-name" :error="errors.name" required><input id="cp-name" v-model="editing.name" class="field-input" maxlength="120" required></UiField>
        <UiField label="Description" id="cp-desc" :error="errors.description"><textarea id="cp-desc" v-model="editing.description" rows="3" maxlength="500" class="field-input" /></UiField>
        <UiField label="Colour" id="cp-color"><input id="cp-color" v-model="editing.color" type="color" class="h-10 w-14 cursor-pointer rounded-lg border border-line-strong bg-white p-1"></UiField>
      </form>
      <template #footer>
        <UiButton variant="secondary" @click="editing = null">Cancel</UiButton>
        <UiButton type="submit" form="campaign-form" :loading="saving">Save</UiButton>
      </template>
    </UiModal>
  </div>
</template>
