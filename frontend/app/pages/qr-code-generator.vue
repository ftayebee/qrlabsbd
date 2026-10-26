<script setup lang="ts">
import { QR_TYPES, type QrType } from '~/types/qr'

const route = useRoute()
const queryType = route.query.type as QrType
const initialType = QR_TYPES.includes(queryType) ? queryType : 'url'
const crumbs = [{ label: 'QR code generator' }]

usePageSeo({
  title: 'Free QR Code Generator – 12 Types, Custom Designs',
  description: 'Generate QR codes for URLs, PDFs, Wi-Fi, vCards, SMS, email, apps and social profiles. Customise colours, logos and frames, then download PNG, SVG or PDF.',
  path: '/qr-code-generator',
  crumbs,
})

const router = useRouter()
function onSaved(record: { id: number }) {
  router.push(`/dashboard/qr-codes/${record.id}`)
}
</script>

<template>
  <div>
    <MarketingPageHero
      eyebrow="Generator" title="Create a QR code"
      lead="Pick a type, add your content and style it. Static codes download instantly with no account; dynamic and hosted codes are saved to your library."
      :crumbs="crumbs"
    />
    <div class="container-page pb-24">
      <ClientOnly>
        <QrGenerator :initial-type="initialType" @saved="onSaved" />
        <template #fallback><UiSpinner label="Loading generator…" /></template>
      </ClientOnly>
    </div>
  </div>
</template>
