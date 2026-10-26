<script setup lang="ts">
const crumbs = [{ label: 'Documentation' }]
usePageSeo({
  title: 'Documentation – Printing, Analytics and API',
  description: 'Printing guidelines for reliable QR codes, how scan analytics are calculated, data retention and the REST API overview.',
  path: '/docs',
  crumbs,
})

const sections = [
  { id: 'printing', title: 'Printing guidelines', items: [
    'Minimum size: 2 × 2 cm for close-range scans; roughly one tenth of the expected scanning distance in general.',
    'Keep a quiet zone (clear margin) of at least four modules around the code.',
    'Use dark modules on a light background with at least 4:1 contrast. Inverted codes fail on some older scanners.',
    'Add a logo only with error correction Q or H, and keep it under 30% of the code width.',
    'Prefer SVG or PDF for professional print; PNG is ideal for screens and office printers.',
    'Always test the final printed material with both an iPhone and an Android phone.',
  ] },
  { id: 'analytics', title: 'How analytics are calculated', items: [
    'Scans: every request to a dynamic code’s short link, excluding detected bots and link-preview crawlers.',
    'Unique scans (estimate): one per code per visitor per UTC day, where a visitor is a salted, daily-rotating hash of network address and browser. Shared Wi-Fi undercounts; switching networks overcounts.',
    'Country: taken from the country header supplied by the CDN or proxy in front of the redirect service. Shown as “Unknown” when not available.',
    'Device, OS and browser are coarse categories derived from the user agent; the user agent itself is not stored.',
    'Raw scan events are kept for 90 days; daily aggregates are kept for reporting according to your plan’s history.',
    'Static codes cannot be tracked because they never contact our servers.',
  ] },
  { id: 'api', title: 'REST API', items: [
    'All endpoints are versioned under /api/v1 and return JSON with a consistent { message, code, errors } error envelope.',
    'The web app authenticates with secure session cookies and CSRF protection. Rate limits apply to authentication, uploads, code creation and redirects.',
    'Dynamic redirects are served from /r/{code}; hosted files from /f/{token}.',
    'An OpenAPI description is published with the API at /docs/openapi.yaml.',
  ] },
]
</script>

<template>
  <div>
    <MarketingPageHero eyebrow="Documentation" title="Make codes that scan, and numbers you can trust" :crumbs="crumbs" />
    <div class="container-page grid gap-10 pb-24 lg:grid-cols-[200px_1fr]">
      <nav aria-label="On this page" class="hidden lg:block">
        <ul class="sticky top-24 space-y-2 text-sm">
          <li v-for="s in sections" :key="s.id"><a :href="`#${s.id}`" class="font-medium text-muted hover:text-ink">{{ s.title }}</a></li>
        </ul>
      </nav>
      <div class="space-y-6">
        <AppReveal v-for="s in sections" :id="s.id" :key="s.id" as="section" class="card scroll-mt-24 p-7">
          <h2 class="text-xl font-bold">{{ s.title }}</h2>
          <ul class="mt-4 list-disc space-y-2.5 pl-5 text-muted marker:text-brand">
            <li v-for="item in s.items" :key="item" class="leading-relaxed">{{ item }}</li>
          </ul>
        </AppReveal>
      </div>
    </div>
  </div>
</template>
