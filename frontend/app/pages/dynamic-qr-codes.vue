<script setup lang="ts">
import { ArrowRight } from 'lucide-vue-next'

const crumbs = [{ label: 'Dynamic QR codes' }]
usePageSeo({
  title: 'Dynamic QR Codes – Edit After Printing and Track Scans',
  description: 'Dynamic QR codes encode a short link you control. Change the destination any time, pause campaigns and see privacy-first scan analytics.',
  path: '/dynamic-qr-codes',
  crumbs,
})

const sections = [
  { title: 'How a dynamic code works', body: 'Instead of storing your destination, the code stores a short link on our domain, such as qrio.app/r/Ab3dE7q. When someone scans it, our redirect service looks up the current destination and sends them there in milliseconds. Because the printed code never changes, you can update where it points as often as you need.' },
  { title: 'What happens on each scan', body: 'We record the time, device category, operating system, browser, referrer domain and, when your CDN provides it, the country. We never store the scanner’s IP address. A salted hash that rotates every day lets us estimate unique visitors without being able to identify anyone.' },
  { title: 'Bots and link previews', body: 'Messaging apps and social networks often “scan” links to build previews. These are detected by their user agent and excluded from your totals; you can still see how many were filtered.' },
  { title: 'Safety and abuse prevention', body: 'Destinations must use http or https, cannot contain credentials, cannot point to private networks and cannot point at another dynamic code, which prevents redirect loops. Anyone can report a suspicious code from its landing page.' },
  { title: 'If your subscription ends', body: 'Printed codes are never broken by billing changes. After a paid plan ends you keep full access for a grace period. After that, codes keep redirecting, but codes above the free plan limit become read-only until you upgrade or archive some.' },
]
</script>

<template>
  <div>
    <MarketingPageHero eyebrow="Dynamic QR codes" title="Print once. Change it whenever you like." lead="Dynamic codes give every printed QR code a second life: edit the destination, pause a campaign and measure results, without reprinting." :crumbs="crumbs">
      <div class="flex flex-col gap-3 sm:flex-row">
        <UiButton to="/qr-code-generator?type=url">Create a dynamic code <ArrowRight class="size-4" /></UiButton>
        <UiButton to="/pricing" variant="secondary">See plans</UiButton>
      </div>
    </MarketingPageHero>
    <div class="container-page grid gap-6 pb-12 lg:grid-cols-2">
      <AppReveal v-for="(s, i) in sections" :key="s.title" :delay="(i % 2) * 0.06" :class="['card p-7', i === 0 ? 'lg:col-span-2' : '']">
        <h2 class="text-xl font-bold">{{ s.title }}</h2>
        <p class="mt-3 leading-relaxed text-muted">{{ s.body }}</p>
      </AppReveal>
    </div>
    <MarketingComparisonSection />
    <MarketingFaqSection category="analytics" title="Analytics questions" />
    <MarketingFinalCta />
  </div>
</template>
