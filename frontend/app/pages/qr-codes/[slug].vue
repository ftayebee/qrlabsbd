<script setup lang="ts">
import { Check } from 'lucide-vue-next'
import { QR_TYPE_LIST, metaBySlug } from '~/utils/qr/catalog'

const route = useRoute()
const meta = metaBySlug(String(route.params.slug))
if (!meta) throw createError({ statusCode: 404, statusMessage: 'QR code type not found', fatal: true })

const crumbs = [{ label: 'QR code generator', to: '/qr-code-generator' }, { label: meta.label }]
usePageSeo({ title: meta.seoTitle, description: meta.seoDescription, path: `/qr-codes/${meta.slug}`, crumbs })
useSchemaOrg([defineHowTo({
  name: `How to create a ${meta.label} QR code`,
  step: meta.howItWorks.map((text, i) => ({ name: `Step ${i + 1}`, text })),
})])

const related = QR_TYPE_LIST.filter(t => t.type !== meta.type).slice(0, 6)
const router = useRouter()
</script>

<template>
  <div v-if="meta">
    <MarketingPageHero :eyebrow="`${meta.label} QR code`" :title="meta.seoTitle.split(' – ')[0]!" :lead="meta.intro" :crumbs="crumbs" />

    <div class="container-page pb-20">
      <ClientOnly>
        <QrGenerator :initial-type="meta.type" lock-type @saved="r => router.push(`/dashboard/qr-codes/${r.id}`)" />
        <template #fallback><UiSpinner label="Loading generator…" /></template>
      </ClientOnly>
    </div>

    <section class="border-t border-line bg-white">
      <div class="container-page grid gap-12 py-20 lg:grid-cols-2">
        <AppReveal>
          <h2 class="text-2xl font-extrabold">How it works</h2>
          <ol class="mt-6 space-y-5">
            <li v-for="(step, i) in meta.howItWorks" :key="step" class="flex gap-4">
              <span class="grid size-8 shrink-0 place-items-center rounded-full bg-soft text-sm font-bold text-brand-700">{{ i + 1 }}</span>
              <p class="pt-1 text-muted">{{ step }}</p>
            </li>
          </ol>
        </AppReveal>
        <AppReveal :delay="0.08">
          <h2 class="text-2xl font-extrabold">Tips for reliable scans</h2>
          <ul class="mt-6 space-y-4">
            <li v-for="tip in meta.tips" :key="tip" class="flex gap-3 text-muted"><Check class="mt-0.5 size-5 shrink-0 text-brand" aria-hidden="true" /> {{ tip }}</li>
          </ul>
          <div class="mt-8 rounded-2xl bg-canvas p-5 text-sm text-muted">
            <p class="font-semibold text-ink">Static or dynamic?</p>
            <p class="mt-1">
              <template v-if="meta.modes.length > 1">This type supports both. Choose dynamic if you might change the content or want scan analytics.</template>
              <template v-else-if="meta.modes[0] === 'static'">This type is always static: the content is stored in the code itself and works offline.</template>
              <template v-else>This type is always dynamic because the content is hosted on a page we serve for you.</template>
              <NuxtLink to="/dynamic-qr-codes" class="ml-1 font-semibold text-brand-700 hover:underline">Learn more</NuxtLink>
            </p>
          </div>
        </AppReveal>
      </div>
    </section>

    <section class="section !pt-16">
      <div class="container-page">
        <h2 class="text-2xl font-extrabold">Other QR code types</h2>
        <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <NuxtLink v-for="t in related" :key="t.type" :to="`/qr-codes/${t.slug}`" class="card group flex items-center gap-3 p-4 transition-shadow hover:shadow-[var(--shadow-lift)]">
            <span class="grid size-10 place-items-center rounded-xl bg-soft text-brand-700"><QrTypeIcon :type="t.type" class="size-5" /></span>
            <span><span class="block font-semibold">{{ t.label }}</span><span class="block text-sm text-muted">{{ t.short }}</span></span>
          </NuxtLink>
        </div>
      </div>
    </section>
    <MarketingFinalCta />
  </div>
</template>
