<script setup lang="ts">
import { Search } from 'lucide-vue-next'
import { formatDate, formatRelative } from '~/utils/format'

definePageMeta({ layout: 'admin', middleware: 'admin' })
useSeoMeta({ title: 'Customers', robots: 'noindex' })

interface Row { id: number; name: string; email: string; status: string; emailVerified: boolean; plan: string; subscriptionStatus: string | null; qrCount: number; roles: string[]; lastLoginAt: string | null; createdAt: string }
const { data, status, filters, page, search } = useAdminList<Row>('admin-customers', '/admin/customers', { status: '', plan: '' })
const api = useApi()
const { data: plans } = await useAsyncData('admin-plan-options', async () => (await api.get<{ data: { id: number; slug: string; name: string }[] }>('/admin/plans')).data, { server: false, default: () => [] })
</script>

<template>
  <div>
    <h1 class="text-2xl font-extrabold sm:text-3xl">Customers</h1>
    <div class="card mt-6 flex flex-wrap gap-3 p-3">
      <div class="relative min-w-56 flex-1">
        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" />
        <input v-model="search" type="search" class="field-input !pl-9" placeholder="Search name, email or ID" aria-label="Search customers">
      </div>
      <select v-model="filters.status" class="field-input !w-auto" aria-label="Status"><option value="">All statuses</option><option value="active">Active</option><option value="suspended">Suspended</option></select>
      <select v-model="filters.plan" class="field-input !w-auto" aria-label="Plan"><option value="">All plans</option><option v-for="p in plans" :key="p.id" :value="p.slug">{{ p.name }}</option></select>
    </div>
    <UiSpinner v-if="status === 'pending' && !data" />
    <div v-else-if="data" class="card mt-4 overflow-x-auto">
      <table class="table-base min-w-[860px]">
        <thead><tr><th>Customer</th><th>Plan</th><th>Status</th><th class="text-right">Codes</th><th>Last sign-in</th><th>Joined</th></tr></thead>
        <tbody>
          <tr v-for="u in data.data" :key="u.id">
            <td>
              <NuxtLink :to="`/admin/customers/${u.id}`" class="font-semibold hover:text-brand-700">{{ u.name }}</NuxtLink>
              <p class="text-xs text-muted">{{ u.email }} <span v-if="!u.emailVerified" class="text-warning">· unverified</span></p>
            </td>
            <td>{{ u.plan }} <DashboardStatusBadge v-if="u.subscriptionStatus" :status="u.subscriptionStatus" /></td>
            <td><DashboardStatusBadge :status="u.status" /> <UiBadge v-for="r in u.roles.filter(r => r !== 'customer')" :key="r" tone="dark">{{ r }}</UiBadge></td>
            <td class="text-right tabular-nums">{{ u.qrCount }}</td>
            <td class="text-muted">{{ formatRelative(u.lastLoginAt) }}</td>
            <td class="text-muted">{{ formatDate(u.createdAt) }}</td>
          </tr>
        </tbody>
      </table>
      <p v-if="!data.data.length" class="p-6 text-center text-sm text-muted">No customers match.</p>
      <div class="px-4 pb-4"><UiPagination v-model="page" :last-page="data.meta.last_page" :total="data.meta.total" /></div>
    </div>
  </div>
</template>
