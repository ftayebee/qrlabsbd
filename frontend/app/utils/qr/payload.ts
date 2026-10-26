import type {
  AppContent, ContactContent, EmailContent, PhoneContent, QrContentMap, QrType,
  SmsContent, TextContent, UrlContent, WifiContent,
} from '~/types/qr'

/**
 * Pure payload encoders for static QR codes. Each returns the exact string the
 * QR symbol will contain. Dynamic and hosted types encode the short URL instead
 * (see encodedValueFor).
 */

const SAFE_SCHEMES = ['http:', 'https:']

export function normalizeUrl(input: string): string {
  const trimmed = input.trim()
  if (!trimmed) return ''
  return /^[a-z][a-z0-9+.-]*:/i.test(trimmed) ? trimmed : `https://${trimmed}`
}

export function isValidHttpUrl(input: string): boolean {
  try {
    const url = new URL(normalizeUrl(input))
    return SAFE_SCHEMES.includes(url.protocol) && url.hostname.includes('.') && !/\s/.test(input.trim())
  } catch {
    return false
  }
}

export function normalizePhone(input: string): string {
  const trimmed = input.trim()
  const plus = trimmed.startsWith('+') ? '+' : ''
  return plus + trimmed.replace(/[^\d]/g, '')
}

export function isValidPhone(input: string): boolean {
  const digits = normalizePhone(input).replace('+', '')
  return digits.length >= 5 && digits.length <= 15
}

export function isValidEmail(input: string): boolean {
  return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(input.trim())
}

/** Escapes the characters the Wi-Fi QR format reserves: \ ; , : " */
export function escapeWifi(value: string): string {
  return value.replace(/([\\;,:"])/g, '\\$1')
}

/** Escapes text values per vCard 3.0 (RFC 2426): backslash, comma, semicolon, newline. */
export function escapeVCard(value: string): string {
  return value.replace(/\\/g, '\\\\').replace(/,/g, '\\,').replace(/;/g, '\\;').replace(/\r?\n/g, '\\n')
}

export function encodeUrl(c: UrlContent): string {
  return normalizeUrl(c.url)
}

export function encodeText(c: TextContent): string {
  return c.text
}

export function encodePhone(c: PhoneContent): string {
  return `tel:${normalizePhone(c.phone)}`
}

export function encodeSms(c: SmsContent): string {
  const number = normalizePhone(c.phone)
  return c.message ? `SMSTO:${number}:${c.message}` : `SMSTO:${number}`
}

export function encodeEmail(c: EmailContent): string {
  const params: string[] = []
  if (c.subject) params.push(`subject=${encodeURIComponent(c.subject)}`)
  if (c.body) params.push(`body=${encodeURIComponent(c.body)}`)
  const to = c.to.trim().split(',').map(addr => encodeURIComponent(addr.trim())).join(',')
  return `mailto:${to}${params.length ? `?${params.join('&')}` : ''}`
}

export function encodeWifi(c: WifiContent): string {
  const security = c.security === 'nopass' ? 'nopass' : c.security
  const parts = [`T:${security}`, `S:${escapeWifi(c.ssid)}`]
  if (security !== 'nopass' && c.password) parts.push(`P:${escapeWifi(c.password)}`)
  if (c.hidden) parts.push('H:true')
  return `WIFI:${parts.join(';')};;`
}

export function encodeVCard(c: ContactContent): string {
  const e = (v?: string) => escapeVCard(v?.trim() ?? '')
  const lines = ['BEGIN:VCARD', 'VERSION:3.0']
  lines.push(`N:${e(c.lastName)};${e(c.firstName)};;;`)
  lines.push(`FN:${e([c.firstName, c.lastName].filter(Boolean).join(' '))}`)
  if (c.company) lines.push(`ORG:${e(c.company)}`)
  if (c.jobTitle) lines.push(`TITLE:${e(c.jobTitle)}`)
  if (c.phone) lines.push(`TEL;TYPE=WORK,VOICE:${normalizePhone(c.phone)}`)
  if (c.mobile) lines.push(`TEL;TYPE=CELL:${normalizePhone(c.mobile)}`)
  if (c.email) lines.push(`EMAIL;TYPE=INTERNET:${c.email.trim()}`)
  if (c.website) lines.push(`URL:${normalizeUrl(c.website)}`)
  if (c.street || c.city || c.region || c.postcode || c.country) {
    lines.push(`ADR;TYPE=WORK:;;${e(c.street)};${e(c.city)};${e(c.region)};${e(c.postcode)};${e(c.country)}`)
  }
  if (c.note) lines.push(`NOTE:${e(c.note)}`)
  lines.push('END:VCARD')
  return lines.join('\r\n')
}

/** A static App QR can only point at one store; prefer the fallback, then iOS, then Android. */
export function encodeApp(c: AppContent): string {
  return normalizeUrl(c.fallbackUrl || c.iosUrl || c.androidUrl || '')
}

type StaticEncoder<T extends QrType> = (content: QrContentMap[T]) => string

const STATIC_ENCODERS: { [K in QrType]?: StaticEncoder<K> } = {
  url: encodeUrl,
  text: encodeText,
  phone: encodePhone,
  sms: encodeSms,
  email: encodeEmail,
  wifi: encodeWifi,
  contact: encodeVCard,
  app: encodeApp,
}

export function encodeStatic<T extends QrType>(type: T, content: QrContentMap[T]): string | null {
  const encoder = STATIC_ENCODERS[type] as StaticEncoder<T> | undefined
  return encoder ? encoder(content) : null
}

/** QR codes larger than this are dense enough to scan poorly on phone cameras. */
export const RECOMMENDED_MAX_PAYLOAD = 900
