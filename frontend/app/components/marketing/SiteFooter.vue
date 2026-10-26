<script setup lang="ts">
import { brand } from '~/brand.config'
import { QR_TYPE_LIST } from '~/utils/qr/catalog'

const { data: settings } = usePublicSettings()
const social = computed(() => [
  { label: 'X', url: settings.value['social.x'] as string },
  { label: 'LinkedIn', url: settings.value['social.linkedin'] as string },
  { label: 'Instagram', url: settings.value['social.instagram'] as string },
].filter(s => typeof s.url === 'string' && /^https:\/\//.test(s.url)))

const year = new Date().getFullYear()
const columns = [
  { title: 'Product', links: [
    { label: 'QR code generator', to: '/qr-code-generator' },
    { label: 'Dynamic QR codes', to: '/dynamic-qr-codes' },
    { label: 'Pricing', to: '/pricing' },
    { label: 'Use cases', to: '/use-cases' },
  ] },
  { title: 'Popular types', links: QR_TYPE_LIST.filter(t => ['url', 'pdf', 'wifi', 'business_card', 'social'].includes(t.type)).map(t => ({ label: `${t.label} QR`, to: `/qr-codes/${t.slug}` })) },
  { title: 'Resources', links: [
    { label: 'Help & FAQ', to: '/help' },
    { label: 'Documentation', to: '/docs' },
    { label: 'Contact', to: '/contact' },
  ] },
  { title: 'Legal', links: [
    { label: 'Terms', to: '/legal/terms' },
    { label: 'Privacy', to: '/legal/privacy' },
    { label: 'Cookies', to: '/legal/cookies' },
    { label: 'Acceptable use', to: '/legal/acceptable-use' },
  ] },
]
</script>

<template>
  <footer class="border-t border-line bg-white">
    <div class="container-page grid gap-12 py-16 lg:grid-cols-[1.2fr_2fr]">
      <div class="max-w-sm">
        <AppLogo />
        <p class="mt-4 text-sm leading-relaxed text-muted">{{ brand.tagline }}</p>
        <a :href="`mailto:${(settings['brand.support_email'] as string) || brand.supportEmail}`" class="mt-4 inline-block text-sm font-semibold text-brand-700 hover:underline">
          {{ (settings['brand.support_email'] as string) || brand.supportEmail }}
        </a>
        <div v-if="social.length" class="mt-6 flex gap-2">
          <a v-for="s in social" :key="s.label" :href="s.url" target="_blank" rel="noopener noreferrer" class="rounded-full border border-line px-3 py-1.5 text-xs font-semibold text-muted transition-colors hover:border-ink/30 hover:text-ink">{{ s.label }}</a>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-8 sm:grid-cols-4">
        <div v-for="col in columns" :key="col.title">
          <h2 class="text-xs font-bold uppercase tracking-wider text-ink">{{ col.title }}</h2>
          <ul class="mt-4 space-y-2.5">
            <li v-for="l in col.links" :key="l.to"><NuxtLink :to="l.to" class="text-sm text-muted transition-colors hover:text-ink">{{ l.label }}</NuxtLink></li>
          </ul>
        </div>
      </div>
    </div>
    <div class="border-t border-line">
      <div class="container-page flex flex-col gap-2 py-6 text-xs text-muted sm:flex-row sm:items-center sm:justify-between">
        <p>© {{ year }} {{ brand.name }}. All rights reserved.</p>
        <p>We never store the IP addresses of people who scan your codes.</p>
      </div>
    </div>
  </footer>
</template>
