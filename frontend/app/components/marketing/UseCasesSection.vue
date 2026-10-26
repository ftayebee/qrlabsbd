<script setup lang="ts">
import { ArrowUpRight } from 'lucide-vue-next'
import { USE_CASES } from '~/utils/content/useCases'
</script>

<template>
  <section class="section" aria-labelledby="usecases-title">
    <div class="container-page">
      <AppReveal class="max-w-2xl">
        <p class="eyebrow">Solutions</p>
        <h2 id="usecases-title" class="section-title mt-4">Built for the places people actually scan</h2>
        <p class="section-lead">Practical setups for the businesses we see most, from table menus to trade-show badges.</p>
      </AppReveal>
      <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <AppReveal v-for="(u, i) in USE_CASES" :key="u.slug" :delay="(i % 4) * 0.05" :class="i === 0 ? 'lg:col-span-2' : ''">
          <NuxtLink :to="`/use-cases/${u.slug}`" class="card group flex h-full flex-col p-6 transition-[transform,box-shadow] duration-300 hover:-translate-y-1 hover:shadow-[var(--shadow-lift)]">
            <div class="flex items-start justify-between">
              <span class="grid size-11 place-items-center rounded-xl bg-soft text-brand-700 transition-colors group-hover:bg-brand group-hover:text-white">
                <MarketingUseCaseIcon :icon="u.icon" class="size-5" />
              </span>
              <ArrowUpRight class="size-5 text-muted transition-transform group-hover:-translate-y-0.5 group-hover:translate-x-0.5 group-hover:text-ink" />
            </div>
            <h3 class="mt-6 text-lg font-bold">{{ u.title }}</h3>
            <p class="mt-2 text-sm leading-relaxed text-muted">{{ u.summary }}</p>
          </NuxtLink>
        </AppReveal>
        <AppReveal :delay="0.15">
          <NuxtLink to="/use-cases" class="flex h-full flex-col justify-between rounded-[var(--radius-card)] border border-dashed border-line-strong p-6 transition-colors hover:border-brand hover:bg-white">
            <p class="font-display text-lg font-bold">See every use case</p>
            <p class="mt-6 text-sm font-semibold text-brand-700">Browse solutions →</p>
          </NuxtLink>
        </AppReveal>
      </div>
    </div>
  </section>
</template>
