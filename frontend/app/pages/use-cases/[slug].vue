<script setup lang="ts">
import { Check } from 'lucide-vue-next'
import { QR_CATALOG } from '~/utils/qr/catalog'
import { useCaseBySlug } from '~/utils/content/useCases'

const route = useRoute()
const uc = useCaseBySlug(String(route.params.slug))
if (!uc) throw createError({ statusCode: 404, statusMessage: 'Use case not found', fatal: true })

const crumbs = [{ label: 'Use cases', to: '/use-cases' }, { label: uc.title }]
usePageSeo({ title: uc.seoTitle, description: uc.seoDescription, path: `/use-cases/${uc.slug}`, crumbs })
</script>

<template>
  <div v-if="uc">
    <MarketingPageHero :eyebrow="uc.title" :title="uc.seoTitle.split(' – ')[0]!" :lead="uc.summary" :crumbs="crumbs">
      <UiButton :to="`/qr-code-generator?type=${uc.types[0]}`">Create your first code</UiButton>
    </MarketingPageHero>
    <div class="container-page grid gap-5 pb-16 md:grid-cols-3">
      <AppReveal v-for="(idea, i) in uc.ideas" :key="idea.title" :delay="i * 0.06" class="card p-7">
        <h2 class="text-lg font-bold">{{ idea.title }}</h2>
        <p class="mt-2 text-muted">{{ idea.body }}</p>
      </AppReveal>
    </div>
    <section class="border-y border-line bg-white">
      <div class="container-page grid gap-12 py-16 lg:grid-cols-2">
        <div>
          <h2 class="text-2xl font-extrabold">Recommended code types</h2>
          <div class="mt-6 grid gap-3 sm:grid-cols-2">
            <NuxtLink v-for="t in uc.types" :key="t" :to="`/qr-codes/${QR_CATALOG[t].slug}`" class="flex items-center gap-3 rounded-2xl border border-line p-4 transition-colors hover:border-brand hover:bg-soft/40">
              <span class="grid size-10 place-items-center rounded-xl bg-soft text-brand-700"><QrTypeIcon :type="t" class="size-5" /></span>
              <span><span class="block font-semibold">{{ QR_CATALOG[t].label }}</span><span class="block text-sm text-muted">{{ QR_CATALOG[t].short }}</span></span>
            </NuxtLink>
          </div>
        </div>
        <div>
          <h2 class="text-2xl font-extrabold">Practical tips</h2>
          <ul class="mt-6 space-y-4">
            <li v-for="tip in uc.tips" :key="tip" class="flex gap-3 text-muted"><Check class="mt-0.5 size-5 shrink-0 text-brand" /> {{ tip }}</li>
          </ul>
        </div>
      </div>
    </section>
    <div class="pt-24"><MarketingFinalCta /></div>
  </div>
</template>
