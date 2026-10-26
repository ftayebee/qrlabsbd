<script setup lang="ts">
import { Copy, Plus } from 'lucide-vue-next'
import type { Plan } from '~/types/api'
import { formatMoney } from '~/utils/format'

definePageMeta({ layout: 'admin', middleware: 'admin' })
useSeoMeta({ title: 'Plans & pricing', robots: 'noindex' })

type AdminPlan = Plan & { status: string; isPublic: boolean; isDefault: boolean; subscribersCount: number | null }
const api = useApi()
const toast = useToast()
const { data: plans } = await useAsyncData('admin-plans', async () => (await api.get<{ data: AdminPlan[] }>('/admin/plans')).data, { server: false, default: () => [] })

async function duplicate(p: AdminPlan) {
  try {
    const res = await api.post<{ data: { id: number } }>(`/admin/plans/${p.id}/duplicate`)
    toast.success('Plan duplicated as a draft.')
    await navigateTo(`/admin/plans/${res.data.id}`)
  } catch (e) { toast.error((e as Error).message) }
}
</script>

<template>
  <div>
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 class="text-2xl font-extrabold sm:text-3xl">Plans &amp; pricing</h1>
        <p class="mt-1 text-muted">Changes apply to new subscribers. Existing subscribers keep their current price.</p>
      </div>
      <UiButton to="/admin/plans/new"><Plus class="size-4" /> New plan</UiButton>
    </div>
    <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
      <div v-for="p in plans" :key="p.id" class="card flex flex-col p-5">
        <div class="flex items-start justify-between gap-2">
          <div>
            <h2 class="text-lg font-bold">{{ p.name }}</h2>
            <p class="text-xs text-muted">/{{ p.slug }} · order {{ p.sortOrder }}</p>
          </div>
          <div class="flex flex-wrap justify-end gap-1">
            <DashboardStatusBadge :status="p.status === 'published' ? 'active' : p.status === 'draft' ? 'pending' : 'archived'" />
            <UiBadge v-if="p.isDefault" tone="dark">Default</UiBadge>
            <UiBadge v-if="!p.isPublic" tone="amber">Hidden</UiBadge>
          </div>
        </div>
        <ul class="mt-4 flex-1 space-y-1 text-sm">
          <li v-for="pr in p.prices" :key="pr.id" class="flex justify-between">
            <span class="text-muted">{{ pr.currency }} / {{ pr.interval }}</span>
            <span class="tabular-nums">{{ formatMoney(pr.amount, pr.currency) }}<span v-if="pr.promoAmount !== null" class="text-brand-700"> → {{ formatMoney(pr.promoAmount, pr.currency) }}</span></span>
          </li>
          <li v-if="!p.prices.length" class="text-muted">{{ p.isCustom ? 'Custom pricing' : 'Free' }}</li>
        </ul>
        <p class="mt-4 text-xs text-muted">{{ p.subscribersCount ?? 0 }} active subscribers · {{ p.features.length }} features</p>
        <div class="mt-4 flex gap-2">
          <UiButton :to="`/admin/plans/${p.id}`" size="sm" variant="secondary">Edit</UiButton>
          <UiButton size="sm" variant="ghost" @click="duplicate(p)"><Copy class="size-4" /> Duplicate</UiButton>
        </div>
      </div>
    </div>
  </div>
</template>
