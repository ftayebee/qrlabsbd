<script setup lang="ts">
import { USE_CASES } from '~/utils/content/useCases'

const crumbs = [{ label: 'Use cases' }]
usePageSeo({
  title: 'QR Code Use Cases – Restaurants, Retail, Events and More',
  description: 'Practical ways to use static and dynamic QR codes in restaurants, retail, ecommerce, events, marketing, education and professional services.',
  path: '/use-cases',
  crumbs,
})
</script>

<template>
  <div>
    <MarketingPageHero eyebrow="Solutions" title="QR codes that fit how your business works" lead="Choose your industry for setups, recommended code types and printing tips." :crumbs="crumbs" />
    <div class="container-page grid gap-5 pb-24 sm:grid-cols-2 lg:grid-cols-3">
      <AppReveal v-for="(u, i) in USE_CASES" :key="u.slug" :delay="(i % 3) * 0.05">
        <NuxtLink :to="`/use-cases/${u.slug}`" class="card group flex h-full flex-col p-7 transition-[transform,box-shadow] duration-300 hover:-translate-y-1 hover:shadow-[var(--shadow-lift)]">
          <span class="grid size-12 place-items-center rounded-2xl bg-soft text-brand-700 transition-colors group-hover:bg-brand group-hover:text-charcoal"><MarketingUseCaseIcon :icon="u.icon" class="size-6" /></span>
          <h2 class="mt-6 text-xl font-bold">{{ u.title }}</h2>
          <p class="mt-2 flex-1 text-muted">{{ u.summary }}</p>
          <p class="mt-6 text-sm font-semibold text-brand-700">Read the guide →</p>
        </NuxtLink>
      </AppReveal>
    </div>
  </div>
</template>
