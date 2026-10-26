<script setup lang="ts">
import { AppWindow, Building2, ChevronRight, Download, ExternalLink, FileText, Flag, Globe, Mail, MapPin, Phone, Smartphone } from 'lucide-vue-next'
import { brand } from '~/brand.config'
import { formatBytes } from '~/utils/format'

/**
 * Public landing page for hosted dynamic codes. All owner-supplied text is
 * rendered with interpolation (escaped); links come from validated http(s) URLs.
 */
definePageMeta({ layout: 'hosted' })

interface Link { label: string | null; url: string; platform: string | null }
interface HostedPage { type: string; name: string; code: string; content: Record<string, unknown> & { links?: Link[] } }

const route = useRoute()
const code = String(route.params.code)
const api = useApi()
const { data: page, error } = await useAsyncData(`hosted-${code}`, async () => (await api.get<{ data: HostedPage }>(`/public/qr/${code}`)).data)

const c = computed(() => page.value?.content ?? {})
const str = (k: string) => (typeof c.value[k] === 'string' ? (c.value[k] as string) : '')
const fullName = computed(() => [str('firstName'), str('lastName')].filter(Boolean).join(' '))
const title = computed(() => {
  if (!page.value) return 'Unavailable'
  return ({ multi_url: str('title'), social: str('title'), pdf: str('title'), app: str('name') } as Record<string, string>)[page.value.type] ?? fullName.value
})
const accent = computed(() => (/^#[0-9a-f]{6}$/i.test(str('accentColor')) ? str('accentColor') : brand.colors.primary))
const address = computed(() => ['street', 'city', 'region', 'postcode', 'country'].map(str).filter(Boolean).join(', '))
const isIos = ref(false)
const isAndroid = ref(false)
onMounted(() => { isIos.value = /iPhone|iPad|iPod/.test(navigator.userAgent); isAndroid.value = /Android/.test(navigator.userAgent) })

const platformLabel: Record<string, string> = {
  instagram: 'Instagram', facebook: 'Facebook', x: 'X', linkedin: 'LinkedIn', tiktok: 'TikTok', youtube: 'YouTube',
  github: 'GitHub', whatsapp: 'WhatsApp', telegram: 'Telegram', pinterest: 'Pinterest', threads: 'Threads', website: 'Website',
}

useSeoMeta({ title: () => title.value || 'Shared page', robots: 'noindex, nofollow', description: () => str('description') || str('headline') || undefined })

const statusMessage = computed(() => {
  const e = error.value
  if (!e) return null
  return e.message || 'This page is not available.'
})

const hostOf = (url: string) => { try { return new URL(url).hostname } catch { return url } }

const reportOpen = ref(false)
const report = reactive({ reason: 'phishing', details: '', email: '' })
const reportSent = ref(false)
async function sendReport() {
  try {
    await api.post(`/public/qr/${code}/report`, report)
    reportSent.value = true
  } catch (e) { useToast().error((e as Error).message) }
}
</script>

<template>
  <div>
    <div v-if="!page" class="card p-8 text-center">
      <h1 class="text-xl font-bold">This page isn’t available</h1>
      <p class="mt-2 text-sm text-muted">{{ statusMessage }}</p>
    </div>

    <article v-else class="space-y-4">
      <header class="card overflow-hidden text-center">
        <div class="h-20" :style="{ background: `linear-gradient(135deg, ${accent}, #14251A)` }" />
        <div class="-mt-10 px-6 pb-7">
          <div class="mx-auto grid size-20 place-items-center rounded-3xl border-4 border-white bg-white text-2xl font-extrabold shadow-[var(--shadow-card)]" :style="{ color: accent }">
            <FileText v-if="page.type === 'pdf'" class="size-8" />
            <AppWindow v-else-if="page.type === 'app'" class="size-8" />
            <template v-else>{{ (title || '?').slice(0, 1).toUpperCase() }}</template>
          </div>
          <h1 class="mt-4 text-2xl font-extrabold">{{ title }}</h1>
          <p v-if="str('headline') || str('jobTitle')" class="mt-1 text-sm font-medium text-muted">{{ str('headline') || [str('jobTitle'), str('company')].filter(Boolean).join(' · ') }}</p>
          <p v-if="str('description') || str('bio')" class="mt-3 text-sm leading-relaxed text-muted">{{ str('description') || str('bio') }}</p>
        </div>
      </header>

      <!-- Links (multi_url, social, business_card) -->
      <ul v-if="c.links?.length" class="space-y-2.5">
        <li v-for="(l, i) in c.links" :key="i">
          <Motion as="a" :href="l.url" rel="noopener noreferrer nofollow" :initial="{ opacity: 0, y: 8 }" :animate="{ opacity: 1, y: 0 }" :transition="{ delay: i * 0.04 }" class="card flex items-center justify-between gap-3 px-5 py-4 font-semibold transition-transform hover:-translate-y-0.5">
            <span class="flex items-center gap-3">
              <Globe class="size-4 text-muted" aria-hidden="true" />
              {{ l.label || (l.platform ? platformLabel[l.platform] : null) || hostOf(l.url) }}
            </span>
            <ChevronRight class="size-4 text-muted" aria-hidden="true" />
          </Motion>
        </li>
      </ul>

      <!-- Contact details -->
      <section v-if="page.type === 'business_card' || page.type === 'contact'" class="card divide-y divide-line">
        <a v-if="str('phone')" :href="`tel:${str('phone')}`" class="flex items-center gap-3 px-5 py-4 text-sm"><Phone class="size-4 text-muted" /> {{ str('phone') }}</a>
        <a v-if="str('mobile')" :href="`tel:${str('mobile')}`" class="flex items-center gap-3 px-5 py-4 text-sm"><Smartphone class="size-4 text-muted" /> {{ str('mobile') }}</a>
        <a v-if="str('email')" :href="`mailto:${str('email')}`" class="flex items-center gap-3 px-5 py-4 text-sm"><Mail class="size-4 text-muted" /> {{ str('email') }}</a>
        <a v-if="str('website')" :href="str('website')" rel="noopener noreferrer nofollow" class="flex items-center gap-3 px-5 py-4 text-sm"><Globe class="size-4 text-muted" /> {{ str('website').replace(/^https?:\/\//, '') }}</a>
        <p v-if="str('company')" class="flex items-center gap-3 px-5 py-4 text-sm"><Building2 class="size-4 text-muted" /> {{ str('company') }}</p>
        <p v-if="address" class="flex items-center gap-3 px-5 py-4 text-sm"><MapPin class="size-4 shrink-0 text-muted" /> {{ address }}</p>
      </section>
      <a v-if="page.type === 'business_card' || page.type === 'contact'" :href="`/api/v1/public/qr/${code}/vcard`" class="flex w-full items-center justify-center gap-2 rounded-[var(--radius-control)] px-5 py-3.5 text-sm font-semibold text-white shadow-[var(--shadow-card)]" :style="{ background: '#14251A' }">
        <Download class="size-4" /> Save contact
      </a>

      <!-- PDF -->
      <section v-if="page.type === 'pdf'" class="card p-6 text-center">
        <template v-if="c.fileUrl">
          <p class="text-sm text-muted">{{ c.fileName }}<span v-if="c.fileSize"> · {{ formatBytes(Number(c.fileSize)) }}</span></p>
          <div class="mt-5 grid gap-2">
            <UiButton :href="String(c.fileUrl)" block>Open PDF <ExternalLink class="size-4" /></UiButton>
          </div>
        </template>
        <p v-else class="text-sm text-muted">The document is being updated. Please check back soon.</p>
      </section>

      <!-- App -->
      <section v-if="page.type === 'app'" class="grid gap-2">
        <UiButton v-if="str('iosUrl')" :href="str('iosUrl')" :variant="isAndroid ? 'secondary' : 'dark'" block>Download on the App Store</UiButton>
        <UiButton v-if="str('androidUrl')" :href="str('androidUrl')" :variant="isIos ? 'secondary' : 'dark'" block>Get it on Google Play</UiButton>
        <UiButton v-if="str('fallbackUrl')" :href="str('fallbackUrl')" variant="secondary" block>Visit website</UiButton>
      </section>

      <footer class="pt-6 text-center text-xs text-muted">
        <p>Made with <NuxtLink to="/" class="font-semibold text-ink hover:underline">{{ brand.name }}</NuxtLink></p>
        <button type="button" class="mt-3 inline-flex items-center gap-1 hover:text-ink" @click="reportOpen = true"><Flag class="size-3" /> Report this page</button>
      </footer>
    </article>

    <UiModal v-model:open="reportOpen" title="Report this page" description="Tell us if this page looks like a scam, phishing or other abuse.">
      <UiAlert v-if="reportSent" tone="success">Thank you. Our team will review it.</UiAlert>
      <form v-else id="report-form" class="space-y-4" @submit.prevent="sendReport">
        <UiField label="Reason" id="rep-reason">
          <select id="rep-reason" v-model="report.reason" class="field-input">
            <option value="phishing">Phishing or scam</option>
            <option value="malware">Malware</option>
            <option value="spam">Spam</option>
            <option value="impersonation">Impersonation</option>
            <option value="illegal">Illegal content</option>
            <option value="other">Other</option>
          </select>
        </UiField>
        <UiField label="Details" id="rep-details"><textarea id="rep-details" v-model="report.details" rows="3" maxlength="2000" class="field-input" /></UiField>
        <UiField label="Your email (optional)" id="rep-email"><input id="rep-email" v-model="report.email" type="email" class="field-input"></UiField>
      </form>
      <template v-if="!reportSent" #footer>
        <UiButton variant="secondary" @click="reportOpen = false">Cancel</UiButton>
        <UiButton type="submit" form="report-form">Send report</UiButton>
      </template>
    </UiModal>
  </div>
</template>
