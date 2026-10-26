<script setup lang="ts">
import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-vue-next'

/** Editable, reorderable list of links used by Multiple URLs, Social and Business card types. */
const props = withDefaults(defineProps<{
  items: Record<string, string>[]
  fields: { key: string; label: string; placeholder?: string; type?: 'text' | 'url' | 'select'; options?: { value: string; label: string }[] }[]
  errors: Record<string, string>
  max?: number
  addLabel?: string
  template: () => Record<string, string>
}>(), { max: 20, addLabel: 'Add link' })

function move(i: number, dir: -1 | 1) {
  const j = i + dir
  if (j < 0 || j >= props.items.length) return
  const [item] = props.items.splice(i, 1)
  props.items.splice(j, 0, item!)
}
</script>

<template>
  <div class="space-y-3">
    <TransitionGroup tag="div" class="space-y-3" enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 -translate-y-1">
      <div v-for="(item, i) in items" :key="i" class="rounded-2xl border border-line bg-canvas/50 p-3">
        <div class="grid gap-3 sm:grid-cols-[1fr_1.4fr]">
          <UiField v-for="f in fields" :key="f.key" :label="f.label" :id="`link-${i}-${f.key}`" :error="errors[`links.${i}.${f.key}`]">
            <select v-if="f.type === 'select'" :id="`link-${i}-${f.key}`" v-model="item[f.key]" class="field-input">
              <option v-for="o in f.options" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
            <input v-else :id="`link-${i}-${f.key}`" v-model="item[f.key]" :type="f.type === 'url' ? 'url' : 'text'" :inputmode="f.type === 'url' ? 'url' : undefined" class="field-input" :placeholder="f.placeholder" maxlength="2048">
          </UiField>
        </div>
        <div class="mt-2 flex justify-end gap-1">
          <button type="button" class="rounded-lg p-1.5 text-muted hover:bg-white hover:text-ink disabled:opacity-30" :disabled="i === 0" :aria-label="`Move link ${i + 1} up`" @click="move(i, -1)"><ArrowUp class="size-4" /></button>
          <button type="button" class="rounded-lg p-1.5 text-muted hover:bg-white hover:text-ink disabled:opacity-30" :disabled="i === items.length - 1" :aria-label="`Move link ${i + 1} down`" @click="move(i, 1)"><ArrowDown class="size-4" /></button>
          <button type="button" class="rounded-lg p-1.5 text-muted hover:bg-white hover:text-danger" :aria-label="`Remove link ${i + 1}`" @click="items.splice(i, 1)"><Trash2 class="size-4" /></button>
        </div>
      </div>
    </TransitionGroup>
    <p v-if="errors.links" class="field-error">{{ errors.links }}</p>
    <UiButton v-if="items.length < max" variant="soft" size="sm" @click="items.push(template())"><Plus class="size-4" /> {{ addLabel }}</UiButton>
  </div>
</template>
