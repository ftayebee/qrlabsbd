<script setup lang="ts">
import { Frame, ImageIcon, Palette, ScanLine } from 'lucide-vue-next'
import type { QrDesign } from '~/types/qr'
import { DEFAULT_DESIGN } from '~/types/qr'

const presets: { name: string; design: Partial<QrDesign> }[] = [
  { name: 'Forest', design: { foreground: '#14251A', cornerColor: '#1DB45A', dotStyle: 'rounded', cornerSquareStyle: 'extra-rounded', frame: 'label-bottom', frameColor: '#14251A', frameLabel: 'Scan the menu' } },
  { name: 'Ink', design: { foreground: '#0F172A', cornerColor: '#0F172A', dotStyle: 'square', cornerSquareStyle: 'square', cornerDotStyle: 'square', frame: 'none' } },
  { name: 'Ocean', design: { foreground: '#1D4ED8', cornerColor: '#0EA5E9', background: '#F0F7FF', dotStyle: 'dots', cornerSquareStyle: 'dot', frame: 'badge', frameColor: '#1D4ED8', frameLabel: 'Join Wi-Fi' } },
  { name: 'Ember', design: { foreground: '#7C2D12', cornerColor: '#EA580C', background: '#FFF7ED', dotStyle: 'classy-rounded', cornerSquareStyle: 'extra-rounded', frame: 'label-top', frameColor: '#7C2D12', frameLabel: 'Follow us' } },
]
const active = ref(0)
const design = computed<QrDesign>(() => ({ ...DEFAULT_DESIGN, ...presets[active.value]!.design }))

const features = [
  { icon: Palette, title: 'Brand colours', body: 'Code, corner and background colours with live contrast checks.' },
  { icon: ScanLine, title: 'Patterns & corners', body: 'Six module styles and three corner shapes.' },
  { icon: ImageIcon, title: 'Logo placement', body: 'Centre your logo; error correction adjusts to keep it scannable.' },
  { icon: Frame, title: 'Frames & calls to action', body: 'Add “Scan me” style labels that tell people what to expect.' },
]
</script>

<template>
  <section class="section" aria-labelledby="customize-title">
    <div class="container-page grid items-center gap-14 lg:grid-cols-[1fr_1.1fr]">
      <AppReveal class="order-2 lg:order-1">
        <div class="card relative grid place-items-center overflow-hidden p-10">
          <div class="qr-grid-bg absolute inset-0 opacity-60" aria-hidden="true" />
          <div class="relative">
            <QrPreview value="https://example.com/brand" :design="design" :size="260" />
          </div>
          <div class="relative mt-8 flex flex-wrap justify-center gap-2" role="radiogroup" aria-label="Design presets">
            <button
              v-for="(p, i) in presets" :key="p.name" type="button" role="radio" :aria-checked="active === i"
              :class="['rounded-full border px-4 py-1.5 text-xs font-semibold transition-colors', active === i ? 'border-brand bg-soft text-charcoal' : 'border-line bg-white text-muted hover:text-ink']"
              @click="active = i"
            >{{ p.name }}</button>
          </div>
        </div>
      </AppReveal>
      <div class="order-1 lg:order-2">
        <AppReveal><p class="eyebrow">Customisation</p></AppReveal>
        <AppReveal :delay="0.05"><h2 id="customize-title" class="section-title mt-4">On-brand codes that still scan every time</h2></AppReveal>
        <AppReveal :delay="0.1"><p class="section-lead">Style every detail while we watch for low contrast, oversized logos and missing quiet zones, the most common reasons printed codes fail.</p></AppReveal>
        <div class="mt-10 grid gap-6 sm:grid-cols-2">
          <AppReveal v-for="(f, i) in features" :key="f.title" :delay="0.12 + i * 0.05">
            <span class="grid size-10 place-items-center rounded-xl bg-soft text-brand-700"><component :is="f.icon" class="size-5" /></span>
            <h3 class="mt-3 font-bold">{{ f.title }}</h3>
            <p class="mt-1 text-sm text-muted">{{ f.body }}</p>
          </AppReveal>
        </div>
      </div>
    </div>
  </section>
</template>
