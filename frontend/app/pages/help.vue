<script setup lang="ts">
import { Search } from 'lucide-vue-next'

const crumbs = [{ label: 'Help & FAQ' }]
usePageSeo({
  title: 'Help Centre & FAQ',
  description: 'Answers about static and dynamic QR codes, design and downloads, scan analytics, billing and security.',
  path: '/help',
  crumbs,
})

const { data: faqs, status } = useFaqs()
const q = ref('')
const category = ref('all')
const categories = computed(() => ['all', ...new Set(faqs.value.map(f => f.category).filter(Boolean) as string[])])
const filtered = computed(() => faqs.value.filter(f =>
  (category.value === 'all' || f.category === category.value)
  && (!q.value || `${f.question} ${f.answer}`.toLowerCase().includes(q.value.toLowerCase()))))

useSchemaOrg(computed(() => faqs.value.length ? [{
  '@type': 'FAQPage',
  mainEntity: faqs.value.map(f => ({ '@type': 'Question', name: f.question, acceptedAnswer: { '@type': 'Answer', text: f.answer } })),
}] : []))
const label = (c: string) => (c === 'all' ? 'All topics' : c.charAt(0).toUpperCase() + c.slice(1))
</script>

<template>
  <div>
    <MarketingPageHero eyebrow="Resources" title="How can we help?" lead="Guides and answers for creating, printing and measuring QR codes." :crumbs="crumbs">
      <div class="relative max-w-lg">
        <Search class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-muted" aria-hidden="true" />
        <label for="help-search" class="sr-only">Search help</label>
        <input id="help-search" v-model="q" type="search" class="field-input !h-12 !pl-10" placeholder="Search questions…">
      </div>
    </MarketingPageHero>
    <div class="container-page grid gap-10 pb-24 lg:grid-cols-[220px_1fr]">
      <nav aria-label="Topics" class="flex gap-2 overflow-x-auto lg:flex-col">
        <button
          v-for="c in categories" :key="c" type="button" :aria-pressed="category === c"
          :class="['rounded-xl px-4 py-2 text-left text-sm font-semibold whitespace-nowrap transition-colors', category === c ? 'bg-charcoal text-white' : 'text-muted hover:bg-white hover:text-ink']"
          @click="category = c"
        >{{ label(c) }}</button>
      </nav>
      <div>
        <UiSpinner v-if="status === 'pending' && !faqs.length" />
        <UiAccordion v-else-if="filtered.length" :items="filtered" />
        <UiEmpty v-else title="No matching answers" description="Try another search, or ask our team directly.">
          <UiButton to="/contact" variant="secondary">Contact support</UiButton>
        </UiEmpty>
        <div class="mt-10 grid gap-4 sm:grid-cols-2">
          <NuxtLink to="/docs" class="card p-6 transition-shadow hover:shadow-[var(--shadow-lift)]"><p class="font-bold">Documentation</p><p class="mt-1 text-sm text-muted">Printing guidelines, analytics definitions and the API.</p></NuxtLink>
          <NuxtLink to="/contact" class="card p-6 transition-shadow hover:shadow-[var(--shadow-lift)]"><p class="font-bold">Contact us</p><p class="mt-1 text-sm text-muted">Sales, support, billing and privacy requests.</p></NuxtLink>
        </div>
      </div>
    </div>
  </div>
</template>
