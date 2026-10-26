<script setup lang="ts">
import type { Item, Paginated } from '~/types/api'
import { formatBytes, formatRelative } from '~/utils/format'

definePageMeta({ layout: 'admin', middleware: 'admin' })
useSeoMeta({ title: 'Moderation', robots: 'noindex' })

interface CodeRow { id: number; name: string; type: string; mode: string; status: string; shortUrl: string | null; moderationStatus: string; totalScans: number; openReports: number; destinations: { kind: string; url: string }[]; owner: { id: number; name: string; email: string }; createdAt: string }
interface ReportRow { id: number; reason: string; details: string | null; status: string; reporterEmail: string | null; resolutionNote: string | null; qr: { id: number; name: string; shortUrl: string | null; moderationStatus: string } | null; createdAt: string }
interface Storage { totalBytes: number; totalFiles: number; disk: string; byPurpose: { purpose: string; files: number; bytes: number }[]; topUsers: { id: number; email: string; files: number; bytes: number }[] }

const api = useApi()
const toast = useToast()
const { confirm } = useConfirm()
const tab = ref('reports')

const codes = useAdminList<CodeRow>('admin-mod-codes', '/admin/qr-codes', { moderation: '' })
const reportStatus = ref('open')
const { data: reports, refresh: refreshReports } = await useAsyncData('admin-reports', () => api.get<Paginated<ReportRow>>('/admin/abuse-reports', { status: reportStatus.value }), { server: false, watch: [reportStatus] })
const { data: storage } = await useAsyncData('admin-storage', async () => (await api.get<Item<Storage>>('/admin/storage')).data, { server: false })

async function moderate(id: number, status: 'clear' | 'flagged' | 'blocked') {
  const reason = await confirm({ title: status === 'blocked' ? 'Block this code?' : `Mark as ${status}?`, message: status === 'blocked' ? 'Scans show a “disabled” page immediately and the owner cannot edit it.' : 'The code will keep working.', confirmLabel: 'Confirm', tone: status === 'blocked' ? 'danger' : 'primary', requireReason: true })
  if (!reason) return
  try { await api.patch(`/admin/qr-codes/${id}/moderation`, { moderation_status: status, reason }); toast.success('Updated.'); await Promise.all([codes.refresh(), refreshReports()]) } catch (e) { toast.error((e as Error).message) }
}

async function resolve(r: ReportRow, status: 'resolved' | 'dismissed', block = false) {
  const note = await confirm({ title: status === 'dismissed' ? 'Dismiss report?' : block ? 'Resolve and block code?' : 'Resolve report?', message: 'Add a note for the audit trail.', confirmLabel: 'Save', tone: block ? 'danger' : 'primary', requireReason: true })
  if (!note) return
  try { await api.patch(`/admin/abuse-reports/${r.id}`, { status, resolution_note: note, block_code: block }); toast.success('Report updated.'); await refreshReports() } catch (e) { toast.error((e as Error).message) }
}
</script>

<template>
  <div>
    <h1 class="text-2xl font-extrabold sm:text-3xl">Moderation</h1>
    <UiTabs v-model="tab" class="mt-6" :tabs="[{ value: 'reports', label: 'Abuse reports' }, { value: 'codes', label: 'All QR codes' }, { value: 'storage', label: 'File storage' }]" />

    <template v-if="tab === 'reports'">
      <div class="card mt-4 p-3"><select v-model="reportStatus" class="field-input !w-auto" aria-label="Report status"><option value="open">Open</option><option value="resolved">Resolved</option><option value="dismissed">Dismissed</option><option value="all">All</option></select></div>
      <div class="mt-4 space-y-3">
        <div v-for="r in reports?.data" :key="r.id" class="card p-5">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <p class="font-semibold capitalize">{{ r.reason }} <DashboardStatusBadge :status="r.status" /></p>
              <p class="mt-1 text-sm text-muted">{{ r.qr?.name }} · <span class="font-mono text-xs">{{ r.qr?.shortUrl }}</span> · reported {{ formatRelative(r.createdAt) }}<template v-if="r.reporterEmail"> by {{ r.reporterEmail }}</template></p>
              <p v-if="r.details" class="mt-2 text-sm">{{ r.details }}</p>
              <p v-if="r.resolutionNote" class="mt-2 text-xs text-muted">Resolution: {{ r.resolutionNote }}</p>
            </div>
            <div v-if="r.status === 'open'" class="flex flex-wrap gap-2">
              <UiButton size="sm" variant="secondary" @click="resolve(r, 'dismissed')">Dismiss</UiButton>
              <UiButton size="sm" variant="secondary" @click="resolve(r, 'resolved')">Resolve</UiButton>
              <UiButton size="sm" variant="danger" @click="resolve(r, 'resolved', true)">Block code</UiButton>
            </div>
          </div>
        </div>
        <UiEmpty v-if="reports && !reports.data.length" title="No reports" description="Nothing needs attention right now." />
      </div>
    </template>

    <template v-else-if="tab === 'codes'">
      <div class="card mt-4 flex flex-wrap gap-3 p-3">
        <input v-model="codes.search.value" type="search" class="field-input flex-1" placeholder="Short code, name or destination URL" aria-label="Search codes">
        <select v-model="codes.filters.moderation" class="field-input !w-auto" aria-label="Moderation"><option value="">Any status</option><option value="clear">Clear</option><option value="flagged">Flagged</option><option value="blocked">Blocked</option></select>
      </div>
      <div v-if="codes.data.value" class="card mt-4 overflow-x-auto">
        <table class="table-base min-w-[980px]">
          <thead><tr><th>Code</th><th>Owner</th><th>Destination</th><th>Moderation</th><th class="text-right">Scans</th><th /></tr></thead>
          <tbody>
            <tr v-for="c in codes.data.value.data" :key="c.id">
              <td><p class="font-medium">{{ c.name }}</p><p class="font-mono text-xs text-muted">{{ c.shortUrl ?? 'static' }} · {{ c.type }}</p></td>
              <td><NuxtLink :to="`/admin/customers/${c.owner.id}`" class="text-sm hover:text-brand-700">{{ c.owner.email }}</NuxtLink></td>
              <td class="max-w-xs"><p v-for="d in c.destinations.slice(0, 2)" :key="d.url" class="truncate font-mono text-xs text-muted" :title="d.url">{{ d.url }}</p></td>
              <td><DashboardStatusBadge :status="c.moderationStatus" /><UiBadge v-if="c.openReports" tone="amber">{{ c.openReports }} reports</UiBadge></td>
              <td class="text-right tabular-nums">{{ c.totalScans }}</td>
              <td class="whitespace-nowrap">
                <UiButton v-if="c.moderationStatus !== 'blocked'" size="sm" variant="ghost" class="!text-danger" @click="moderate(c.id, 'blocked')">Block</UiButton>
                <UiButton v-else size="sm" variant="ghost" @click="moderate(c.id, 'clear')">Unblock</UiButton>
              </td>
            </tr>
          </tbody>
        </table>
        <div class="px-4 pb-4"><UiPagination v-model="codes.page.value" :last-page="codes.data.value.meta.last_page" :total="codes.data.value.meta.total" /></div>
      </div>
    </template>

    <template v-else-if="storage">
      <div class="mt-4 grid gap-4 sm:grid-cols-3">
        <div class="card p-5"><p class="text-sm text-muted">Total stored</p><p class="mt-2 font-display text-3xl font-extrabold">{{ formatBytes(storage.totalBytes) }}</p><p class="text-xs text-muted">{{ storage.totalFiles }} files on “{{ storage.disk }}”</p></div>
        <div v-for="p in storage.byPurpose" :key="p.purpose" class="card p-5"><p class="text-sm capitalize text-muted">{{ p.purpose }} files</p><p class="mt-2 font-display text-3xl font-extrabold">{{ formatBytes(p.bytes) }}</p><p class="text-xs text-muted">{{ p.files }} files</p></div>
      </div>
      <div class="card mt-4 overflow-x-auto">
        <table class="table-base"><thead><tr><th>Customer</th><th class="text-right">Files</th><th class="text-right">Storage</th></tr></thead>
          <tbody><tr v-for="u in storage.topUsers" :key="u.id"><td><NuxtLink :to="`/admin/customers/${u.id}`" class="hover:text-brand-700">{{ u.email }}</NuxtLink></td><td class="text-right">{{ u.files }}</td><td class="text-right">{{ formatBytes(u.bytes) }}</td></tr></tbody>
        </table>
      </div>
    </template>
  </div>
</template>
