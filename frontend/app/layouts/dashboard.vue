<script setup lang="ts">
import { BarChart3, CreditCard, FolderKanban, LayoutDashboard, Plus, QrCode, Settings, Users } from 'lucide-vue-next'
import { limitLabel } from '~/utils/format'

const { usage, refresh } = useUsage()
onMounted(() => { if (!usage.value) refresh().catch(() => {}) })

const nav = [
  { label: 'Overview', to: '/dashboard', icon: LayoutDashboard, exact: true },
  { label: 'QR codes', to: '/dashboard/qr-codes', icon: QrCode },
  { label: 'Campaigns', to: '/dashboard/campaigns', icon: FolderKanban },
  { label: 'Analytics', to: '/dashboard/analytics', icon: BarChart3 },
  { label: 'Billing', to: '/dashboard/billing', icon: CreditCard },
  { label: 'Team', to: '/dashboard/team', icon: Users },
  { label: 'Settings', to: '/dashboard/settings', icon: Settings },
]

const dynamicUsed = computed(() => usage.value?.usage.dynamic_codes ?? 0)
const dynamicLimit = computed(() => Number(usage.value?.entitlements.dynamic_codes ?? 0))
const ratio = computed(() => (dynamicLimit.value > 0 ? Math.min(1, dynamicUsed.value / dynamicLimit.value) : 0))
</script>

<template>
  <AppShell :nav="nav" title="Dashboard">
    <template #actions>
      <UiButton to="/dashboard/qr-codes/new" size="sm"><Plus class="size-4" /> <span class="hidden sm:inline">New QR code</span></UiButton>
    </template>
    <template #sidebar-footer>
      <div v-if="usage" class="rounded-2xl bg-canvas p-4">
        <div class="flex items-center justify-between">
          <p class="text-sm font-bold">{{ usage.plan.name }} plan</p>
          <NuxtLink v-if="usage.plan.slug === 'free'" to="/pricing" class="text-xs font-semibold text-brand-700 hover:underline">Upgrade</NuxtLink>
        </div>
        <p class="mt-3 text-xs text-muted">Dynamic codes · {{ dynamicUsed }} / {{ limitLabel(dynamicLimit) }}</p>
        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-line">
          <div class="h-full rounded-full transition-[width] duration-700" :class="ratio >= 1 ? 'bg-warning' : 'bg-brand'" :style="{ width: dynamicLimit < 0 ? '8%' : `${ratio * 100}%` }" />
        </div>
      </div>
    </template>
    <UiAlert v-if="usage?.readOnly" tone="warning" class="mb-6" title="Some codes are read-only">
      You have more dynamic codes than your plan allows. They keep redirecting, but editing is locked until you
      <NuxtLink to="/pricing" class="font-semibold underline">upgrade</NuxtLink> or archive codes.
    </UiAlert>
    <UiAlert v-else-if="usage?.inGrace" tone="info" class="mb-6" title="Your subscription has ended">
      You keep full access until {{ new Date(usage.graceEndsAt!).toLocaleDateString() }}. <NuxtLink to="/dashboard/billing" class="font-semibold underline">Renew</NuxtLink> to keep premium features.
    </UiAlert>
    <slot />
  </AppShell>
</template>
