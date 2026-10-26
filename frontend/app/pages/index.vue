<script setup lang="ts">
import { brand } from '~/brand.config'

const config = useRuntimeConfig()
useSeoMeta({
  title: 'Dynamic QR Code Generator with Analytics',
  titleTemplate: `${brand.name} – %s`,
  description: 'Create static and dynamic QR codes for links, PDFs, Wi-Fi, vCards and more. Customise the design, change destinations after printing and track every scan.',
  ogTitle: `${brand.name} – Make Every Scan Count`,
  ogDescription: 'Create, customise and track QR codes. Edit destinations after printing with privacy-first analytics.',
})
useHead({ link: [{ rel: 'canonical', href: config.public.siteUrl }] })
useSchemaOrg([
  defineWebSite({ name: brand.name }),
  defineWebPage(),
  { '@type': 'SoftwareApplication', name: brand.name, applicationCategory: 'BusinessApplication', operatingSystem: 'Web' },
])
</script>

<template>
  <div>
    <MarketingHeroSection />
    <MarketingValueSection />
    <MarketingTypeShowcase />
    <MarketingDynamicSection />
    <MarketingCustomizeSection />
    <MarketingAnalyticsSection />
    <MarketingUseCasesSection />
    <MarketingComparisonSection />
    <section id="pricing" class="section" aria-labelledby="pricing-title">
      <div class="container-page">
        <AppReveal class="mx-auto max-w-2xl text-center">
          <p class="eyebrow">Pricing</p>
          <h2 id="pricing-title" class="section-title mt-4">Start free. Upgrade when it pays for itself.</h2>
          <p class="section-lead">Static codes are free forever. Paid plans add dynamic codes, analytics history and team seats.</p>
        </AppReveal>
        <div class="mt-12"><MarketingPricingPlans /></div>
        <p class="mt-8 text-center text-sm"><NuxtLink to="/pricing" class="font-semibold text-brand-700 hover:underline">Compare every feature →</NuxtLink></p>
      </div>
    </section>
    <MarketingFaqSection />
    <MarketingFinalCta />
  </div>
</template>
