<script setup lang="ts">
import { BadgePercent, CreditCard, FileClock, FileText, LayoutDashboard, Layers, Settings, ShieldAlert, ShieldCheck, UserRound, Wallet } from 'lucide-vue-next'

const { can, user } = useAuth()
const all = [
  { label: 'Overview', to: '/admin', icon: LayoutDashboard, exact: true, perm: 'dashboard.view' },
  { label: 'Customers', to: '/admin/customers', icon: UserRound, perm: 'customers.view' },
  { label: 'Subscriptions', to: '/admin/subscriptions', icon: Wallet, perm: 'subscriptions.view' },
  { label: 'Plans & pricing', to: '/admin/plans', icon: Layers, perm: 'plans.manage' },
  { label: 'Coupons', to: '/admin/coupons', icon: BadgePercent, perm: 'coupons.manage' },
  { label: 'Payments', to: '/admin/payments', icon: CreditCard, perm: 'payments.view' },
  { label: 'Moderation', to: '/admin/moderation', icon: ShieldAlert, perm: 'moderation.manage' },
  { label: 'Content', to: '/admin/content', icon: FileText, perm: 'content.manage' },
  { label: 'Settings', to: '/admin/settings', icon: Settings, perm: 'settings.manage' },
  { label: 'Roles', to: '/admin/roles', icon: ShieldCheck, perm: 'roles.manage' },
  { label: 'Audit log', to: '/admin/audit-logs', icon: FileClock, perm: 'audit.view' },
]
// Navigation is filtered for convenience only; the API enforces every permission.
const isSuper = computed(() => user.value?.roles.includes('super-admin'))
const nav = computed(() => all.filter(i => isSuper.value || can(i.perm)))
</script>

<template>
  <AppShell :nav="nav" title="Administration" badge="Admin">
    <template #sidebar-footer>
      <NuxtLink to="/dashboard" class="block rounded-xl px-3 py-2 text-sm font-semibold text-muted hover:bg-canvas hover:text-ink">← Customer dashboard</NuxtLink>
    </template>
    <slot />
  </AppShell>
</template>
