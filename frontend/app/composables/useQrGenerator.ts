import type { QrCodeRecord, QrContentMap, QrDesign, QrMode, QrType } from '~/types/qr'
import { DEFAULT_DESIGN, QR_TYPES } from '~/types/qr'
import { QR_CATALOG, defaultContent } from '~/utils/qr/catalog'
import { encodeStatic } from '~/utils/qr/payload'
import { validateContent } from '~/utils/qr/validation'
import { scanWarnings } from '~/utils/qr/contrast'

const DRAFT_KEY = 'qrio:draft'

type Contents = { [K in QrType]: QrContentMap[K] }

/**
 * All generator state in one place: per-type content (kept when switching
 * types), design, mode, validation and the exact value that will be encoded.
 */
export function useQrGenerator(initial?: { type?: QrType; record?: QrCodeRecord }) {
  const config = useRuntimeConfig()
  const record = initial?.record

  const type = ref<QrType>(record?.type ?? initial?.type ?? 'url')
  const contents = reactive(Object.fromEntries(QR_TYPES.map(t => [t, defaultContent(t)])) as Contents)
  if (record) Object.assign(contents[record.type], record.content)

  const mode = ref<QrMode>(record?.mode ?? QR_CATALOG[type.value].modes[0]!)
  const design = reactive<QrDesign>({ ...DEFAULT_DESIGN, ...(record?.design ?? {}) })
  const name = ref(record?.name ?? '')
  const touched = ref(false)
  const serverErrors = ref<Record<string, string>>({})

  const meta = computed(() => QR_CATALOG[type.value])
  const content = computed(() => contents[type.value])

  // Keep mode valid for the selected type.
  watch(type, (t) => {
    if (!QR_CATALOG[t].modes.includes(mode.value)) mode.value = QR_CATALOG[t].modes[0]!
    serverErrors.value = {}
    touched.value = false
  })

  const errors = computed(() => ({ ...validateContent(type.value, content.value), ...serverErrors.value }))
  const isValid = computed(() => Object.keys(validateContent(type.value, content.value)).length === 0)

  const shortBase = `${String(config.public.shortDomain).replace(/\/$/, '')}/r/`
  const encodedValue = computed(() => {
    if (mode.value === 'dynamic') return record?.shortUrl ?? `${shortBase}xxxxxxx`
    return encodeStatic(type.value, content.value as never) ?? ''
  })

  /** What the preview renders: real payload when valid, a neutral sample otherwise. */
  const previewValue = computed(() => (mode.value === 'dynamic' || isValid.value ? encodedValue.value : `${shortBase}preview`))
  const warnings = computed(() => scanWarnings(design, previewValue.value.length))

  function payload() {
    return {
      name: name.value.trim() || `${meta.value.label} code`,
      type: type.value,
      mode: mode.value,
      content: toRaw(content.value),
      // logoUrl is derived server-side from logoFileId.
      design: { ...toRaw(design), logoUrl: undefined },
    }
  }

  /** Stores a guest's work so it survives sign-up. Wi-Fi passwords are never written to storage. */
  function saveDraft() {
    if (import.meta.server) return
    const c = { ...toRaw(content.value) } as Record<string, unknown>
    delete c.password
    try { localStorage.setItem(DRAFT_KEY, JSON.stringify({ type: type.value, mode: mode.value, name: name.value, content: c, design: { ...toRaw(design), logoUrl: null } })) } catch { /* storage unavailable */ }
  }

  function restoreDraft(): boolean {
    if (import.meta.server) return false
    try {
      const raw = localStorage.getItem(DRAFT_KEY)
      if (!raw) return false
      const d = JSON.parse(raw)
      if (!QR_TYPES.includes(d.type)) return false
      type.value = d.type
      Object.assign(contents[d.type as QrType], d.content)
      mode.value = d.mode
      name.value = d.name ?? ''
      Object.assign(design, d.design)
      localStorage.removeItem(DRAFT_KEY)
      return true
    } catch { return false }
  }

  return { type, mode, contents, content, design, name, meta, errors, isValid, touched, serverErrors, encodedValue, previewValue, warnings, payload, saveDraft, restoreDraft }
}

export type QrGenerator = ReturnType<typeof useQrGenerator>
