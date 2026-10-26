<script setup lang="ts">
import type { NuxtError } from '#app'

const props = defineProps<{ error: NuxtError }>()
const is404 = computed(() => props.error.statusCode === 404)
useSeoMeta({ title: is404.value ? 'Page not found' : 'Something went wrong', robots: 'noindex' })
</script>

<template>
  <NuxtLayout name="default">
    <section class="container-page grid min-h-[60vh] place-items-center py-20 text-center">
      <div class="max-w-md">
        <p class="font-display text-7xl font-extrabold text-brand-600">{{ error.statusCode }}</p>
        <h1 class="mt-4 text-2xl font-extrabold">{{ is404 ? 'We couldn’t find that page' : 'Something went wrong' }}</h1>
        <p class="mt-3 text-muted">{{ is404 ? 'The link may be broken or the page may have moved.' : 'Please try again. If the problem continues, contact support.' }}</p>
        <div class="mt-8 flex justify-center gap-3">
          <UiButton @click="clearError({ redirect: '/' })">Go home</UiButton>
          <UiButton to="/help" variant="secondary">Help centre</UiButton>
        </div>
      </div>
    </section>
  </NuxtLayout>
</template>
