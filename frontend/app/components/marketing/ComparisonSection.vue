<script setup lang="ts">
import { Check, Minus } from 'lucide-vue-next'

const rows: { feature: string; static: boolean | string; dynamic: boolean | string }[] = [
  { feature: 'Works without internet (Wi-Fi, text, vCard)', static: true, dynamic: false },
  { feature: 'No account required', static: true, dynamic: false },
  { feature: 'Change destination after printing', static: false, dynamic: true },
  { feature: 'Scan analytics', static: false, dynamic: true },
  { feature: 'Pause, archive or resume', static: false, dynamic: true },
  { feature: 'Hosted pages (PDF, links, social, business card)', static: false, dynamic: true },
  { feature: 'Short, low-density symbol', static: 'Depends on content', dynamic: true },
  { feature: 'Custom colours, frames and logos', static: true, dynamic: true },
  { feature: 'PNG, SVG and PDF downloads', static: true, dynamic: true },
]
</script>

<template>
  <section class="section bg-white" aria-labelledby="compare-title">
    <div class="container-page max-w-5xl">
      <AppReveal class="text-center">
        <p class="eyebrow">Compare</p>
        <h2 id="compare-title" class="section-title mt-4">Static or dynamic?</h2>
        <p class="section-lead mx-auto max-w-2xl">Static codes are perfect when content never changes. Choose dynamic when you want to edit, measure or host content.</p>
      </AppReveal>
      <AppReveal :delay="0.1" class="mt-12 overflow-x-auto">
        <table class="w-full min-w-[560px] border-separate border-spacing-0 text-sm">
          <caption class="sr-only">Static versus dynamic QR code features</caption>
          <thead>
            <tr>
              <th scope="col" class="border-b border-line py-4 pr-4 text-left font-semibold text-muted">Feature</th>
              <th scope="col" class="w-36 border-b border-line py-4 text-center font-bold">Static</th>
              <th scope="col" class="w-36 rounded-t-2xl border-b border-brand/20 bg-soft py-4 text-center font-bold text-brand-700">Dynamic</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(r, i) in rows" :key="r.feature">
              <th scope="row" class="border-b border-line py-4 pr-4 text-left font-medium text-ink">{{ r.feature }}</th>
              <td v-for="col in (['static', 'dynamic'] as const)" :key="col" :class="['border-b py-4 text-center', col === 'dynamic' ? 'border-brand/15 bg-soft/60' : 'border-line', col === 'dynamic' && i === rows.length - 1 ? 'rounded-b-2xl' : '']">
                <Check v-if="r[col] === true" class="mx-auto size-5 text-brand" aria-label="Yes" />
                <Minus v-else-if="r[col] === false" class="mx-auto size-5 text-line-strong" aria-label="No" />
                <span v-else class="text-xs text-muted">{{ r[col] }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </AppReveal>
    </div>
  </section>
</template>
