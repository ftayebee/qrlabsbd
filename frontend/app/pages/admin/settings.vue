<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'admin' })
useSeoMeta({ title: 'Settings', robots: 'noindex' })

interface Setting { key: string; group: string; value: unknown; is_public: boolean }
const api = useApi()
const toast = useToast()
const { confirm } = useConfirm()
const { data, refresh } = await useAsyncData('admin-settings', async () => (await api.get<{ data: Setting[] }>('/admin/settings')).data, { server: false, default: () => [] })

// Edited as text; list-valued settings (e.g. blocked hosts) as one item per line.
const draft = ref<Record<string, string>>({})
watch(data, (rows) => {
  draft.value = Object.fromEntries(rows.map(r => [r.key, Array.isArray(r.value) ? r.value.join('\n') : String(r.value ?? '')]))
}, { immediate: true })
const groups = computed(() => [...new Set(data.value.map(r => r.group))])
const changed = computed(() => data.value.filter(r => (Array.isArray(r.value) ? r.value.join('\n') : String(r.value ?? '')) !== draft.value[r.key]))

async function save() {
  const reason = await confirm({ title: `Save ${changed.value.length} settings?`, message: 'Changes apply immediately and are recorded in the audit log.', confirmLabel: 'Save', requireReason: true })
  if (!reason) return
  try {
    await api.put('/admin/settings', {
      reason,
      settings: changed.value.map(r => ({ key: r.key, value: Array.isArray(r.value) ? draft.value[r.key]!.split('\n').map(s => s.trim()).filter(Boolean) : draft.value[r.key] })),
    })
    toast.success('Settings saved.')
    await refresh()
  } catch (e) { toast.error((e as Error).message) }
}
const label = (key: string) => key.split('.').slice(1).join(' ').replace(/_/g, ' ')
</script>

<template>
  <div class="max-w-3xl">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 class="text-2xl font-extrabold sm:text-3xl">Global settings</h1>
        <p class="mt-1 text-muted">Brand, SEO and safety settings. Secrets such as API keys live in environment variables, not here.</p>
      </div>
      <UiButton :disabled="!changed.length" @click="save">Save {{ changed.length || '' }} changes</UiButton>
    </div>
    <section v-for="g in groups" :key="g" class="card mt-6 p-6">
      <h2 class="font-bold capitalize">{{ g }}</h2>
      <div class="mt-4 space-y-4">
        <UiField v-for="s in data.filter(r => r.group === g)" :key="s.key" :label="label(s.key)" :id="`set-${s.key}`" :hint="`${s.key}${s.is_public ? ' · public' : ''}${Array.isArray(s.value) ? ' · one per line' : ''}`">
          <textarea v-if="Array.isArray(s.value) || String(s.value ?? '').length > 80" :id="`set-${s.key}`" v-model="draft[s.key]" rows="3" class="field-input" />
          <input v-else :id="`set-${s.key}`" v-model="draft[s.key]" class="field-input">
        </UiField>
      </div>
    </section>
  </div>
</template>
