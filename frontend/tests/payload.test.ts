import { describe, expect, it } from 'vitest'
import { QR_TYPES } from '~/types/qr'
import { QR_CATALOG, defaultContent } from '~/utils/qr/catalog'
import { contrastRatio, scanWarnings } from '~/utils/qr/contrast'
import { renderMarkdown } from '~/utils/markdown'
import { DEFAULT_DESIGN } from '~/types/qr'
import {
  encodeApp, encodeEmail, encodePhone, encodeSms, encodeStatic, encodeText, encodeUrl, encodeVCard, encodeWifi,
  escapeWifi, isValidHttpUrl, normalizeUrl,
} from '~/utils/qr/payload'
import { validateContent } from '~/utils/qr/validation'

describe('static payload encoders', () => {
  it('normalises and validates URLs', () => {
    expect(normalizeUrl('example.com/a')).toBe('https://example.com/a')
    expect(encodeUrl({ url: ' https://example.com ' })).toBe('https://example.com')
    expect(isValidHttpUrl('javascript:alert(1)')).toBe(false)
    expect(isValidHttpUrl('ftp://example.com')).toBe(false)
    expect(isValidHttpUrl('https://example')).toBe(false)
    expect(isValidHttpUrl('https://exa mple.com')).toBe(false)
    expect(isValidHttpUrl('example.com')).toBe(true)
  })

  it('escapes Wi-Fi reserved characters and omits passwords for open networks', () => {
    expect(escapeWifi('a;b,c:d"e\\f')).toBe('a\\;b\\,c\\:d\\"e\\\\f')
    expect(encodeWifi({ ssid: 'Café;Guest', security: 'WPA', password: 'p:a,s"s\\1' })).toBe('WIFI:T:WPA;S:Café\\;Guest;P:p\\:a\\,s\\"s\\\\1;;')
    expect(encodeWifi({ ssid: 'Lobby', security: 'nopass', password: 'ignored', hidden: true })).toBe('WIFI:T:nopass;S:Lobby;H:true;;')
  })

  it('percent-encodes mailto subject and body', () => {
    expect(encodeEmail({ to: 'hello@example.com', subject: 'Order #42 & more', body: 'Line 1\nLine 2' }))
      .toBe('mailto:hello%40example.com?subject=Order%20%2342%20%26%20more&body=Line%201%0ALine%202')
  })

  it('builds SMS and tel payloads', () => {
    expect(encodeSms({ phone: '+44 (20) 7946-00', message: 'Hi there' })).toBe('SMSTO:+4420794600:Hi there')
    expect(encodeSms({ phone: '5551234' })).toBe('SMSTO:5551234')
    expect(encodePhone({ phone: '+1 555 123 4567' })).toBe('tel:+15551234567')
  })

  it('builds RFC-compliant vCards', () => {
    const v = encodeVCard({ firstName: 'Ada', lastName: 'Lovelace', company: 'Analytical, Engines; Ltd', email: 'ada@example.com', note: 'Line 1\nLine 2' })
    expect(v.startsWith('BEGIN:VCARD\r\nVERSION:3.0\r\nN:Lovelace;Ada;;;\r\nFN:Ada Lovelace\r\n')).toBe(true)
    expect(v).toContain('ORG:Analytical\\, Engines\\; Ltd')
    expect(v).toContain('NOTE:Line 1\\nLine 2')
    expect(v.endsWith('END:VCARD')).toBe(true)
  })

  it('prefers the fallback URL for static app codes', () => {
    expect(encodeApp({ name: 'X', iosUrl: 'https://apps.apple.com/app/id1', fallbackUrl: '' })).toBe('https://apps.apple.com/app/id1')
    expect(encodeApp({ name: 'X', iosUrl: 'https://apps.apple.com/app/id1', fallbackUrl: 'https://x.app' })).toBe('https://x.app')
  })

  it('keeps plain text unchanged', () => {
    expect(encodeText({ text: 'Hello\nworld ✓' })).toBe('Hello\nworld ✓')
  })

  it('has no static payload for hosted types', () => {
    for (const t of ['pdf', 'multi_url', 'social', 'business_card'] as const) {
      expect(encodeStatic(t, defaultContent(t) as never)).toBeNull()
    }
  })
})

describe('catalog and validation', () => {
  it('defines all 12 types with at least one mode', () => {
    expect(QR_TYPES).toHaveLength(12)
    for (const t of QR_TYPES) expect(QR_CATALOG[t].modes.length).toBeGreaterThan(0)
  })

  it('rejects empty default content for every type', () => {
    for (const t of QR_TYPES) expect(Object.keys(validateContent(t, defaultContent(t) as never)).length).toBeGreaterThan(0)
  })

  it('accepts valid content', () => {
    expect(validateContent('url', { url: 'https://example.com' })).toEqual({})
    expect(validateContent('wifi', { ssid: 'Home', security: 'WPA', password: '12345678' })).toEqual({})
    expect(validateContent('wifi', { ssid: 'Home', security: 'WPA', password: 'short' })).toHaveProperty('password')
    expect(validateContent('multi_url', { title: 'Links', links: [{ label: 'Shop', url: 'https://shop.example.com' }] })).toEqual({})
    expect(validateContent('multi_url', { title: 'Links', links: [{ label: 'Bad', url: 'javascript:void(0)' }] })).toHaveProperty('links.0.url')
  })
})

describe('scan reliability heuristics', () => {
  it('computes WCAG contrast', () => {
    expect(contrastRatio('#000000', '#FFFFFF')).toBeCloseTo(21, 0)
    expect(contrastRatio('#FFFFFF', '#FFFFFF')).toBeCloseTo(1, 5)
  })

  it('warns about low contrast and logos with low error correction', () => {
    const warnings = scanWarnings({ ...DEFAULT_DESIGN, foreground: '#DDDDDD', logoUrl: 'data:x', errorCorrection: 'L' }, 40)
    expect(warnings.some(w => w.level === 'danger')).toBe(true)
    expect(warnings.some(w => /error correction/i.test(w.message))).toBe(true)
    expect(scanWarnings(DEFAULT_DESIGN, 40)).toEqual([])
  })
})

describe('markdown renderer', () => {
  it('escapes HTML and blocks unsafe links', () => {
    const html = renderMarkdown('## Title\n\n<script>alert(1)</script> **bold** [x](javascript:alert(1)) [ok](https://example.com)')
    expect(html).not.toContain('<script>')
    expect(html).toContain('&lt;script&gt;')
    expect(html).not.toContain('href="javascript')
    expect(html).toContain('<a href="https://example.com" rel="noopener noreferrer" target="_blank">ok</a>')
    expect(html).toContain('<h2>Title</h2>')
  })
})
