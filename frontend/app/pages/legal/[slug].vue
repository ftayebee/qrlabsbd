<script setup lang="ts">
import { renderMarkdown } from '~/utils/markdown'
import { formatDate } from '~/utils/format'

interface ContentPage { slug: string; title: string; body: string; metaTitle: string | null; metaDescription: string | null; updatedAt: string | null }

const route = useRoute()
const api = useApi()
const slug = String(route.params.slug)
const { data: page, error } = await useAsyncData(`page-${slug}`, async () => (await api.get<{ data: ContentPage }>(`/pages/${slug}`)).data)
if (error.value || !page.value) throw createError({ statusCode: 404, statusMessage: 'Page not found', fatal: true })

const crumbs = [{ label: page.value.title }]
usePageSeo({
  title: page.value.metaTitle ?? page.value.title,
  description: page.value.metaDescription ?? `${page.value.title} for our QR code platform.`,
  path: `/legal/${slug}`,
  crumbs,
})
// Safe: renderMarkdown escapes all HTML before applying formatting.
const html = computed(() => renderMarkdown(page.value?.body ?? ''))
</script>

<template>
  <div v-if="page">
    <MarketingPageHero eyebrow="Legal" :title="page.title" :lead="page.updatedAt ? `Last updated ${formatDate(page.updatedAt)}` : undefined" :crumbs="crumbs" />
    <div class="container-page pb-24">
      <article class="card prose-content mx-auto max-w-3xl p-7 sm:p-10" v-html="html" />
    </div>
  </div>
</template>
