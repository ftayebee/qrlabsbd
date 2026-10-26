<script setup lang="ts">
import { ArrowLeft, Copy, CopyPlus, ExternalLink, Pause, Pencil, Play, Archive, Trash2, History } from 'lucide-vue-next'
import type { AnalyticsReport, Campaign, Folder, Item } from '~/types/api'
import type { QrCodeRecord } from '~/types/qr'
import { DEFAULT_DESIGN } from '~/types/qr'
import { QR_CATALOG } from '~/utils/qr/catalog'
import { formatDateTime, formatRelative } from '~/utils/format'

definePageMeta({ layout: 'dashboard', middleware: 'auth' })

const route = useRoute()
const id = Number(route.params.id)
const api = useApi()
const toast = useToast()
const { confirm } = useConfirm()
const { refresh: refreshUsage, allows } = useUsage()

const { data: qr, error, refresh } = await useAsyncData(`qr-${id}`, async () => (await api.get<Item<QrCodeRecord>>(`/qr-codes/${id}`)).data, { server: false })
useSeoMeta({ title: () => qr.value?.name ?? 'QR code', robots: 'noindex' })
const design = computed(() => ({ ...DEFAULT_DESIGN, ...(qr.value?.design ?? {}) }))

const iso = (d: Date) => d.toISOString().slice(0, 10)
const from = ref(iso(new Date(Date.now() - 29 * 86400000)))
const to = ref(iso(new Date()))
const { data: report, status: reportStatus } = await useAsyncData(`qr-${id}-analytics`, async () => {
  if (qr.value?.mode !== 'dynamic') return null
  return (await api.get<Item<AnalyticsReport>>(`/analytics/qr-codes/${id}`, { from: from.value, to: to.value })).data
}, { server: false, watch: [from, to, () => qr.value?.mode] })

const { data: history } = await useAsyncData(`qr-${id}-history`, async () => (await api.get<{ data: { id: number; summary: string; user: string | null; createdAt: string }[] }>(`/qr-codes/${id}/history`)).data, { server: false, default: () => [] })
const { data: folders } = await useAsyncData('folders', async () => (await api.get<{ data: Folder[] }>('/folders')).data, { server: false, default: () => [] })
const { data: campaigns } = await useAsyncData('campaigns-list', async () => (await api.get<{ data: Campaign[] }>('/campaigns')).data, { server: false, default: () => [] })

async function copy(text: string) {
  try { await navigator.clipboard.writeText(text); toast.success('Copied to clipboard.') } catch { toast.error('Copy failed.') }
}

async function setStatus(status: 'active' | 'paused' | 'archived') {
  try {
    await api.patch(`/qr-codes/${id}/status`, { status })
    toast.success(status === 'active' ? 'Code is live.' : status === 'paused' ? 'Code paused. Scans show a “paused” page.' : 'Code archived.')
    await refresh()
    refreshUsage().catch(() => {})
  } catch (e) { toast.error((e as Error).message) }
}

async function duplicate() {
  try {
    const res = await api.post<Item<QrCodeRecord>>(`/qr-codes/${id}/duplicate`)
    toast.success('Duplicated.')
    await navigateTo(`/dashboard/qr-codes/${res.data.id}`)
  } catch (e) { toast.error((e as Error).message) }
}

async function remove() {
  const ok = await confirm({
    title: 'Delete this QR code?',
    message: qr.value?.mode === 'dynamic' ? 'Printed copies will stop working immediately. This cannot be undone.' : 'It will be removed from your library.',
    confirmLabel: 'Delete', tone: 'danger',
  })
  if (!ok) return
  await api.del(`/qr-codes/${id}`)
  toast.success('QR code deleted.')
  refreshUsage().catch(() => {})
  await navigateTo('/dashboard/qr-codes')
}

async function assign(field: 'folder_id' | 'campaign_id', value: string) {
  try {
    await api.patch(`/qr-codes/${id}`, { [field]: value ? Number(value) : null })
    await refresh()
    toast.success('Saved.')
  } catch (e) { toast.error((e as Error).message) }
}

const exporting = ref(false)
async function exportCsv() {
  exporting.value = true
  try {
    const res = await fetch(`/api/v1/analytics/export?qr_code_id=${id}&from=${from.value}&to=${to.value}`, { credentials: 'include' })
    if (!res.ok) throw new Error(res.status === 402 ? 'CSV export is available on paid plans.' : 'Export failed.')
    const url = URL.createObjectURL(await res.blob())
    Object.assign(document.createElement('a'), { href: url, download: `scans-${id}.csv` }).click()
    URL.revokeObjectURL(url)
  } catch (e) { toast.error((e as Error).message) } finally { exporting.value = false }
}
</script>

<template>
  <div>
    <NuxtLink to="/dashboard/qr-codes" class="inline-flex items-center gap-1.5 text-sm font-semibold text-muted hover:text-ink"><ArrowLeft class="size-4" /> QR codes</NuxtLink>

    <UiAlert v-if="error" tone="danger" class="mt-6">This QR code could not be found, or you do not have access to it.</UiAlert>
    <UiSpinner v-else-if="!qr" />

    <template v-else>
      <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
          <h1 class="truncate text-2xl font-extrabold sm:text-3xl">{{ qr.name }}</h1>
          <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-muted">
            <span class="flex items-center gap-1.5"><QrTypeIcon :type="qr.type" class="size-4" /> {{ QR_CATALOG[qr.type].label }}</span>
            <DashboardStatusBadge :status="qr.status" />
            <UiBadge :tone="qr.mode === 'dynamic' ? 'green' : 'neutral'">{{ qr.mode }}</UiBadge>
            <DashboardStatusBadge v-if="qr.moderationStatus && qr.moderationStatus !== 'clear'" :status="qr.moderationStatus" />
          </div>
        </div>
        <div class="flex flex-wrap gap-2">
          <UiButton v-if="qr.canEdit" :to="`/dashboard/qr-codes/${qr.id}/edit`" variant="dark"><Pencil class="size-4" /> {{ qr.mode === 'dynamic' ? 'Edit destination' : 'Edit' }}</UiButton>
          <UiButton variant="secondary" @click="duplicate"><CopyPlus class="size-4" /> Duplicate</UiButton>
          <template v-if="qr.mode === 'dynamic'">
            <UiButton v-if="qr.status === 'active'" variant="secondary" @click="setStatus('paused')"><Pause class="size-4" /> Pause</UiButton>
            <UiButton v-else variant="secondary" @click="setStatus('active')"><Play class="size-4" /> {{ qr.status === 'archived' ? 'Restore' : 'Resume' }}</UiButton>
          </template>
          <UiButton v-if="qr.status !== 'archived'" variant="ghost" @click="setStatus('archived')"><Archive class="size-4" /> Archive</UiButton>
          <UiButton variant="ghost" class="!text-danger" @click="remove"><Trash2 class="size-4" /> Delete</UiButton>
        </div>
      </div>

      <UiAlert v-if="qr.readOnlyReason" tone="warning" class="mt-6">{{ qr.readOnlyReason }}</UiAlert>

      <div class="mt-6 grid gap-6 lg:grid-cols-[300px_minmax(0,1fr)] 2xl:grid-cols-[340px_minmax(0,1fr)]">
        <aside class="space-y-6">
          <div class="card p-5">
            <div class="grid place-items-center rounded-2xl bg-canvas p-4">
              <QrPreview v-if="qr.encodedValue" :value="qr.encodedValue" :design="design" :size="260" />
            </div>
            <div v-if="qr.shortUrl" class="mt-4 flex items-center gap-2 rounded-xl bg-canvas px-3 py-2">
              <p class="min-w-0 flex-1 truncate font-mono text-xs">{{ qr.shortUrl }}</p>
              <button type="button" class="rounded-lg p-1.5 text-muted hover:bg-white hover:text-ink" aria-label="Copy short link" @click="copy(qr.shortUrl!)"><Copy class="size-4" /></button>
              <a :href="qr.shortUrl" target="_blank" rel="noopener" class="rounded-lg p-1.5 text-muted hover:bg-white hover:text-ink" aria-label="Open short link"><ExternalLink class="size-4" /></a>
            </div>
            <div class="mt-4">
              <QrDownloadButtons v-if="qr.encodedValue" :value="qr.encodedValue" :design="design" :name="qr.name" :vector-allowed="allows('vector_downloads')" />
            </div>
          </div>

          <div class="card space-y-4 p-5">
            <UiField label="Folder" id="d-folder">
              <select id="d-folder" :value="qr.folderId ? String(qr.folderId) : ''" class="field-input" :disabled="!qr.canEdit" @change="assign('folder_id', ($event.target as HTMLSelectElement).value)">
                <option value="">No folder</option><option v-for="f in folders" :key="f.id" :value="String(f.id)">{{ f.name }}</option>
              </select>
            </UiField>
            <UiField label="Campaign" id="d-campaign" :hint="campaigns.length ? undefined : 'Create campaigns to compare groups of codes.'">
              <select id="d-campaign" :value="qr.campaignId ? String(qr.campaignId) : ''" class="field-input" :disabled="!qr.canEdit" @change="assign('campaign_id', ($event.target as HTMLSelectElement).value)">
                <option value="">No campaign</option><option v-for="c in campaigns" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
              </select>
            </UiField>
            <dl class="grid grid-cols-2 gap-3 border-t border-line pt-4 text-xs">
              <div><dt class="text-muted">Created</dt><dd class="mt-0.5 font-medium">{{ formatDateTime(qr.createdAt) }}</dd></div>
              <div><dt class="text-muted">Last scan</dt><dd class="mt-0.5 font-medium">{{ formatRelative(qr.lastScannedAt) }}</dd></div>
            </dl>
          </div>

          <div class="card p-5">
            <h2 class="mb-3 flex items-center gap-2 text-sm font-bold"><History class="size-4 text-muted" /> Change history</h2>
            <ol class="space-y-3 text-sm">
              <li v-for="h in history" :key="h.id" class="flex justify-between gap-3">
                <span>{{ h.summary }}<span v-if="h.user" class="text-muted"> · {{ h.user }}</span></span>
                <time class="shrink-0 text-xs text-muted" :datetime="h.createdAt">{{ formatRelative(h.createdAt) }}</time>
              </li>
            </ol>
          </div>
        </aside>

        <section class="min-w-0">
          <template v-if="qr.mode === 'dynamic'">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
              <h2 class="text-lg font-bold">Analytics</h2>
              <div class="flex flex-wrap items-center gap-2">
                <DashboardDateRange v-model:from="from" v-model:to="to" />
                <UiButton size="sm" variant="secondary" :loading="exporting" @click="exportCsv">CSV</UiButton>
              </div>
            </div>
            <UiSpinner v-if="reportStatus === 'pending' && !report" />
            <DashboardAnalyticsPanels v-else-if="report" :report="report" />
          </template>
          <UiEmpty v-else title="Static codes are not tracked" description="The content is stored directly in the code, so scans never reach our servers. Create a dynamic version to edit and measure it.">
            <UiButton :to="`/dashboard/qr-codes/new?type=${qr.type}`" variant="secondary">Create a dynamic code</UiButton>
          </UiEmpty>
        </section>
      </div>
    </template>
  </div>
</template>
