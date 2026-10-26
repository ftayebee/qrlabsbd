<script setup lang="ts">
import { Archive, Download, FolderInput, FolderPlus, LayoutGrid, List, MoreHorizontal, Plus, Search, Trash2 } from 'lucide-vue-next'
import type { Campaign, Folder, Paginated } from '~/types/api'
import type { QrCodeRecord } from '~/types/qr'
import { QR_TYPE_LIST, QR_CATALOG } from '~/utils/qr/catalog'
import { formatNumber, formatRelative } from '~/utils/format'

definePageMeta({ layout: 'dashboard', middleware: 'auth' })
useSeoMeta({ title: 'QR codes', robots: 'noindex' })

const api = useApi()
const toast = useToast()
const { confirm } = useConfirm()
const route = useRoute()
const router = useRouter()

const filters = reactive({
  search: String(route.query.search ?? ''),
  type: String(route.query.type ?? ''),
  mode: String(route.query.mode ?? ''),
  status: String(route.query.status ?? ''),
  folder_id: String(route.query.folder_id ?? ''),
  campaign_id: String(route.query.campaign_id ?? ''),
  sort: String(route.query.sort ?? '-created_at'),
  page: Number(route.query.page ?? 1),
})
const view = ref<'grid' | 'list'>('grid')
onMounted(() => { try { view.value = (localStorage.getItem('qrio:view') as 'grid' | 'list') || 'grid' } catch { /* ignore */ } })
watch(view, v => { try { localStorage.setItem('qrio:view', v) } catch { /* ignore */ } })

const query = computed(() => Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== '' && v !== 0)))
const { data, status, refresh } = await useAsyncData('qr-library', () => api.get<Paginated<QrCodeRecord>>('/qr-codes', { ...query.value, per_page: 24 }), { server: false, watch: [query] })
watch(query, q => router.replace({ query: q as Record<string, string> }))

let searchTimer: ReturnType<typeof setTimeout> | undefined
const searchInput = ref(filters.search)
watch(searchInput, (v) => { clearTimeout(searchTimer); searchTimer = setTimeout(() => { filters.search = v; filters.page = 1 }, 300) })
watch(() => [filters.type, filters.mode, filters.status, filters.folder_id, filters.campaign_id, filters.sort], () => { filters.page = 1 })

const { data: folders, refresh: refreshFolders } = await useAsyncData('folders', async () => (await api.get<{ data: Folder[] }>('/folders')).data, { server: false, default: () => [] })
const { data: campaigns } = await useAsyncData('campaigns-list', async () => (await api.get<{ data: Campaign[] }>('/campaigns')).data, { server: false, default: () => [] })

const selected = ref<number[]>([])
const allSelected = computed(() => !!data.value?.data.length && data.value.data.every(q => selected.value.includes(q.id)))
function toggleAll() { selected.value = allSelected.value ? [] : (data.value?.data.map(q => q.id) ?? []) }

async function bulk(action: 'archive' | 'restore' | 'delete' | 'move', folderId?: number | null) {
  if (action === 'delete' && !(await confirm({ title: `Delete ${selected.value.length} codes?`, message: 'Deleted dynamic codes stop working immediately and cannot be restored.', confirmLabel: 'Delete', tone: 'danger' }))) return
  try {
    const res = await api.post<{ message: string }>('/qr-codes/bulk', { ids: selected.value, action, folder_id: folderId ?? null })
    toast.success(res.message)
    selected.value = []
    await Promise.all([refresh(), refreshFolders()])
  } catch (e) { toast.error((e as Error).message) }
}

const newFolderOpen = ref(false)
const newFolder = ref('')
async function createFolder() {
  try {
    await api.post('/folders', { name: newFolder.value })
    newFolder.value = ''
    newFolderOpen.value = false
    await refreshFolders()
  } catch (e) { toast.error((e as Error).message) }
}
async function deleteFolder(f: Folder) {
  if (!(await confirm({ title: `Delete folder “${f.name}”?`, message: 'QR codes inside are kept and moved out of the folder.', confirmLabel: 'Delete folder', tone: 'danger' }))) return
  await api.del(`/folders/${f.id}`)
  if (filters.folder_id === String(f.id)) filters.folder_id = ''
  await Promise.all([refreshFolders(), refresh()])
}

const moveOpen = ref(false)
const moveTarget = ref<string>('')
const exporting = ref(false)
async function exportCsv() {
  exporting.value = true
  try {
    const res = await fetch('/api/v1/qr-codes/export', { credentials: 'include', headers: { Accept: 'text/csv' } })
    if (!res.ok) throw new Error(res.status === 402 ? 'CSV export is available on paid plans.' : 'Export failed.')
    const url = URL.createObjectURL(await res.blob())
    const a = Object.assign(document.createElement('a'), { href: url, download: `qr-codes-${new Date().toISOString().slice(0, 10)}.csv` })
    a.click()
    URL.revokeObjectURL(url)
  } catch (e) { toast.error((e as Error).message) } finally { exporting.value = false }
}
</script>

<template>
  <div>
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 class="text-2xl font-extrabold sm:text-3xl">QR codes</h1>
        <p class="mt-1 text-muted">{{ data?.meta.total ?? 0 }} codes in your library</p>
      </div>
      <div class="flex gap-2">
        <UiButton variant="secondary" :loading="exporting" @click="exportCsv"><Download class="size-4" /> Export CSV</UiButton>
        <UiButton to="/dashboard/qr-codes/new"><Plus class="size-4" /> New code</UiButton>
      </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[220px_1fr]">
      <!-- Folders -->
      <aside class="card h-fit p-3" aria-label="Folders">
        <div class="flex items-center justify-between px-2 py-1.5">
          <p class="text-xs font-bold uppercase tracking-wider text-muted">Folders</p>
          <button type="button" class="rounded-lg p-1 text-muted hover:bg-canvas hover:text-ink" aria-label="New folder" @click="newFolderOpen = true"><FolderPlus class="size-4" /></button>
        </div>
        <div class="flex gap-1 overflow-x-auto xl:flex-col">
          <button type="button" :class="['rounded-lg px-3 py-2 text-left text-sm font-medium whitespace-nowrap', !filters.folder_id ? 'bg-soft text-charcoal' : 'text-muted hover:bg-canvas']" @click="filters.folder_id = ''">All codes</button>
          <div v-for="f in folders" :key="f.id" class="group flex items-center">
            <button type="button" :class="['flex-1 rounded-lg px-3 py-2 text-left text-sm font-medium whitespace-nowrap', filters.folder_id === String(f.id) ? 'bg-soft text-charcoal' : 'text-muted hover:bg-canvas']" @click="filters.folder_id = String(f.id)">
              {{ f.name }} <span class="text-xs opacity-60">{{ f.qrCount }}</span>
            </button>
            <button type="button" class="hidden rounded p-1 text-muted hover:text-danger group-hover:block" :aria-label="`Delete folder ${f.name}`" @click="deleteFolder(f)"><Trash2 class="size-3.5" /></button>
          </div>
        </div>
      </aside>

      <div class="min-w-0">
        <!-- Filters -->
        <div class="card flex flex-wrap items-center gap-3 p-3">
          <div class="relative min-w-48 flex-1">
            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" />
            <label for="q-search" class="sr-only">Search</label>
            <input id="q-search" v-model="searchInput" type="search" class="field-input !pl-9" placeholder="Search by name or short code">
          </div>
          <select v-model="filters.type" class="field-input !w-auto" aria-label="Type"><option value="">All types</option><option v-for="t in QR_TYPE_LIST" :key="t.type" :value="t.type">{{ t.label }}</option></select>
          <select v-model="filters.mode" class="field-input !w-auto" aria-label="Mode"><option value="">Static &amp; dynamic</option><option value="static">Static</option><option value="dynamic">Dynamic</option></select>
          <select v-model="filters.status" class="field-input !w-auto" aria-label="Status"><option value="">Active &amp; paused</option><option value="active">Active</option><option value="paused">Paused</option><option value="archived">Archived</option><option value="all">All</option></select>
          <select v-if="campaigns.length" v-model="filters.campaign_id" class="field-input !w-auto" aria-label="Campaign"><option value="">All campaigns</option><option v-for="c in campaigns" :key="c.id" :value="String(c.id)">{{ c.name }}</option></select>
          <select v-model="filters.sort" class="field-input !w-auto" aria-label="Sort"><option value="-created_at">Newest</option><option value="created_at">Oldest</option><option value="-total_scans">Most scanned</option><option value="name">Name A–Z</option><option value="-last_scanned_at">Recently scanned</option></select>
          <div class="ml-auto flex rounded-xl border border-line p-0.5">
            <button type="button" :class="['rounded-lg p-1.5', view === 'grid' ? 'bg-canvas text-ink' : 'text-muted']" aria-label="Grid view" :aria-pressed="view === 'grid'" @click="view = 'grid'"><LayoutGrid class="size-4" /></button>
            <button type="button" :class="['rounded-lg p-1.5', view === 'list' ? 'bg-canvas text-ink' : 'text-muted']" aria-label="List view" :aria-pressed="view === 'list'" @click="view = 'list'"><List class="size-4" /></button>
          </div>
        </div>

        <!-- Bulk bar -->
        <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0 -translate-y-1" leave-active-class="transition duration-150" leave-to-class="opacity-0">
          <div v-if="selected.length" class="mt-3 flex flex-wrap items-center gap-2 rounded-2xl bg-charcoal px-4 py-2.5 text-sm text-white">
            <span class="font-semibold">{{ selected.length }} selected</span>
            <span class="flex-1" />
            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 hover:bg-white/10" @click="moveOpen = true"><FolderInput class="size-4" /> Move</button>
            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 hover:bg-white/10" @click="bulk(filters.status === 'archived' ? 'restore' : 'archive')"><Archive class="size-4" /> {{ filters.status === 'archived' ? 'Restore' : 'Archive' }}</button>
            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-orange-200 hover:bg-white/10" @click="bulk('delete')"><Trash2 class="size-4" /> Delete</button>
          </div>
        </Transition>

        <UiSpinner v-if="status === 'pending' && !data" />
        <UiEmpty v-else-if="!data?.data.length" class="mt-6" :title="filters.search || filters.type ? 'No codes match these filters' : 'No QR codes yet'" description="Create a code, then find it here to edit, download or track it.">
          <UiButton to="/dashboard/qr-codes/new"><Plus class="size-4" /> Create a QR code</UiButton>
        </UiEmpty>

        <template v-else>
          <div v-if="view === 'grid'" class="mt-4 grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
            <div v-for="qr in data.data" :key="qr.id" :class="['card group relative p-4 transition-shadow hover:shadow-[var(--shadow-lift)]', selected.includes(qr.id) ? 'ring-2 ring-brand' : '']">
              <input v-model="selected" type="checkbox" :value="qr.id" class="absolute right-4 top-4 z-10 size-4 accent-[var(--color-brand)]" :aria-label="`Select ${qr.name}`">
              <NuxtLink :to="`/dashboard/qr-codes/${qr.id}`" class="flex gap-4">
                <DashboardQrThumb :qr="qr" :size="88" />
                <div class="min-w-0 flex-1 pr-6">
                  <p class="truncate font-semibold">{{ qr.name }}</p>
                  <p class="mt-0.5 flex items-center gap-1.5 text-xs text-muted"><QrTypeIcon :type="qr.type" class="size-3.5" /> {{ QR_CATALOG[qr.type].label }}</p>
                  <div class="mt-2 flex flex-wrap gap-1.5">
                    <DashboardStatusBadge :status="qr.status" />
                    <UiBadge :tone="qr.mode === 'dynamic' ? 'green' : 'neutral'">{{ qr.mode }}</UiBadge>
                  </div>
                </div>
              </NuxtLink>
              <div class="mt-4 flex items-center justify-between border-t border-line pt-3 text-xs text-muted">
                <span v-if="qr.mode === 'dynamic'"><strong class="text-ink">{{ formatNumber(qr.totalScans) }}</strong> scans · last {{ formatRelative(qr.lastScannedAt) }}</span>
                <span v-else>Static · not tracked</span>
                <span v-if="qr.campaign" class="flex items-center gap-1"><span class="size-2 rounded-full" :style="{ background: qr.campaign.color ?? '#1DB45A' }" />{{ qr.campaign.name }}</span>
              </div>
            </div>
          </div>

          <div v-else class="card mt-4 overflow-x-auto">
            <table class="table-base min-w-[720px]">
              <thead><tr>
                <th class="w-10"><input type="checkbox" :checked="allSelected" class="size-4 accent-[var(--color-brand)]" aria-label="Select all" @change="toggleAll"></th>
                <th>Code</th><th>Type</th><th>Status</th><th class="text-right">Scans</th><th>Created</th><th class="w-10"><span class="sr-only">Actions</span></th>
              </tr></thead>
              <tbody>
                <tr v-for="qr in data.data" :key="qr.id">
                  <td><input v-model="selected" type="checkbox" :value="qr.id" class="size-4 accent-[var(--color-brand)]" :aria-label="`Select ${qr.name}`"></td>
                  <td><NuxtLink :to="`/dashboard/qr-codes/${qr.id}`" class="flex items-center gap-3 font-semibold hover:text-brand-700"><DashboardQrThumb :qr="qr" :size="40" />{{ qr.name }}</NuxtLink></td>
                  <td class="text-muted">{{ QR_CATALOG[qr.type].label }} · {{ qr.mode }}</td>
                  <td><DashboardStatusBadge :status="qr.status" /></td>
                  <td class="text-right tabular-nums">{{ qr.mode === 'dynamic' ? formatNumber(qr.totalScans) : '—' }}</td>
                  <td class="text-muted">{{ formatRelative(qr.createdAt) }}</td>
                  <td><NuxtLink :to="`/dashboard/qr-codes/${qr.id}`" class="rounded-lg p-1.5 text-muted hover:bg-canvas" :aria-label="`Open ${qr.name}`"><MoreHorizontal class="size-4" /></NuxtLink></td>
                </tr>
              </tbody>
            </table>
          </div>
          <UiPagination v-model="filters.page" :last-page="data.meta.last_page" :total="data.meta.total" :from="data.meta.from" :to="data.meta.to" />
        </template>
      </div>
    </div>

    <UiModal v-model:open="newFolderOpen" title="New folder" size="sm">
      <form id="new-folder" @submit.prevent="createFolder"><UiField label="Folder name" id="nf-name"><input id="nf-name" v-model="newFolder" class="field-input" maxlength="100" required></UiField></form>
      <template #footer><UiButton variant="secondary" @click="newFolderOpen = false">Cancel</UiButton><UiButton type="submit" form="new-folder" :disabled="!newFolder.trim()">Create</UiButton></template>
    </UiModal>
    <UiModal v-model:open="moveOpen" title="Move to folder" size="sm">
      <UiField label="Folder" id="mv-folder">
        <select id="mv-folder" v-model="moveTarget" class="field-input"><option value="">No folder</option><option v-for="f in folders" :key="f.id" :value="String(f.id)">{{ f.name }}</option></select>
      </UiField>
      <template #footer><UiButton variant="secondary" @click="moveOpen = false">Cancel</UiButton><UiButton @click="bulk('move', moveTarget ? Number(moveTarget) : null); moveOpen = false">Move</UiButton></template>
    </UiModal>
  </div>
</template>
