<script setup lang="ts">
import { ArrowRight, BarChart3, PencilLine, ShieldCheck } from 'lucide-vue-next'

const ease = [0.25, 1, 0.5, 1] as const
const item = (delay: number) => ({ initial: { opacity: 0, y: 16 }, animate: { opacity: 1, y: 0 }, transition: { duration: 0.7, delay, ease } })
</script>

<template>
  <section class="relative overflow-hidden">
    <div class="pointer-events-none absolute inset-0 -top-24 overflow-hidden" aria-hidden="true">
      <div class="qr-grid-bg absolute -inset-10" />
      <div class="absolute left-1/2 top-24 h-[420px] w-[720px] -translate-x-1/2 rounded-full bg-soft blur-3xl" />
    </div>

    <div class="container-page relative grid items-center gap-12 pt-10 pb-20 sm:pt-16 lg:grid-cols-[1.05fr_1fr] lg:gap-16 lg:pt-20 lg:pb-28">
      <div>
        <Motion as="p" v-bind="item(0)" class="eyebrow">Dynamic QR codes · Scan analytics</Motion>
        <Motion as="h1" v-bind="item(0.08)" class="mt-6 text-[2.75rem] leading-[1.02] font-extrabold sm:text-6xl lg:text-[4.25rem]">
          Make Every<br class="hidden sm:block"> Scan <span class="relative whitespace-nowrap text-brand-600">Count.<svg class="absolute -bottom-2 left-0 w-full" viewBox="0 0 200 12" fill="none" aria-hidden="true"><path d="M2 9c40-6 120-8 196-3" stroke="currentColor" stroke-width="4" stroke-linecap="round" opacity=".35" /></svg></span>
        </Motion>
        <Motion as="p" v-bind="item(0.16)" class="mt-6 max-w-xl text-lg leading-relaxed text-muted">
          Create QR codes for links, PDFs, Wi-Fi, menus and business cards. Style them to match your brand, change where they point after printing, and see how every campaign performs.
        </Motion>
        <Motion as="div" v-bind="item(0.24)" class="mt-8 flex flex-col gap-3 sm:flex-row">
          <UiButton to="/qr-code-generator" size="lg">Create a QR Code <ArrowRight class="size-4" /></UiButton>
          <UiButton to="/#features" variant="secondary" size="lg">Explore Features</UiButton>
        </Motion>
        <Motion as="ul" v-bind="item(0.32)" class="mt-10 grid gap-3 text-sm text-muted sm:grid-cols-3">
          <li class="flex items-center gap-2"><PencilLine class="size-4 text-brand" /> Editable after print</li>
          <li class="flex items-center gap-2"><BarChart3 class="size-4 text-brand" /> Real scan analytics</li>
          <li class="flex items-center gap-2"><ShieldCheck class="size-4 text-brand" /> Privacy-first tracking</li>
        </Motion>
      </div>

      <Motion as="div" :initial="{ opacity: 0, y: 24, scale: 0.98 }" :animate="{ opacity: 1, y: 0, scale: 1 }" :transition="{ duration: 0.8, delay: 0.2, ease }">
        <MarketingHeroGenerator />
      </Motion>
    </div>
  </section>
</template>
