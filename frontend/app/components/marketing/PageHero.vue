<script setup lang="ts">
defineProps<{ eyebrow?: string; title: string; lead?: string; crumbs?: { label: string; to?: string }[] }>()
</script>

<template>
  <section class="relative overflow-hidden">
    <div class="qr-grid-bg pointer-events-none absolute -inset-10 opacity-70" aria-hidden="true" />
    <div class="container-page relative pt-10 pb-12 sm:pt-14 sm:pb-16">
      <nav v-if="crumbs?.length" aria-label="Breadcrumb" class="mb-6">
        <ol class="flex flex-wrap items-center gap-1.5 text-sm text-muted">
          <li><NuxtLink to="/" class="hover:text-ink">Home</NuxtLink></li>
          <li v-for="c in crumbs" :key="c.label" class="flex items-center gap-1.5">
            <span aria-hidden="true">/</span>
            <NuxtLink v-if="c.to" :to="c.to" class="hover:text-ink">{{ c.label }}</NuxtLink>
            <span v-else aria-current="page" class="text-ink">{{ c.label }}</span>
          </li>
        </ol>
      </nav>
      <Motion as="div" :initial="{ opacity: 0, y: 12 }" :animate="{ opacity: 1, y: 0 }" :transition="{ duration: 0.6, ease: [0.25, 1, 0.5, 1] }" class="max-w-3xl">
        <p v-if="eyebrow" class="eyebrow">{{ eyebrow }}</p>
        <h1 class="mt-4 text-4xl font-extrabold leading-[1.08] sm:text-5xl">{{ title }}</h1>
        <p v-if="lead" class="mt-5 text-lg leading-relaxed text-muted">{{ lead }}</p>
        <div v-if="$slots.default" class="mt-8"><slot /></div>
      </Motion>
    </div>
  </section>
</template>
