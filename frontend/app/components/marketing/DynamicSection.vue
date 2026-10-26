<script setup lang="ts">
import { ArrowRight, Check, PauseCircle, Pencil, ShieldAlert } from 'lucide-vue-next'

const destinations = ['example.com/summer-menu', 'example.com/autumn-menu', 'example.com/winter-menu']
const idx = ref(0)
let timer: ReturnType<typeof setInterval> | undefined
const reduced = useReducedMotion()
onMounted(() => { if (!reduced.value) timer = setInterval(() => (idx.value = (idx.value + 1) % destinations.length), 2600) })
onBeforeUnmount(() => clearInterval(timer))

const points = [
  { icon: Pencil, title: 'Change destinations any time', body: 'Fix a typo or swap a seasonal menu. The printed code stays the same.' },
  { icon: PauseCircle, title: 'Pause, archive or resume', body: 'Take a campaign offline instantly and bring it back later.' },
  { icon: ShieldAlert, title: 'Safe redirects', body: 'Destinations are checked for unsafe schemes, private addresses and redirect loops.' },
]
</script>

<template>
  <section id="features" class="section bg-charcoal text-white" aria-labelledby="dynamic-title">
    <div class="container-page grid items-center gap-14 lg:grid-cols-2">
      <div>
        <AppReveal><p class="eyebrow !border-white/15 !bg-white/10 !text-brand">Dynamic QR codes</p></AppReveal>
        <AppReveal :delay="0.05"><h2 id="dynamic-title" class="section-title mt-4 !text-white">Printed once. Updated whenever you like.</h2></AppReveal>
        <AppReveal :delay="0.1"><p class="section-lead !text-white/70">A dynamic code holds a short link on our domain. When it is scanned, we send people to wherever you point it today and record the scan for your analytics.</p></AppReveal>
        <ul class="mt-10 space-y-6">
          <AppReveal v-for="(p, i) in points" :key="p.title" as="li" :delay="0.12 + i * 0.06" class="flex gap-4">
            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-white/10 text-brand"><component :is="p.icon" class="size-5" /></span>
            <div>
              <h3 class="font-bold !text-white">{{ p.title }}</h3>
              <p class="mt-1 text-sm text-white/65">{{ p.body }}</p>
            </div>
          </AppReveal>
        </ul>
        <AppReveal :delay="0.3" class="mt-10"><UiButton to="/dynamic-qr-codes" variant="primary">How dynamic codes work <ArrowRight class="size-4" /></UiButton></AppReveal>
      </div>

      <AppReveal :delay="0.1" aria-hidden="true">
        <div class="rounded-[28px] border border-white/10 bg-white/[0.04] p-6 sm:p-8">
          <div class="flex items-center gap-4 rounded-2xl bg-white p-4 text-ink">
            <div class="grid size-14 shrink-0 grid-cols-3 gap-0.5 rounded-lg bg-charcoal p-2">
              <span v-for="n in 9" :key="n" :class="['rounded-[2px]', [1, 3, 5, 7, 9].includes(n) ? 'bg-brand' : 'bg-white']" />
            </div>
            <div class="min-w-0">
              <p class="text-xs font-semibold uppercase tracking-wider text-muted">Printed code</p>
              <p class="truncate font-mono text-sm">qrio.app/r/Menu24x</p>
            </div>
          </div>
          <div class="my-4 ml-11 h-8 border-l-2 border-dashed border-white/20" />
          <div class="rounded-2xl border border-white/10 bg-white/[0.06] p-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-white/50">Current destination</p>
            <div class="relative mt-2 h-6 overflow-hidden">
              <AnimatePresence mode="wait">
                <Motion :key="idx" as="p" class="absolute font-mono text-sm text-white" :initial="{ y: 16, opacity: 0 }" :animate="{ y: 0, opacity: 1 }" :exit="{ y: -16, opacity: 0 }" :transition="{ duration: 0.35 }">
                  {{ destinations[idx] }}
                </Motion>
              </AnimatePresence>
            </div>
            <p class="mt-4 flex items-center gap-2 text-xs text-white/60"><Check class="size-3.5 text-brand" /> Updated without reprinting</p>
          </div>
        </div>
      </AppReveal>
    </div>
  </section>
</template>
