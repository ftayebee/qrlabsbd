<script setup lang="ts">
import { brand } from '~/brand.config'

useSeoMeta({
  ogSiteName: brand.name,
  ogType: 'website',
  twitterCard: 'summary_large_image',
})
</script>

<template>
  <MotionConfig reduced-motion="user">
    <NuxtRouteAnnouncer />
    <NuxtLoadingIndicator :color="brand.colors.primary" :height="2" />
    <NuxtLayout>
      <NuxtPage />
    </NuxtLayout>
    <UiToastHost />
    <UiConfirmHost />
  </MotionConfig>
</template>
