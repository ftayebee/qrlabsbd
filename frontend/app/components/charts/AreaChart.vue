<script setup lang="ts">
import { formatDate, formatNumber } from '~/utils/format'

/**
 * Lightweight SVG time-series chart. Draws in on mount and morphs between
 * datasets; keyboard and pointer users get a crosshair tooltip.
 */
const props = withDefaults(defineProps<{
  points: { date: string; value: number; secondary?: number }[]
  height?: number
  label?: string
  secondaryLabel?: string
}>(), { height: 220, label: 'Scans', secondaryLabel: 'Unique' })

const W = 640
const pad = { t: 12, r: 8, b: 26, l: 36 }
const H = computed(() => props.height)

const max = computed(() => {
  const m = Math.max(1, ...props.points.map(p => Math.max(p.value, p.secondary ?? 0)))
  const mag = 10 ** Math.floor(Math.log10(m))
  return Math.ceil(m / mag) * mag
})

const x = (i: number) => pad.l + (props.points.length <= 1 ? 0 : (i / (props.points.length - 1)) * (W - pad.l - pad.r))
const y = (v: number) => pad.t + (1 - v / max.value) * (H.value - pad.t - pad.b)

function path(key: 'value' | 'secondary') {
  return props.points.map((p, i) => `${i ? 'L' : 'M'}${x(i).toFixed(1)},${y((p[key] ?? 0) as number).toFixed(1)}`).join(' ')
}
const line = computed(() => path('value'))
const line2 = computed(() => (props.points.some(p => p.secondary !== undefined) ? path('secondary') : ''))
const area = computed(() => props.points.length ? `${line.value} L${x(props.points.length - 1)},${H.value - pad.b} L${x(0)},${H.value - pad.b} Z` : '')
const ticks = computed(() => [0, 0.5, 1].map(f => ({ v: Math.round(max.value * f), y: y(max.value * f) })))
const labels = computed(() => {
  const n = props.points.length
  if (!n) return []
  const idxs = n <= 7 ? [...Array(n).keys()] : [0, Math.floor(n / 3), Math.floor((2 * n) / 3), n - 1]
  return idxs.map(i => ({ x: x(i), text: formatDate(props.points[i]!.date, { month: 'short', day: 'numeric' }) }))
})

const hover = ref<number | null>(null)
const svg = ref<SVGSVGElement | null>(null)
function onMove(e: PointerEvent) {
  if (!svg.value || !props.points.length) return
  const rect = svg.value.getBoundingClientRect()
  const px = ((e.clientX - rect.left) / rect.width) * W
  const ratio = (px - pad.l) / (W - pad.l - pad.r)
  hover.value = Math.max(0, Math.min(props.points.length - 1, Math.round(ratio * (props.points.length - 1))))
}
function onKey(e: KeyboardEvent) {
  if (!props.points.length) return
  if (e.key === 'ArrowRight') hover.value = Math.min(props.points.length - 1, (hover.value ?? -1) + 1)
  if (e.key === 'ArrowLeft') hover.value = Math.max(0, (hover.value ?? props.points.length) - 1)
}
const drawn = ref(false)
onMounted(() => requestAnimationFrame(() => (drawn.value = true)))
const gid = useId()
</script>

<template>
  <div class="relative">
    <svg
      ref="svg" :viewBox="`0 0 ${W} ${H}`" class="w-full touch-none select-none outline-none" :style="{ height: `${height}px` }" preserveAspectRatio="none"
      role="img" :aria-label="`${label} over time`" tabindex="0"
      @pointermove="onMove" @pointerleave="hover = null" @keydown="onKey" @blur="hover = null"
    >
      <defs>
        <linearGradient :id="`g-${gid}`" x1="0" x2="0" y1="0" y2="1">
          <stop offset="0%" stop-color="var(--color-brand)" stop-opacity="0.22" />
          <stop offset="100%" stop-color="var(--color-brand)" stop-opacity="0" />
        </linearGradient>
      </defs>
      <g v-for="t in ticks" :key="t.v">
        <line :x1="pad.l" :x2="W - pad.r" :y1="t.y" :y2="t.y" stroke="var(--color-line)" stroke-dasharray="3 4" vector-effect="non-scaling-stroke" />
        <text :x="pad.l - 8" :y="t.y + 4" text-anchor="end" class="fill-muted text-[10px]">{{ t.v >= 1000 ? `${(t.v / 1000).toFixed(t.v % 1000 ? 1 : 0)}k` : t.v }}</text>
      </g>
      <text v-for="l in labels" :key="l.x" :x="l.x" :y="H - 6" text-anchor="middle" class="fill-muted text-[10px]">{{ l.text }}</text>
      <path :d="area" :fill="`url(#g-${gid})`" :class="['transition-opacity duration-700', drawn ? 'opacity-100' : 'opacity-0']" />
      <path v-if="line2" :d="line2" fill="none" stroke="var(--color-charcoal)" stroke-opacity="0.35" stroke-width="1.5" stroke-dasharray="4 4" vector-effect="non-scaling-stroke" class="transition-[d] duration-500" />
      <path :d="line" fill="none" stroke="var(--color-brand)" stroke-width="2.25" vector-effect="non-scaling-stroke" stroke-linejoin="round" pathLength="1" :stroke-dasharray="1" :stroke-dashoffset="drawn ? 0 : 1" class="transition-[stroke-dashoffset,d] duration-[900ms] ease-[var(--ease-out-quart)]" />
      <g v-if="hover !== null && points[hover]">
        <line :x1="x(hover)" :x2="x(hover)" :y1="pad.t" :y2="H - pad.b" stroke="var(--color-ink)" stroke-opacity="0.2" vector-effect="non-scaling-stroke" />
        <circle :cx="x(hover)" :cy="y(points[hover]!.value)" r="4" fill="white" stroke="var(--color-brand)" stroke-width="2" vector-effect="non-scaling-stroke" />
      </g>
    </svg>
    <div
      v-if="hover !== null && points[hover]"
      class="pointer-events-none absolute top-0 z-10 -translate-x-1/2 rounded-xl border border-line bg-white px-3 py-2 text-xs shadow-[var(--shadow-card)]"
      :style="{ left: `${(x(hover) / W) * 100}%` }"
    >
      <p class="font-semibold text-ink">{{ formatDate(points[hover]!.date) }}</p>
      <p class="text-muted"><span class="font-semibold text-brand-700">{{ formatNumber(points[hover]!.value) }}</span> {{ label.toLowerCase() }}</p>
      <p v-if="points[hover]!.secondary !== undefined" class="text-muted">{{ formatNumber(points[hover]!.secondary!) }} {{ secondaryLabel.toLowerCase() }}</p>
    </div>
  </div>
</template>
