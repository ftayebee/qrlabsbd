<script setup lang="ts">
const props = withDefaults(defineProps<{ category?: string; title?: string; limit?: number }>(), { title: 'Questions, answered', limit: 8 })
const { data: faqs } = useFaqs(props.category)
const items = computed(() => faqs.value.slice(0, props.limit))

useSchemaOrg(computed(() => items.value.length ? [{
  '@type': 'FAQPage',
  mainEntity: items.value.map(f => ({ '@type': 'Question', name: f.question, acceptedAnswer: { '@type': 'Answer', text: f.answer } })),
}] : []))
</script>

<template>
  <section class="section" aria-labelledby="faq-title">
    <div class="container-page grid gap-10 lg:grid-cols-[1fr_1.6fr]">
      <AppReveal>
        <p class="eyebrow">FAQ</p>
        <h2 id="faq-title" class="section-title mt-4">{{ title }}</h2>
        <p class="section-lead">Can’t find what you need? <NuxtLink to="/contact" class="font-semibold text-brand-700 hover:underline">Contact our team</NuxtLink>.</p>
      </AppReveal>
      <AppReveal :delay="0.08">
        <UiAccordion v-if="items.length" :items="items" />
        <UiEmpty v-else title="No questions yet" description="Check back soon." />
      </AppReveal>
    </div>
  </section>
</template>
