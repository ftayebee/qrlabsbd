<script setup lang="ts">
import { ImagePlus, RotateCcw, X } from 'lucide-vue-next'
import type { CornerSquareStyle, DotStyle, ErrorCorrection, FrameStyle, QrDesign } from '~/types/qr'
import { DEFAULT_DESIGN } from '~/types/qr'

const props = defineProps<{ design: QrDesign; canUploadLogo: boolean }>()
const d = props.design

const api = useApi()
const toast = useToast()
const uploading = ref(false)

const swatches = ['#14251A', '#1DB45A', '#0F172A', '#1D4ED8', '#7C2D12', '#6D28D9', '#BE123C', '#000000']
const dotStyles: { value: DotStyle; label: string }[] = [
  { value: 'square', label: 'Square' }, { value: 'rounded', label: 'Rounded' }, { value: 'dots', label: 'Dots' },
  { value: 'classy', label: 'Classy' }, { value: 'classy-rounded', label: 'Soft' }, { value: 'extra-rounded', label: 'Fluid' },
]
const cornerStyles: { value: CornerSquareStyle; label: string }[] = [
  { value: 'square', label: 'Square' }, { value: 'extra-rounded', label: 'Rounded' }, { value: 'dot', label: 'Circle' },
]
const frames: { value: FrameStyle; label: string }[] = [
  { value: 'none', label: 'None' }, { value: 'label-bottom', label: 'Label below' }, { value: 'label-top', label: 'Label above' }, { value: 'badge', label: 'Badge' },
]
const levels: { value: ErrorCorrection; label: string; hint: string }[] = [
  { value: 'L', label: 'L', hint: '7%' }, { value: 'M', label: 'M', hint: '15%' }, { value: 'Q', label: 'Q', hint: '25%' }, { value: 'H', label: 'H', hint: '30%' },
]

async function onLogo(e: Event) {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file) return
  if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) { toast.error('Use a PNG, JPG or WebP image.'); return }
  if (file.size > 1024 * 1024) { toast.error('Logos must be under 1 MB.'); return }

  if (props.canUploadLogo) {
    uploading.value = true
    try {
      const form = new FormData()
      form.append('purpose', 'logo')
      form.append('file', file)
      const res = await api.upload<{ data: { id: number; url: string } }>('/files', form)
      // Same-origin URL, so the logo can be embedded when exporting.
      d.logoUrl = new URL(res.data.url).pathname
      d.logoFileId = res.data.id
    } catch (err) {
      toast.error((err as Error).message)
    } finally { uploading.value = false }
  } else {
    // Guests and free plans: logo stays in the browser for preview and download only.
    d.logoUrl = await new Promise<string>((resolve) => { const r = new FileReader(); r.onload = () => resolve(r.result as string); r.readAsDataURL(file) })
  }
  if (d.errorCorrection === 'L' || d.errorCorrection === 'M') d.errorCorrection = 'H'
}

function removeLogo() {
  d.logoUrl = null
  d.logoFileId = null
}

function reset() {
  Object.assign(d, { ...DEFAULT_DESIGN })
  removeLogo()
}
</script>

<template>
  <div class="space-y-6">
    <section>
      <h3 class="mb-3 text-sm font-bold">Colours</h3>
      <div class="grid gap-4 sm:grid-cols-2">
        <UiField label="Code colour" id="d-fg">
          <div class="flex items-center gap-2">
            <input id="d-fg" v-model="d.foreground" type="color" class="h-10 w-12 shrink-0 cursor-pointer rounded-lg border border-line-strong bg-white p-1">
            <input v-model="d.foreground" type="text" class="field-input font-mono uppercase" maxlength="7" aria-label="Code colour hex">
          </div>
        </UiField>
        <UiField label="Background" id="d-bg">
          <div class="flex items-center gap-2">
            <input id="d-bg" v-model="d.background" type="color" class="h-10 w-12 shrink-0 cursor-pointer rounded-lg border border-line-strong bg-white p-1" :disabled="d.transparent">
            <input v-model="d.background" type="text" class="field-input font-mono uppercase" maxlength="7" aria-label="Background hex" :disabled="d.transparent">
          </div>
        </UiField>
      </div>
      <div class="mt-3 flex flex-wrap gap-2" aria-label="Colour presets">
        <button
          v-for="c in swatches" :key="c" type="button" :aria-label="`Use ${c}`"
          :class="['size-7 rounded-full border-2 transition-transform hover:scale-110', d.foreground.toUpperCase() === c ? 'border-brand ring-2 ring-brand/30' : 'border-white shadow']"
          :style="{ background: c }" @click="d.foreground = c"
        />
      </div>
      <UiField label="Corner colour" id="d-corner" class="mt-4">
        <div class="flex items-center gap-2">
          <input id="d-corner" v-model="d.cornerColor" type="color" class="h-10 w-12 shrink-0 cursor-pointer rounded-lg border border-line-strong bg-white p-1">
          <button type="button" class="text-xs font-semibold text-brand-700 hover:underline" @click="d.cornerColor = d.foreground">Match code colour</button>
        </div>
      </UiField>
      <div class="mt-4"><UiSwitch v-model="d.transparent" label="Transparent background" description="PNG and SVG only. Print on a plain, light surface." /></div>
    </section>

    <section>
      <h3 class="mb-3 text-sm font-bold">Pattern</h3>
      <div class="grid grid-cols-3 gap-2">
        <button
          v-for="s in dotStyles" :key="s.value" type="button" :aria-pressed="d.dotStyle === s.value"
          :class="['rounded-xl border px-2 py-2 text-xs font-semibold transition-colors', d.dotStyle === s.value ? 'border-brand bg-soft text-charcoal' : 'border-line bg-white text-muted hover:text-ink']"
          @click="d.dotStyle = s.value"
        >{{ s.label }}</button>
      </div>
      <p class="mt-4 mb-2 text-xs font-semibold text-muted">Corners</p>
      <div class="grid grid-cols-3 gap-2">
        <button
          v-for="s in cornerStyles" :key="s.value" type="button" :aria-pressed="d.cornerSquareStyle === s.value"
          :class="['rounded-xl border px-2 py-2 text-xs font-semibold transition-colors', d.cornerSquareStyle === s.value ? 'border-brand bg-soft text-charcoal' : 'border-line bg-white text-muted hover:text-ink']"
          @click="d.cornerSquareStyle = s.value; d.cornerDotStyle = s.value === 'square' ? 'square' : 'dot'"
        >{{ s.label }}</button>
      </div>
    </section>

    <section>
      <h3 class="mb-3 text-sm font-bold">Logo</h3>
      <div v-if="d.logoUrl" class="flex items-center gap-3 rounded-2xl border border-line bg-canvas/60 p-3">
        <img :src="d.logoUrl" alt="" class="size-10 rounded-lg bg-white object-contain p-1">
        <div class="flex-1">
          <label for="d-logo-size" class="text-xs font-semibold text-muted">Size {{ Math.round(d.logoSize * 100) }}%</label>
          <input id="d-logo-size" v-model.number="d.logoSize" type="range" min="0.12" max="0.3" step="0.01" class="w-full accent-[var(--color-brand)]">
        </div>
        <button type="button" class="rounded-lg p-2 text-muted hover:bg-white hover:text-danger" aria-label="Remove logo" @click="removeLogo"><X class="size-4" /></button>
      </div>
      <label v-else class="flex cursor-pointer items-center gap-3 rounded-2xl border border-dashed border-line-strong bg-canvas/60 px-4 py-4 text-sm transition-colors hover:border-brand">
        <ImagePlus class="size-5 text-brand-700" />
        <span>
          <span class="block font-semibold text-ink">{{ uploading ? 'Uploading…' : 'Add a logo' }}</span>
          <span class="block text-xs text-muted">PNG, JPG or WebP, under 1 MB.<template v-if="!canUploadLogo"> Saving logos requires a paid plan.</template></span>
        </span>
        <input type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" @change="onLogo">
      </label>
    </section>

    <section>
      <h3 class="mb-3 text-sm font-bold">Frame &amp; call to action</h3>
      <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
        <button
          v-for="f in frames" :key="f.value" type="button" :aria-pressed="d.frame === f.value"
          :class="['rounded-xl border px-2 py-2 text-xs font-semibold transition-colors', d.frame === f.value ? 'border-brand bg-soft text-charcoal' : 'border-line bg-white text-muted hover:text-ink']"
          @click="d.frame = f.value"
        >{{ f.label }}</button>
      </div>
      <div v-if="d.frame !== 'none'" class="mt-3 grid gap-3 sm:grid-cols-[1fr_auto]">
        <UiField label="Label" id="d-frame-label">
          <input id="d-frame-label" v-model="d.frameLabel" type="text" maxlength="28" class="field-input" placeholder="Scan me">
        </UiField>
        <UiField label="Frame colour" id="d-frame-color">
          <input id="d-frame-color" v-model="d.frameColor" type="color" class="h-10 w-14 cursor-pointer rounded-lg border border-line-strong bg-white p-1">
        </UiField>
      </div>
    </section>

    <section>
      <h3 class="mb-1 text-sm font-bold">Error correction</h3>
      <p class="mb-3 text-xs text-muted">Higher levels survive damage and logos, but make the code denser.</p>
      <div class="grid grid-cols-4 gap-2">
        <button
          v-for="l in levels" :key="l.value" type="button" :aria-pressed="d.errorCorrection === l.value"
          :class="['rounded-xl border px-2 py-2 text-xs font-semibold transition-colors', d.errorCorrection === l.value ? 'border-brand bg-soft text-charcoal' : 'border-line bg-white text-muted hover:text-ink']"
          @click="d.errorCorrection = l.value"
        >{{ l.label }} <span class="font-normal opacity-70">{{ l.hint }}</span></button>
      </div>
      <div class="mt-4">
        <label for="d-margin" class="text-xs font-semibold text-muted">Quiet zone {{ d.margin }}px</label>
        <input id="d-margin" v-model.number="d.margin" type="range" min="0" max="40" step="2" class="w-full accent-[var(--color-brand)]">
      </div>
    </section>

    <button type="button" class="inline-flex items-center gap-1.5 text-xs font-semibold text-muted hover:text-ink" @click="reset"><RotateCcw class="size-3.5" /> Reset design</button>
  </div>
</template>
