<script setup lang="ts">
const crumbs = [{ label: 'Pricing' }]
usePageSeo({
  title: 'Pricing – Free Static Codes, Paid Dynamic Plans',
  description: 'Static QR codes are free forever. Compare Pro and Business plans for dynamic codes, analytics history, team seats, logos and exports.',
  path: '/pricing',
  crumbs,
})
const { data: settings } = usePublicSettings()
</script>

<template>
  <div>
    <MarketingPageHero eyebrow="Pricing" title="Simple plans that grow with your codes" lead="Every plan includes unlimited static codes and print-ready downloads. Prices exclude any applicable taxes, which are shown at checkout." :crumbs="crumbs" />
    <div class="container-page pb-16">
      <MarketingPricingPlans show-table />
      <UiAlert v-if="settings['billing.grace_days_note']" tone="info" class="mx-auto mt-12 max-w-3xl" title="Your codes never break when billing changes">
        {{ settings['billing.grace_days_note'] }}
      </UiAlert>
    </div>
    <MarketingFaqSection category="billing" title="Billing questions" />
  </div>
</template>
