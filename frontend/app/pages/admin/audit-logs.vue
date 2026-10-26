<script setup lang="ts">
import { formatDateTime } from '~/utils/format'

definePageMeta({ layout: 'admin', middleware: 'admin' })
useSeoMeta({ title: 'Audit log', robots: 'noindex' })

interface Log { id: number; action: string; subjectType: string | null; subjectId: number | null; reason: string | null; changes: Record<string, unknown> | null; actor: { id: number; name: string; email: string } | null; createdAt: string }
const { data, filters, page } = useAdminList<Log>('admin-audit', '/admin/audit-logs', { action: '', subject_type: '' })
const expanded = ref<number | null>(null)
</script>

<template>
  <div>
    <h1 class="text-2xl font-extrabold sm:text-3xl">Audit log</h1>
    <p class="mt-1 text-muted">Append-only record of administrative and security-relevant actions.</p>
    <div class="card mt-6 flex flex-wrap gap-3 p-3">
      <input v-model="filters.action" class="field-input flex-1" placeholder="Action prefix, e.g. subscription. or plan." aria-label="Action filter">
      <select v-model="filters.subject_type" class="field-input !w-auto" aria-label="Subject type"><option value="">All subjects</option><option v-for="t in ['User', 'Plan', 'Subscription', 'Coupon', 'Payment', 'QrCode', 'AbuseReport', 'Role', 'ContentPage', 'EmailTemplate']" :key="t" :value="t">{{ t }}</option></select>
    </div>
    <div v-if="data" class="card mt-4 overflow-x-auto">
      <table class="table-base min-w-[860px]">
        <thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Subject</th><th>Reason</th><th /></tr></thead>
        <tbody>
          <template v-for="l in data.data" :key="l.id">
            <tr>
              <td class="whitespace-nowrap text-muted">{{ formatDateTime(l.createdAt) }}</td>
              <td>{{ l.actor?.email ?? 'System' }}</td>
              <td class="font-mono text-xs">{{ l.action }}</td>
              <td class="text-muted">{{ l.subjectType }}<span v-if="l.subjectId"> #{{ l.subjectId }}</span></td>
              <td class="max-w-xs truncate" :title="l.reason ?? ''">{{ l.reason ?? '—' }}</td>
              <td><button v-if="l.changes" type="button" class="text-xs font-semibold text-brand-700 hover:underline" :aria-expanded="expanded === l.id" @click="expanded = expanded === l.id ? null : l.id">Details</button></td>
            </tr>
            <tr v-if="expanded === l.id"><td colspan="6" class="bg-canvas"><pre class="max-h-64 overflow-auto whitespace-pre-wrap text-xs">{{ JSON.stringify(l.changes, null, 2) }}</pre></td></tr>
          </template>
        </tbody>
      </table>
      <p v-if="!data.data.length" class="p-6 text-center text-sm text-muted">No entries.</p>
      <div class="px-4 pb-4"><UiPagination v-model="page" :last-page="data.meta.last_page" :total="data.meta.total" /></div>
    </div>
  </div>
</template>
