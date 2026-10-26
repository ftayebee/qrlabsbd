<script setup lang="ts">
import { ArrowRight, ChevronDown, Menu, X } from 'lucide-vue-next'
import { QR_TYPE_LIST } from '~/utils/qr/catalog'

const { user, fetchUser } = useAuth()
onMounted(() => fetchUser())

const mobileOpen = ref(false)
const typesOpen = ref(false)
const scrolled = ref(false)
const route = useRoute()

const onScroll = () => { scrolled.value = window.scrollY > 8 }
onMounted(() => { onScroll(); window.addEventListener('scroll', onScroll, { passive: true }) })
onBeforeUnmount(() => window.removeEventListener('scroll', onScroll))

watch(() => route.fullPath, () => { mobileOpen.value = false; typesOpen.value = false })
watch(mobileOpen, (v) => { if (import.meta.client) document.body.style.overflow = v ? 'hidden' : '' })

const nav = [
  { label: 'Features', to: '/#features' },
  { label: 'Solutions', to: '/use-cases' },
  { label: 'Pricing', to: '/pricing' },
  { label: 'Resources', to: '/help' },
]

let closeTimer: ReturnType<typeof setTimeout> | undefined
const openTypes = () => { clearTimeout(closeTimer); typesOpen.value = true }
const closeTypes = () => { closeTimer = setTimeout(() => (typesOpen.value = false), 120) }
const dashboardLink = computed(() => (user.value?.isAdmin ? '/admin' : '/dashboard'))
</script>

<template>
  <header :class="['sticky top-0 z-40 transition-[background-color,box-shadow,border-color] duration-300', scrolled || mobileOpen ? 'border-b border-line bg-canvas/90 backdrop-blur-md' : 'border-b border-transparent bg-transparent']">
    <div class="container-page flex h-16 items-center justify-between gap-6 lg:h-18">
      <NuxtLink to="/" class="shrink-0" aria-label="Home"><AppLogo /></NuxtLink>

      <nav class="hidden items-center gap-1 lg:flex" aria-label="Main">
        <NuxtLink to="/#features" class="rounded-lg px-3 py-2 text-sm font-medium text-muted transition-colors hover:text-ink">Features</NuxtLink>
        <div class="relative" @mouseenter="openTypes" @mouseleave="closeTypes">
          <button
            type="button" class="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-medium text-muted transition-colors hover:text-ink"
            :aria-expanded="typesOpen" aria-haspopup="true" @click="typesOpen = !typesOpen" @keydown.escape="typesOpen = false"
          >
            QR Types <ChevronDown :class="['size-4 transition-transform', typesOpen ? 'rotate-180' : '']" />
          </button>
          <Transition
            enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 translate-y-1"
            leave-active-class="transition duration-150 ease-in" leave-to-class="opacity-0 translate-y-1"
          >
            <div v-if="typesOpen" class="absolute left-1/2 top-full w-[560px] -translate-x-1/2 pt-3">
              <div class="card grid grid-cols-2 gap-1 p-3">
                <NuxtLink
                  v-for="t in QR_TYPE_LIST" :key="t.type" :to="`/qr-codes/${t.slug}`"
                  class="group flex items-start gap-3 rounded-xl p-3 transition-colors hover:bg-canvas"
                >
                  <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-soft text-brand-700 transition-colors group-hover:bg-brand group-hover:text-white">
                    <QrTypeIcon :type="t.type" class="size-4.5" />
                  </span>
                  <span>
                    <span class="block text-sm font-semibold text-ink">{{ t.label }}</span>
                    <span class="block text-xs text-muted">{{ t.short }}</span>
                  </span>
                </NuxtLink>
              </div>
            </div>
          </Transition>
        </div>
        <NuxtLink v-for="item in nav.slice(1)" :key="item.to" :to="item.to" class="rounded-lg px-3 py-2 text-sm font-medium text-muted transition-colors hover:text-ink" active-class="!text-ink">
          {{ item.label }}
        </NuxtLink>
      </nav>

      <div class="hidden items-center gap-2 lg:flex">
        <template v-if="user">
          <UiButton :to="dashboardLink" variant="dark" size="sm">Open dashboard <ArrowRight class="size-4" /></UiButton>
        </template>
        <template v-else>
          <UiButton to="/login" variant="ghost" size="sm">Sign in</UiButton>
          <UiButton to="/register" size="sm">Get started</UiButton>
        </template>
      </div>

      <button type="button" class="-mr-2 rounded-lg p-2 text-ink lg:hidden" :aria-expanded="mobileOpen" aria-controls="mobile-nav" aria-label="Toggle menu" @click="mobileOpen = !mobileOpen">
        <X v-if="mobileOpen" class="size-6" /><Menu v-else class="size-6" />
      </button>
    </div>

    <AnimatePresence>
      <Motion
        v-if="mobileOpen" id="mobile-nav" as="nav" aria-label="Mobile"
        class="fixed inset-x-0 top-16 bottom-0 overflow-y-auto border-t border-line bg-canvas px-4 pb-10 pt-4 lg:hidden"
        :initial="{ opacity: 0, y: -8 }" :animate="{ opacity: 1, y: 0 }" :exit="{ opacity: 0, y: -8 }" :transition="{ duration: 0.2 }"
      >
        <div class="space-y-1">
          <NuxtLink v-for="item in nav" :key="item.to" :to="item.to" class="block rounded-xl px-3 py-3 text-base font-semibold text-ink hover:bg-white">{{ item.label }}</NuxtLink>
        </div>
        <p class="mt-6 px-3 text-xs font-semibold uppercase tracking-wider text-muted">QR types</p>
        <div class="mt-2 grid grid-cols-2 gap-1">
          <NuxtLink v-for="t in QR_TYPE_LIST" :key="t.type" :to="`/qr-codes/${t.slug}`" class="flex items-center gap-2 rounded-xl px-3 py-2.5 text-sm font-medium text-ink hover:bg-white">
            <QrTypeIcon :type="t.type" class="size-4 text-brand-700" /> {{ t.label }}
          </NuxtLink>
        </div>
        <div class="mt-8 grid gap-2">
          <template v-if="user"><UiButton :to="dashboardLink" variant="dark" block>Open dashboard</UiButton></template>
          <template v-else>
            <UiButton to="/register" block>Get started free</UiButton>
            <UiButton to="/login" variant="secondary" block>Sign in</UiButton>
          </template>
        </div>
      </Motion>
    </AnimatePresence>
  </header>
</template>
