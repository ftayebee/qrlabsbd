<script setup lang="ts">
import { FileUp, Lock } from 'lucide-vue-next'
import type { QrGenerator } from '~/composables/useQrGenerator'
import type { QrContentMap } from '~/types/qr'
import { formatBytes } from '~/utils/format'

const props = defineProps<{ gen: QrGenerator; showErrors: boolean }>()
const { type, contents } = props.gen
const errs = computed(() => (props.showErrors ? props.gen.errors.value : {}))

const { user } = useAuth()
const api = useApi()
const toast = useToast()
const uploading = ref(false)

const socialOptions = [
  ['instagram', 'Instagram'], ['facebook', 'Facebook'], ['x', 'X'], ['linkedin', 'LinkedIn'], ['tiktok', 'TikTok'], ['youtube', 'YouTube'],
  ['github', 'GitHub'], ['whatsapp', 'WhatsApp'], ['telegram', 'Telegram'], ['pinterest', 'Pinterest'], ['threads', 'Threads'], ['website', 'Website'],
].map(([value, label]) => ({ value: value!, label: label! }))

const pdf = computed(() => contents.pdf as QrContentMap['pdf'] & { fileSize?: number })

async function uploadPdf(e: Event) {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file) return
  if (file.type !== 'application/pdf') { toast.error('Choose a PDF file.'); return }
  uploading.value = true
  try {
    const form = new FormData()
    form.append('purpose', 'pdf')
    form.append('file', file)
    const res = await api.upload<{ data: { id: number; name: string; size: number } }>('/files', form)
    Object.assign(contents.pdf, { fileId: res.data.id, fileName: res.data.name, fileSize: res.data.size })
    if (!contents.pdf.title) contents.pdf.title = res.data.name.replace(/\.pdf$/i, '')
    toast.success('PDF uploaded.')
  } catch (err) {
    toast.error((err as Error).message)
  } finally {
    uploading.value = false
  }
}

const contactFields: { key: keyof QrContentMap['contact']; label: string; type?: string; half?: boolean; placeholder?: string; autocomplete?: string }[] = [
  { key: 'firstName', label: 'First name', half: true, autocomplete: 'given-name' },
  { key: 'lastName', label: 'Last name', half: true, autocomplete: 'family-name' },
  { key: 'company', label: 'Company', half: true, autocomplete: 'organization' },
  { key: 'jobTitle', label: 'Job title', half: true, autocomplete: 'organization-title' },
  { key: 'phone', label: 'Work phone', type: 'tel', half: true, placeholder: '+1 555 123 4567' },
  { key: 'mobile', label: 'Mobile', type: 'tel', half: true },
  { key: 'email', label: 'Email', type: 'email', half: true },
  { key: 'website', label: 'Website', type: 'url', half: true, placeholder: 'https://' },
  { key: 'street', label: 'Street address' },
  { key: 'city', label: 'City', half: true },
  { key: 'region', label: 'State / region', half: true },
  { key: 'postcode', label: 'Postcode', half: true },
  { key: 'country', label: 'Country', half: true },
]
</script>

<template>
  <div class="space-y-4">
    <!-- URL -->
    <template v-if="type === 'url'">
      <UiField label="Destination URL" id="f-url" :error="errs.url" required hint="Dynamic codes let you change this later.">
        <input id="f-url" v-model.trim="contents.url.url" type="url" inputmode="url" class="field-input" placeholder="https://example.com/menu" autocomplete="url" maxlength="2048">
      </UiField>
    </template>

    <!-- Text -->
    <template v-else-if="type === 'text'">
      <UiField label="Text" id="f-text" :error="errs.text" required :hint="`${contents.text.text.length} / 1,200 characters`">
        <textarea id="f-text" v-model="contents.text.text" rows="5" class="field-input" maxlength="1200" placeholder="Type the message people will see" />
      </UiField>
    </template>

    <!-- Phone -->
    <template v-else-if="type === 'phone'">
      <UiField label="Phone number" id="f-phone" :error="errs.phone" required hint="Include the country code.">
        <input id="f-phone" v-model="contents.phone.phone" type="tel" inputmode="tel" class="field-input" placeholder="+44 20 7946 0000" autocomplete="tel">
      </UiField>
    </template>

    <!-- SMS -->
    <template v-else-if="type === 'sms'">
      <UiField label="Phone number" id="f-sms-phone" :error="errs.phone" required>
        <input id="f-sms-phone" v-model="contents.sms.phone" type="tel" inputmode="tel" class="field-input" placeholder="+1 555 123 4567">
      </UiField>
      <UiField label="Pre-filled message" id="f-sms-msg" :error="errs.message" hint="Optional. Some phones ignore pre-filled text.">
        <textarea id="f-sms-msg" v-model="contents.sms.message" rows="3" maxlength="300" class="field-input" placeholder="JOIN" />
      </UiField>
    </template>

    <!-- Email -->
    <template v-else-if="type === 'email'">
      <UiField label="Recipient" id="f-mail-to" :error="errs.to" required hint="Separate several addresses with commas.">
        <input id="f-mail-to" v-model.trim="contents.email.to" type="email" multiple class="field-input" placeholder="hello@example.com">
      </UiField>
      <UiField label="Subject" id="f-mail-subject" :error="errs.subject">
        <input id="f-mail-subject" v-model="contents.email.subject" type="text" maxlength="200" class="field-input" placeholder="Booking enquiry">
      </UiField>
      <UiField label="Message" id="f-mail-body" :error="errs.body">
        <textarea id="f-mail-body" v-model="contents.email.body" rows="4" maxlength="600" class="field-input" />
      </UiField>
    </template>

    <!-- Wi-Fi -->
    <template v-else-if="type === 'wifi'">
      <UiField label="Network name (SSID)" id="f-ssid" :error="errs.ssid" required>
        <input id="f-ssid" v-model="contents.wifi.ssid" type="text" maxlength="32" class="field-input" autocomplete="off" spellcheck="false">
      </UiField>
      <UiField label="Security" id="f-sec">
        <select id="f-sec" v-model="contents.wifi.security" class="field-input">
          <option value="WPA">WPA / WPA2 / WPA3</option>
          <option value="WEP">WEP (legacy)</option>
          <option value="nopass">No password</option>
        </select>
      </UiField>
      <UiField v-if="contents.wifi.security !== 'nopass'" label="Password" id="f-wpass" :error="errs.password" required>
        <input id="f-wpass" v-model="contents.wifi.password" type="password" maxlength="63" class="field-input" autocomplete="new-password" spellcheck="false">
      </UiField>
      <label class="flex items-center gap-2 text-sm text-ink"><input v-model="contents.wifi.hidden" type="checkbox" class="size-4 accent-[var(--color-brand)]"> Hidden network</label>
      <p class="flex items-center gap-1.5 text-xs text-muted"><Lock class="size-3.5" /> Generated in your browser. The password is never sent to analytics or logs.</p>
    </template>

    <!-- Contact & Business card -->
    <template v-else-if="type === 'contact' || type === 'business_card'">
      <template v-if="type === 'business_card'">
        <UiField label="Headline" id="f-headline" hint="One line under your name, e.g. “Product designer at Acme”.">
          <input id="f-headline" v-model="contents.business_card.headline" type="text" maxlength="120" class="field-input">
        </UiField>
      </template>
      <div class="grid gap-4 sm:grid-cols-2">
        <UiField
          v-for="f in contactFields" :key="f.key" :label="f.label" :id="`f-c-${f.key}`" :error="errs[f.key]"
          :required="f.key === 'firstName'" :class="f.half ? '' : 'sm:col-span-2'"
        >
          <input :id="`f-c-${f.key}`" v-model="(contents[type] as Record<string, string>)[f.key]" :type="f.type ?? 'text'" class="field-input" :placeholder="f.placeholder" :autocomplete="f.autocomplete">
        </UiField>
      </div>
      <template v-if="type === 'business_card'">
        <UiField label="Short bio" id="f-bio">
          <textarea id="f-bio" v-model="contents.business_card.bio" rows="3" maxlength="600" class="field-input" />
        </UiField>
        <UiField label="Accent colour" id="f-accent">
          <div class="flex items-center gap-3">
            <input id="f-accent" v-model="contents.business_card.accentColor" type="color" class="h-10 w-14 cursor-pointer rounded-lg border border-line-strong bg-white p-1">
            <span class="text-sm text-muted">{{ contents.business_card.accentColor ?? '#1DB45A' }}</span>
          </div>
        </UiField>
        <div>
          <p class="field-label">Links</p>
          <QrLinkList
            :items="(contents.business_card.links as unknown as Record<string, string>[])" :errors="errs" :max="10"
            :fields="[{ key: 'label', label: 'Label', placeholder: 'Portfolio' }, { key: 'url', label: 'URL', type: 'url', placeholder: 'https://' }]"
            :template="() => ({ label: '', url: '' })"
          />
        </div>
      </template>
    </template>

    <!-- App -->
    <template v-else-if="type === 'app'">
      <UiField label="App name" id="f-app-name" :error="errs.name" required>
        <input id="f-app-name" v-model="contents.app.name" type="text" maxlength="80" class="field-input">
      </UiField>
      <UiField label="App Store URL" id="f-ios" :error="errs.iosUrl">
        <input id="f-ios" v-model.trim="contents.app.iosUrl" type="url" class="field-input" placeholder="https://apps.apple.com/app/id…">
      </UiField>
      <UiField label="Google Play URL" id="f-android" :error="errs.androidUrl">
        <input id="f-android" v-model.trim="contents.app.androidUrl" type="url" class="field-input" placeholder="https://play.google.com/store/apps/details?id=…">
      </UiField>
      <UiField label="Fallback website" id="f-fallback" :error="errs.fallbackUrl" hint="Shown on desktops. Static codes use this (or the first store link) only.">
        <input id="f-fallback" v-model.trim="contents.app.fallbackUrl" type="url" class="field-input" placeholder="https://">
      </UiField>
    </template>

    <!-- Multiple URLs & Social -->
    <template v-else-if="type === 'multi_url' || type === 'social'">
      <UiField label="Page title" id="f-page-title" :error="errs.title" required>
        <input id="f-page-title" v-model="contents[type].title" type="text" maxlength="100" class="field-input" :placeholder="type === 'social' ? 'Follow us' : 'Our links'">
      </UiField>
      <UiField label="Description" id="f-page-desc">
        <textarea id="f-page-desc" v-model="contents[type].description" rows="2" maxlength="300" class="field-input" />
      </UiField>
      <div>
        <p class="field-label">{{ type === 'social' ? 'Profiles' : 'Links' }}</p>
        <QrLinkList
          v-if="type === 'multi_url'" :items="(contents.multi_url.links as unknown as Record<string, string>[])" :errors="errs"
          :fields="[{ key: 'label', label: 'Label', placeholder: 'Book a table' }, { key: 'url', label: 'URL', type: 'url', placeholder: 'https://' }]"
          :template="() => ({ label: '', url: '' })"
        />
        <QrLinkList
          v-else :items="(contents.social.links as unknown as Record<string, string>[])" :errors="errs" :max="15" add-label="Add profile"
          :fields="[{ key: 'platform', label: 'Network', type: 'select', options: socialOptions }, { key: 'url', label: 'Profile URL', type: 'url', placeholder: 'https://instagram.com/yourbrand' }]"
          :template="() => ({ platform: 'instagram', url: '' })"
        />
      </div>
    </template>

    <!-- PDF -->
    <template v-else-if="type === 'pdf'">
      <UiField label="Document title" id="f-pdf-title" :error="errs.title" required>
        <input id="f-pdf-title" v-model="contents.pdf.title" type="text" maxlength="120" class="field-input" placeholder="Dinner menu">
      </UiField>
      <div>
        <p class="field-label">PDF file</p>
        <div v-if="!user" class="rounded-2xl border border-dashed border-line-strong bg-canvas/60 p-5 text-sm text-muted">
          PDF codes are hosted for you. <NuxtLink to="/register" class="font-semibold text-brand-700 underline">Create a free account</NuxtLink> to upload a file.
        </div>
        <label v-else :class="['flex cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border border-dashed px-5 py-7 text-center text-sm transition-colors', errs.file ? 'border-danger bg-orange-50/50' : 'border-line-strong bg-canvas/60 hover:border-brand hover:bg-soft/50']">
          <FileUp class="size-6 text-brand-700" />
          <span v-if="uploading" class="font-medium text-ink">Uploading…</span>
          <span v-else-if="pdf.fileName" class="font-medium text-ink">{{ pdf.fileName }}<span v-if="pdf.fileSize" class="text-muted"> · {{ formatBytes(pdf.fileSize) }}</span></span>
          <span v-else class="font-medium text-ink">Choose a PDF to upload</span>
          <span class="text-xs text-muted">Stored privately with a randomised name. Size limits depend on your plan.</span>
          <input type="file" accept="application/pdf" class="sr-only" :disabled="uploading" @change="uploadPdf">
        </label>
        <p v-if="errs.file" class="field-error">{{ errs.file }}</p>
      </div>
    </template>
  </div>
</template>
