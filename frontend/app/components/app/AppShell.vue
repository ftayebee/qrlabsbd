<script setup lang="ts">
import { ChevronDown, LogOut, Menu, X } from 'lucide-vue-next'
import type { Component } from 'vue'

/** Shared application chrome for the dashboard and admin layouts. */
defineProps<{ nav: { label: string; to: string; icon: Component; exact?: boolean }[]; title: string; badge?: string }>()

const { user, logout } = useAuth()
const open = ref(false)
const menu = ref(false)
const route = useRoute()
watch(() => route.fullPath, () => { open.value = false; menu.value = false })

const isActive = (to: string, exact?: boolean) => (exact ? route.path === to : route.path === to || route.path.startsWith(`${to}/`))
const initials = computed(() => (user.value?.name ?? '?').split(' ').map(p => p[0]).join('').slice(0, 2).toUpperCase())

function onDocClick(e: MouseEvent) {
  if (!(e.target as HTMLElement).closest('[data-user-menu]')) menu.value = false
}
onMounted(() => document.addEventListener('click', onDocClick))
onBeforeUnmount(() => document.removeEventListener('click', onDocClick))
</script>

<template>
  <div class="min-h-dvh bg-canvas lg:grid lg:grid-cols-[256px_1fr]">
    <!-- Sidebar -->
    <Transition enter-active-class="transition-opacity duration-200" enter-from-class="opacity-0" leave-active-class="transition-opacity duration-150" leave-to-class="opacity-0">
      <div v-if="open" class="fixed inset-0 z-40 bg-charcoal/40 lg:hidden" @click="open = false" />
    </Transition>
    <aside :class="['fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-line bg-white transition-transform duration-300 ease-[var(--ease-out-quart)] lg:sticky lg:top-0 lg:h-dvh lg:translate-x-0', open ? 'translate-x-0' : '-translate-x-full']">
      <div class="flex h-16 items-center justify-between px-5">
        <NuxtLink to="/" aria-label="Home"><AppLogo /></NuxtLink>
        <UiBadge v-if="badge" tone="dark">{{ badge }}</UiBadge>
        <button type="button" class="rounded-lg p-1.5 text-muted lg:hidden" aria-label="Close menu" @click="open = false"><X class="size-5" /></button>
      </div>
      <nav class="flex-1 space-y-0.5 overflow-y-auto px-3 py-4" :aria-label="title">
        <NuxtLink
          v-for="item in nav" :key="item.to" :to="item.to"
          :class="['group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition-colors', isActive(item.to, item.exact) ? 'bg-soft text-charcoal' : 'text-muted hover:bg-canvas hover:text-ink']"
          :aria-current="isActive(item.to, item.exact) ? 'page' : undefined"
        >
          <span v-if="isActive(item.to, item.exact)" class="absolute left-0 top-2 bottom-2 w-1 rounded-r-full bg-brand" aria-hidden="true" />
          <component :is="item.icon" :class="['size-[18px]', isActive(item.to, item.exact) ? 'text-brand-700' : '']" aria-hidden="true" />
          {{ item.label }}
        </NuxtLink>
      </nav>
      <div class="border-t border-line p-3"><slot name="sidebar-footer" /></div>
    </aside>

    <div class="min-w-0">
      <header class="sticky top-0 z-30 flex h-16 items-center justify-between gap-4 border-b border-line bg-canvas/85 px-4 backdrop-blur-md sm:px-6 lg:px-8">
        <button type="button" class="-ml-1 rounded-lg p-2 lg:hidden" aria-label="Open menu" @click="open = true"><Menu class="size-5" /></button>
        <div class="hidden text-sm font-semibold text-muted lg:block">{{ title }}</div>
        <div class="flex items-center gap-3">
          <slot name="actions" />
          <div class="relative" data-user-menu>
            <button type="button" class="flex items-center gap-2 rounded-xl p-1 pr-2 hover:bg-white" :aria-expanded="menu" aria-haspopup="menu" @click="menu = !menu">
              <span class="grid size-8 place-items-center rounded-lg bg-charcoal text-xs font-bold text-white">{{ initials }}</span>
              <ChevronDown class="size-4 text-muted" />
            </button>
            <Transition enter-active-class="transition duration-150 ease-out" enter-from-class="opacity-0 scale-95" leave-active-class="transition duration-100" leave-to-class="opacity-0 scale-95">
              <div v-if="menu" role="menu" class="absolute right-0 mt-2 w-60 origin-top-right rounded-2xl border border-line bg-white p-2 shadow-[var(--shadow-lift)]">
                <div class="px-3 py-2">
                  <p class="truncate text-sm font-semibold">{{ user?.name }}</p>
                  <p class="truncate text-xs text-muted">{{ user?.email }}</p>
                </div>
                <div class="my-1 h-px bg-line" />
                <NuxtLink role="menuitem" to="/dashboard/settings" class="block rounded-lg px-3 py-2 text-sm hover:bg-canvas">Account settings</NuxtLink>
                <NuxtLink v-if="user?.isAdmin" role="menuitem" :to="route.path.startsWith('/admin') ? '/dashboard' : '/admin'" class="block rounded-lg px-3 py-2 text-sm hover:bg-canvas">{{ route.path.startsWith('/admin') ? 'Customer dashboard' : 'Admin panel' }}</NuxtLink>
                <NuxtLink role="menuitem" to="/help" class="block rounded-lg px-3 py-2 text-sm hover:bg-canvas">Help centre</NuxtLink>
                <button role="menuitem" type="button" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-danger hover:bg-orange-50" @click="logout"><LogOut class="size-4" /> Sign out</button>
              </div>
            </Transition>
          </div>
        </div>
      </header>
      <main id="main" class="px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        <slot />
      </main>
    </div>
  </div>
</template>
