<script setup lang="ts">
import { ArrowRight } from 'lucide-vue-next'

withDefaults(defineProps<{ title?: string; body?: string }>(), {
  title: 'Your next print run deserves a smarter code.',
  body: 'Start with free static codes today. Upgrade when you want to edit, track and host.',
})
</script>

<template>
  <section class="pb-24">
    <div class="container-page">
      <AppReveal>
        <div class="relative overflow-hidden rounded-[32px] border border-brand/20 bg-soft px-6 py-16 text-center sm:px-12 sm:py-20">
          <div class="qr-grid-bg absolute inset-0" aria-hidden="true" />
          <div class="relative mx-auto max-w-2xl">
            <h2 class="text-3xl font-extrabold sm:text-[2.75rem] sm:leading-[1.1]">{{ title }}</h2>
            <p class="mt-4 text-lg text-muted">{{ body }}</p>
            <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
              <UiButton to="/qr-code-generator" size="lg">Create a QR Code <ArrowRight class="size-4" /></UiButton>
              <UiButton to="/pricing" variant="secondary" size="lg">See pricing</UiButton>
            </div>
          </div>
        </div>
      </AppReveal>
    </div>
  </section>
</template>
