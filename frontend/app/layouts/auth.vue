<script setup lang="ts">
import { BarChart3, PencilLine, ShieldCheck } from 'lucide-vue-next'
</script>

<template>
  <div class="grid min-h-dvh lg:grid-cols-[1fr_1.05fr]">
    <div class="flex flex-col px-4 py-6 sm:px-10">
      <NuxtLink to="/" aria-label="Home" class="self-start"><AppLogo /></NuxtLink>
      <main id="main" class="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center py-12">
        <slot />
      </main>
    </div>
    <aside class="relative hidden overflow-hidden bg-charcoal lg:block" aria-hidden="true">
      <div class="qr-grid-bg absolute inset-0 opacity-40 [filter:invert(1)]" />
      <div class="relative flex h-full flex-col justify-end p-14 text-white">
        <p class="font-display text-3xl font-extrabold leading-tight">Change the destination.<br>Keep the print.</p>
        <ul class="mt-8 space-y-4 text-sm text-white/75">
          <li class="flex items-center gap-3"><PencilLine class="size-5 text-brand" /> Edit dynamic codes after they are printed</li>
          <li class="flex items-center gap-3"><BarChart3 class="size-5 text-brand" /> See scans by day, device and country</li>
          <li class="flex items-center gap-3"><ShieldCheck class="size-5 text-brand" /> No IP addresses stored for scanners</li>
        </ul>
      </div>
    </aside>
  </div>
</template>
