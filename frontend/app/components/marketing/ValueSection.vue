<script setup lang="ts">
import { FileDown, Fingerprint, MousePointerClick, RefreshCcw } from 'lucide-vue-next'

const items = [
  { icon: MousePointerClick, title: 'Free static codes, no sign-up', body: 'Links, Wi-Fi, text, email, SMS, phone and vCards are generated in your browser and downloaded instantly.' },
  { icon: RefreshCcw, title: 'Edit after printing', body: 'Dynamic codes point to a short link we control, so you can change the destination without touching the print.' },
  { icon: Fingerprint, title: 'Privacy-first analytics', body: 'We never store scanners’ IP addresses. Unique scans are estimated with a salted hash that rotates daily.' },
  { icon: FileDown, title: 'Print-ready exports', body: 'High-resolution PNG, scalable SVG and PDF files with scan-reliability checks before you download.' },
]
</script>

<template>
  <section class="border-y border-line bg-white" aria-labelledby="value-title">
    <div class="container-page py-14 sm:py-16">
      <h2 id="value-title" class="sr-only">Why choose us</h2>
      <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4 lg:gap-10">
        <AppReveal v-for="(item, i) in items" :key="item.title" :delay="i * 0.06">
          <component :is="item.icon" class="size-6 text-brand" aria-hidden="true" />
          <h3 class="mt-4 text-base font-bold">{{ item.title }}</h3>
          <p class="mt-2 text-sm leading-relaxed text-muted">{{ item.body }}</p>
        </AppReveal>
      </div>
    </div>
  </section>
</template>
