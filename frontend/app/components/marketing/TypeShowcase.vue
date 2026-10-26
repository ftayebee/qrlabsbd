<script setup lang="ts">
import { ArrowRight } from 'lucide-vue-next'
import type { QrType } from '~/types/qr'
import { DEFAULT_DESIGN } from '~/types/qr'
import { QR_CATALOG } from '~/utils/qr/catalog'
import { encodeEmail, encodePhone, encodeSms, encodeText, encodeVCard, encodeWifi } from '~/utils/qr/payload'

const active = ref<QrType>('url')
const meta = computed(() => QR_CATALOG[active.value])
const config = useRuntimeConfig()
const sampleShort = `${String(config.public.shortDomain).replace(/\/$/, '')}/r/Demo7Qx`

// Representative payloads so the preview reflects each type's real density.
const samples: Record<QrType, string> = {
  url: 'https://example.com/spring-menu',
  pdf: sampleShort, multi_url: sampleShort, social: sampleShort, business_card: sampleShort, app: sampleShort,
  contact: encodeVCard({ firstName: 'Alex', lastName: 'Rivera', company: 'Northwind', phone: '+15551234567', email: 'alex@example.com' }),
  text: encodeText({ text: 'Thanks for visiting! Show this at the till for 10% off.' }),
  sms: encodeSms({ phone: '+15551234567', message: 'JOIN' }),
  email: encodeEmail({ to: 'hello@example.com', subject: 'Booking enquiry' }),
  phone: encodePhone({ phone: '+15551234567' }),
  wifi: encodeWifi({ ssid: 'Cafe Guest', security: 'WPA', password: 'welcome2026' }),
}
const design = { ...DEFAULT_DESIGN, frame: 'label-bottom' as const, frameLabel: 'Scan me', frameColor: '#14251A' }
</script>

<template>
  <section id="qr-types" class="section" aria-labelledby="types-title">
    <div class="container-page">
      <AppReveal class="mx-auto max-w-2xl text-center">
        <p class="eyebrow">12 QR code types</p>
        <h2 id="types-title" class="section-title mt-4">One generator for everything you print</h2>
        <p class="section-lead">From a single link to a full digital business card. Pick a type to see what it does.</p>
      </AppReveal>

      <div class="mt-12 grid gap-6 lg:grid-cols-[1.25fr_1fr] lg:gap-10">
        <AppReveal>
          <QrTypeSelector v-model="active" />
        </AppReveal>

        <AppReveal :delay="0.1" class="card overflow-hidden">
          <div class="grid gap-6 p-6 sm:grid-cols-[1fr_auto] sm:items-center lg:grid-cols-1 xl:grid-cols-[1fr_auto]">
            <AnimatePresence mode="wait">
              <Motion :key="active" as="div" :initial="{ opacity: 0, x: -8 }" :animate="{ opacity: 1, x: 0 }" :exit="{ opacity: 0, x: 8 }" :transition="{ duration: 0.22 }">
                <div class="flex items-center gap-2">
                  <h3 class="text-xl font-bold">{{ meta.label }}</h3>
                  <UiBadge :tone="meta.modes.includes('dynamic') ? 'green' : 'neutral'">{{ meta.hosted ? 'Hosted page' : meta.modes.length > 1 ? 'Static or dynamic' : meta.modes[0] === 'static' ? 'Static' : 'Dynamic' }}</UiBadge>
                </div>
                <p class="mt-3 text-sm leading-relaxed text-muted">{{ meta.intro }}</p>
                <div class="mt-5 flex flex-wrap gap-2">
                  <UiButton :to="`/qr-code-generator?type=${active}`" size="sm">Create {{ meta.label.toLowerCase() }} code <ArrowRight class="size-4" /></UiButton>
                  <UiButton :to="`/qr-codes/${meta.slug}`" variant="ghost" size="sm">Learn more</UiButton>
                </div>
              </Motion>
            </AnimatePresence>
            <div class="mx-auto w-44 shrink-0">
              <QrPreview :value="samples[active]" :design="design" :size="176" />
            </div>
          </div>
        </AppReveal>
      </div>
    </div>
  </section>
</template>
