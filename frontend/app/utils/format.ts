/** Currencies without a minor unit (amounts are already whole units). */
const ZERO_DECIMAL = new Set(['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'])

/** Multiplier between major and minor units (100 for USD, 1 for JPY). */
export function minorFactor(currency: string): number {
  return ZERO_DECIMAL.has(currency.toUpperCase()) ? 1 : 100
}

export function formatMoney(minor: number, currency: string, opts: { compact?: boolean } = {}): string {
  const code = currency.toUpperCase()
  const value = ZERO_DECIMAL.has(code) ? minor : minor / 100
  const whole = Number.isInteger(value)
  return new Intl.NumberFormat('en', {
    style: 'currency',
    currency: code,
    minimumFractionDigits: whole && opts.compact ? 0 : undefined,
    maximumFractionDigits: whole && opts.compact ? 0 : undefined,
  }).format(value)
}

export function formatNumber(n: number): string {
  return new Intl.NumberFormat('en').format(n)
}

export function formatCompact(n: number): string {
  return new Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 }).format(n)
}

export function formatDate(iso: string | null | undefined, opts: Intl.DateTimeFormatOptions = { dateStyle: 'medium' }): string {
  if (!iso) return '—'
  return new Intl.DateTimeFormat('en', opts).format(new Date(iso))
}

export function formatDateTime(iso: string | null | undefined): string {
  return formatDate(iso, { dateStyle: 'medium', timeStyle: 'short' })
}

export function formatRelative(iso: string | null | undefined): string {
  if (!iso) return 'Never'
  const diff = (new Date(iso).getTime() - Date.now()) / 1000
  const rtf = new Intl.RelativeTimeFormat('en', { numeric: 'auto' })
  const units: [Intl.RelativeTimeFormatUnit, number][] = [['year', 31536000], ['month', 2592000], ['week', 604800], ['day', 86400], ['hour', 3600], ['minute', 60]]
  for (const [unit, secs] of units) {
    if (Math.abs(diff) >= secs) return rtf.format(Math.round(diff / secs), unit)
  }
  return 'just now'
}

export function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  const units = ['KB', 'MB', 'GB', 'TB']
  let v = bytes / 1024
  let i = 0
  while (v >= 1024 && i < units.length - 1) { v /= 1024; i++ }
  return `${v.toFixed(v < 10 ? 1 : 0)} ${units[i]}`
}

export function pct(part: number, total: number): number {
  return total > 0 ? Math.round((part / total) * 100) : 0
}

const COUNTRY_NAMES = typeof Intl !== 'undefined' && 'DisplayNames' in Intl ? new Intl.DisplayNames(['en'], { type: 'region' }) : null

export function countryName(code: string): string {
  if (!code || code === 'unknown') return 'Unknown'
  try { return COUNTRY_NAMES?.of(code) ?? code } catch { return code }
}

export function limitLabel(v: number | boolean | string | undefined): string {
  if (typeof v === 'boolean') return v ? 'Included' : '—'
  if (typeof v === 'number') return v < 0 ? 'Unlimited' : formatNumber(v)
  return v ?? '—'
}
