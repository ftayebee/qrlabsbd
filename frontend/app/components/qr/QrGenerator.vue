<script setup lang="ts">
import { AlertTriangle, Info, Save, Sparkles } from 'lucide-vue-next'
import type { Item, UsageSummary } from '~/types/api'
import type { QrCodeRecord, QrType } from '~/types/qr'
import { ApiError } from '~/composables/useApi'
import { QR_CATALOG } from '~/utils/qr/catalog'

const props = withDefaults(defineProps<{
  initialType?: QrType
  record?: QrCodeRecord
  /** Hide the type rail (used on per-type landing pages that already chose a type). */
  lockType?: boolean
  restoreDraft?: boolean
}>(), { lockType: false, restoreDraft: false })

const emit = defineEmits<{ saved: [QrCodeRecord] }>()

const gen = useQrGenerator({ type: props.initialType, record: props.record })
const { type, mode, design, name, meta, warnings, previewValue, isValid } = gen

const { user, fetchUser } = useAuth()
const api = useApi()
const toast = useToast()
const usage = ref<UsageSummary | null>(null)

onMounted(async () => {
  if (props.restoreDraft && gen.restoreDraft()) toast.info('We restored the code you started before signing in.')
  if (await fetchUser()) {
    usage.value = (await api.get<Item<UsageSummary>>('/account/usage').catch(() => null))?.data ?? null
  }
})

const editing = computed(() => !!props.record)
const readOnlyReason = computed(() => (props.record && !props.record.canEdit ? props.record.readOnlyReason ?? 'You can view this code but not edit it.' : null))
const canUploadLogo = computed(() => !!usage.value?.entitlements.custom_logo)
const vectorAllowed = computed(() => !user.value || usage.value === null || !!usage.value.entitlements.vector_downloads)
const needsAccount = computed(() => mode.value === 'dynamic' || meta.value.hosted)
const downloadable = computed(() => isValid.value && (mode.value === 'static' || !!props.record?.shortUrl))

const tab = ref<'content' | 'design'>('content')
const saving = ref(false)

async function save() {
  gen.touched.value = true
  if (!isValid.value) { tab.value = 'content'; toast.error('Check the highlighted fields.'); return }
  if (!user.value) {
    gen.saveDraft()
    await navigateTo({ path: '/register', query: { redirect: '/dashboard/qr-codes/new?draft=1' } })
    return
  }
  saving.value = true
  gen.serverErrors.value = {}
  try {
    const body = gen.payload()
    const res = editing.value
      ? await api.patch<Item<QrCodeRecord>>(`/qr-codes/${props.record!.id}`, { name: body.name, content: body.content, design: body.design })
      : await api.post<Item<QrCodeRecord>>('/qr-codes', body)
    toast.success(editing.value ? 'Changes saved. Printed codes update instantly.' : 'QR code saved.')
    emit('saved', res.data)
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) {
      gen.serverErrors.value = e.fieldErrors
      tab.value = 'content'
    }
    toast.error(e instanceof ApiError && e.status === 402 ? `${e.message} Upgrade on the pricing page.` : (e as Error).message)
  } finally {
    saving.value = false
  }
}

const modeTabs = computed(() => meta.value.modes.map(m => ({ value: m, label: m === 'static' ? 'Static' : 'Dynamic' })))
</script>

<template>
  <div :class="['grid gap-5', lockType || editing ? 'lg:grid-cols-[minmax(0,1fr)_360px]' : 'lg:grid-cols-[220px_minmax(0,1fr)_360px]']">
    <!-- 1. Type selector -->
    <aside v-if="!lockType && !editing" class="min-w-0 lg:sticky lg:top-24 lg:self-start">
      <p class="mb-2 hidden text-xs font-bold uppercase tracking-wider text-muted lg:block">QR type</p>
      <QrTypeSelector v-model="type" layout="rail" />
    </aside>

    <!-- 2. Content & customisation -->
    <section class="card min-w-0 p-5 sm:p-6">
      <div class="mb-5 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
          <AnimatePresence mode="wait">
            <Motion :key="type" as="div" :initial="{ opacity: 0, y: 6 }" :animate="{ opacity: 1, y: 0 }" :exit="{ opacity: 0, y: -6 }" :transition="{ duration: 0.18 }">
              <h2 class="flex items-center gap-2 text-lg font-bold">
                <span class="grid size-8 place-items-center rounded-lg bg-soft text-brand-700"><QrTypeIcon :type="type" class="size-4" /></span>
                {{ meta.label }}
              </h2>
              <p class="mt-1 text-sm text-muted">{{ meta.short }}</p>
            </Motion>
          </AnimatePresence>
        </div>
        <UiTabs v-if="modeTabs.length > 1 && !editing" v-model="mode" :tabs="modeTabs" size="sm" />
        <UiBadge v-else :tone="mode === 'dynamic' ? 'green' : 'neutral'">{{ mode === 'dynamic' ? (meta.hosted ? 'Hosted page' : 'Dynamic') : 'Static' }}</UiBadge>
      </div>

      <UiAlert v-if="readOnlyReason" tone="warning" class="mb-5">{{ readOnlyReason }}</UiAlert>
      <UiAlert v-else-if="mode === 'dynamic' && !editing" tone="info" class="mb-5">
        {{ meta.hosted ? 'This code opens a hosted page on our domain. Edit it any time without reprinting.' : 'The code stores a short link on our domain, so you can change the destination after printing and track scans.' }}
      </UiAlert>

      <div class="mb-5">
        <UiField label="Name (only visible to you)" id="qr-name">
          <input id="qr-name" v-model="name" type="text" maxlength="150" class="field-input" :placeholder="`${meta.label} code`">
        </UiField>
      </div>

      <UiTabs v-model="tab" :tabs="[{ value: 'content', label: 'Content' }, { value: 'design', label: 'Design' }]" class="mb-5" />

      <fieldset :disabled="!!readOnlyReason" class="min-w-0">
        <AnimatePresence mode="wait">
          <Motion v-if="tab === 'content'" key="content" as="div" :initial="{ opacity: 0 }" :animate="{ opacity: 1 }" :exit="{ opacity: 0 }" :transition="{ duration: 0.15 }">
            <QrContentForm :gen="gen" :show-errors="gen.touched.value || Object.keys(gen.serverErrors.value).length > 0" />
          </Motion>
          <Motion v-else key="design" as="div" :initial="{ opacity: 0 }" :animate="{ opacity: 1 }" :exit="{ opacity: 0 }" :transition="{ duration: 0.15 }">
            <QrDesignPanel :design="design" :can-upload-logo="canUploadLogo" />
          </Motion>
        </AnimatePresence>
      </fieldset>
    </section>

    <!-- 3. Preview & actions -->
    <aside class="min-w-0 lg:sticky lg:top-24 lg:self-start">
      <div class="card p-5">
        <div class="relative grid place-items-center rounded-2xl bg-[radial-gradient(circle_at_center,var(--color-soft),transparent_70%)] p-4" :class="design.transparent ? 'bg-[conic-gradient(#eee_25%,#fff_0_50%,#eee_0_75%,#fff_0)] bg-[length:16px_16px]' : ''">
          <QrPreview :value="previewValue" :design="design" :size="300" :dimmed="!isValid && gen.touched.value" />
          <span v-if="mode === 'dynamic' && !record" class="absolute bottom-2 rounded-full bg-white/90 px-2.5 py-1 text-[11px] font-semibold text-muted shadow-sm">Short link assigned on save</span>
        </div>

        <p v-if="record?.shortUrl" class="mt-4 truncate rounded-xl bg-canvas px-3 py-2 text-center font-mono text-xs text-muted" :title="record.shortUrl">{{ record.shortUrl }}</p>

        <TransitionGroup tag="ul" class="mt-4 space-y-2" enter-active-class="transition duration-200" enter-from-class="opacity-0 -translate-y-1">
          <li v-for="w in warnings" :key="w.message" :class="['flex gap-2 rounded-xl px-3 py-2 text-xs', w.level === 'danger' ? 'bg-orange-50 text-orange-900' : 'bg-amber-50 text-amber-900']">
            <AlertTriangle class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" /> {{ w.message }}
          </li>
        </TransitionGroup>

        <div class="mt-5 space-y-3">
          <template v-if="downloadable">
            <QrDownloadButtons :value="gen.encodedValue.value" :design="design" :name="name || meta.label" :vector-allowed="vectorAllowed" />
            <p v-if="!vectorAllowed" class="text-center text-xs text-muted">SVG and PDF downloads are on paid plans.</p>
          </template>
          <p v-else-if="needsAccount && !record" class="flex gap-2 rounded-xl bg-canvas px-3 py-2.5 text-xs text-muted">
            <Info class="mt-0.5 size-3.5 shrink-0" /> {{ QR_CATALOG[type].hosted ? 'Hosted codes are created in your account.' : 'Dynamic codes get their short link when you save.' }} Downloads unlock after saving.
          </p>

          <UiButton v-if="!readOnlyReason && (user || needsAccount || editing)" block :variant="downloadable && !editing ? 'dark' : 'primary'" :loading="saving" @click="save">
            <Save v-if="user" class="size-4" /><Sparkles v-else class="size-4" />
            {{ editing ? 'Save changes' : user ? 'Save to library' : 'Create free account to save' }}
          </UiButton>
          <p v-if="!user && mode === 'static' && !needsAccount" class="text-center text-xs text-muted">
            No account needed for static codes. <NuxtLink to="/register" class="font-semibold text-brand-700 hover:underline">Sign up</NuxtLink> to save and track.
          </p>
        </div>
      </div>
    </aside>
  </div>
</template>
