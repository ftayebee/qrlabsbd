<script setup lang="ts">
import type { QrType } from '~/types/qr'
import { QR_TYPE_LIST } from '~/utils/qr/catalog'

const model = defineModel<QrType>({ required: true })
withDefaults(defineProps<{ layout?: 'grid' | 'rail' }>(), { layout: 'grid' })
</script>

<template>
  <div
    role="radiogroup" aria-label="QR code type"
    :class="layout === 'rail' ? 'flex gap-2 overflow-x-auto pb-1 [scrollbar-width:none] lg:grid lg:grid-cols-1 lg:overflow-visible' : 'grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-6'"
  >
    <button
      v-for="t in QR_TYPE_LIST" :key="t.type" type="button" role="radio" :aria-checked="model === t.type"
      :class="[
        'group relative flex shrink-0 items-center rounded-xl border text-left transition-[border-color,background-color,transform] duration-200 active:scale-[0.98]',
        layout === 'rail' ? 'gap-2.5 px-3 py-2.5 lg:w-full' : 'flex-col justify-center gap-2 px-2 py-3 text-center',
        model === t.type ? 'border-brand bg-soft' : 'border-line bg-white hover:border-line-strong hover:bg-canvas',
      ]"
      @click="model = t.type"
    >
      <span :class="['grid size-8 shrink-0 place-items-center rounded-lg transition-colors duration-200', model === t.type ? 'bg-brand text-white' : 'bg-canvas text-ink group-hover:bg-white']">
        <QrTypeIcon :type="t.type" class="size-4" />
      </span>
      <span :class="['text-xs font-semibold whitespace-nowrap', model === t.type ? 'text-charcoal' : 'text-ink']">{{ t.label }}</span>
    </button>
  </div>
</template>
