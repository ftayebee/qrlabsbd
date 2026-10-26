<script setup lang="ts">
import { Check, Download } from 'lucide-vue-next'
import type { QrDesign } from '~/types/qr'
import { DEFAULT_DESIGN } from '~/types/qr'
import { encodeText, encodeUrl, encodeWifi, isValidHttpUrl } from '~/utils/qr/payload'
import { exportQr } from '~/utils/qr/render'

/** A small but fully working static generator for the hero. Everything runs in the browser. */
type Kind = 'url' | 'wifi' | 'text'
const kind = ref<Kind>('url')
const url = ref('https://example.com')
const text = ref('Hello from the front desk!')
const ssid = ref('Guest Wi-Fi')
const pass = ref('')

const palettes = [
  { fg: '#14251A', corner: '#1DB45A', bg: '#FFFFFF' },
  { fg: '#0F172A', corner: '#0F172A', bg: '#FFFFFF' },
  { fg: '#1D4ED8', corner: '#1D4ED8', bg: '#EEF4FF' },
  { fg: '#7C2D12', corner: '#EA580C', bg: '#FFF7ED' },
]
const palette = ref(0)
const design = computed<QrDesign>(() => ({ ...DEFAULT_DESIGN, foreground: palettes[palette.value]!.fg, cornerColor: palettes[palette.value]!.corner, background: palettes[palette.value]!.bg }))

const value = computed(() => {
  if (kind.value === 'url') return isValidHttpUrl(url.value) ? encodeUrl({ url: url.value }) : ''
  if (kind.value === 'text') return text.value.trim() ? encodeText({ text: text.value }) : ''
  return ssid.value.trim() ? encodeWifi({ ssid: ssid.value, security: pass.value ? 'WPA' : 'nopass', password: pass.value }) : ''
})

const busy = ref(false)
const done = ref(false)
async function download() {
  if (!value.value) return
  busy.value = true
  try {
    await exportQr(value.value, design.value, 'png', `qrio-${kind.value}`)
    done.value = true
    setTimeout(() => (done.value = false), 1800)
  } finally { busy.value = false }
}
</script>

<template>
  <div class="card relative overflow-hidden p-5 sm:p-6">
    <div class="flex items-center justify-between gap-3">
      <UiTabs v-model="kind" size="sm" :tabs="[{ value: 'url', label: 'Website' }, { value: 'wifi', label: 'Wi-Fi' }, { value: 'text', label: 'Text' }]" />
      <span class="hidden items-center gap-1.5 text-xs font-medium text-muted sm:flex"><span class="size-1.5 rounded-full bg-brand" /> Live</span>
    </div>

    <div class="mt-5 grid gap-5 sm:grid-cols-[1fr_auto] sm:items-center">
      <div class="min-w-0 space-y-3">
        <template v-if="kind === 'url'">
          <label for="hero-url" class="sr-only">Website address</label>
          <input id="hero-url" v-model.trim="url" type="url" inputmode="url" class="field-input" placeholder="https://your-site.com">
          <p v-if="url && !value" class="field-error">Enter a full web address.</p>
        </template>
        <template v-else-if="kind === 'wifi'">
          <label for="hero-ssid" class="sr-only">Network name</label>
          <input id="hero-ssid" v-model="ssid" type="text" maxlength="32" class="field-input" placeholder="Network name">
          <label for="hero-pass" class="sr-only">Password</label>
          <input id="hero-pass" v-model="pass" type="password" maxlength="63" class="field-input" placeholder="Password (optional)" autocomplete="new-password">
        </template>
        <template v-else>
          <label for="hero-text" class="sr-only">Text</label>
          <textarea id="hero-text" v-model="text" rows="3" maxlength="300" class="field-input" />
        </template>

        <div class="flex items-center gap-2" role="radiogroup" aria-label="Colour">
          <button
            v-for="(p, i) in palettes" :key="i" type="button" role="radio" :aria-checked="palette === i" :aria-label="`Colour ${i + 1}`"
            :class="['grid size-8 place-items-center rounded-full border-2 transition-transform hover:scale-110', palette === i ? 'border-brand' : 'border-transparent']"
            @click="palette = i"
          >
            <span class="size-5 rounded-full" :style="{ background: `linear-gradient(135deg, ${p.fg} 50%, ${p.corner} 50%)` }" />
          </button>
        </div>

        <div class="flex flex-wrap gap-2 pt-1">
          <UiButton size="sm" :disabled="!value" :loading="busy" @click="download">
            <Check v-if="done" class="size-4" /><Download v-else-if="!busy" class="size-4" /> {{ done ? 'Downloaded' : 'Download PNG' }}
          </UiButton>
          <UiButton to="/qr-code-generator" variant="secondary" size="sm">More options</UiButton>
        </div>
      </div>
      <div class="mx-auto rounded-2xl bg-canvas p-3">
        <QrPreview :value="value || 'https://qrio.app'" :design="design" :size="176" :dimmed="!value" />
      </div>
    </div>
  </div>
</template>
