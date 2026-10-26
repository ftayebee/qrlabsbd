import type { QrDesign } from '~/types/qr'

function channel(hex: string, offset: number): number {
  const v = parseInt(hex.slice(offset, offset + 2), 16) / 255
  return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4
}

export function expandHex(hex: string): string {
  const h = hex.replace('#', '').trim()
  if (h.length === 3) return h.split('').map(c => c + c).join('')
  return h.slice(0, 6).padEnd(6, '0')
}

export function luminance(hex: string): number {
  const h = expandHex(hex)
  return 0.2126 * channel(h, 0) + 0.7152 * channel(h, 2) + 0.0722 * channel(h, 4)
}

export function contrastRatio(a: string, b: string): number {
  const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x) as [number, number]
  return (hi + 0.05) / (lo + 0.05)
}

export interface ScanWarning { level: 'warning' | 'danger'; message: string }

/** Heuristics for designs that commonly fail on phone cameras. */
export function scanWarnings(design: QrDesign, payloadLength: number): ScanWarning[] {
  const warnings: ScanWarning[] = []
  const bg = design.transparent ? '#FFFFFF' : design.background
  const ratio = contrastRatio(design.foreground, bg)

  if (ratio < 3) warnings.push({ level: 'danger', message: `Contrast is ${ratio.toFixed(1)}:1. Aim for at least 4:1 between code and background.` })
  else if (ratio < 4) warnings.push({ level: 'warning', message: `Contrast is ${ratio.toFixed(1)}:1. Darker modules on a light background scan most reliably.` })

  if (luminance(design.foreground) > luminance(bg)) warnings.push({ level: 'warning', message: 'Light modules on a dark background (inverted) are not supported by some older scanners.' })

  // Finder patterns only need to binarise as dark; mid-tone brand colours (≈2.9:1) still read reliably.
  if (design.cornerColor && contrastRatio(design.cornerColor, bg) < 2.5) warnings.push({ level: 'warning', message: 'Corner markers have low contrast. Scanners rely on them to find the code.' })

  if (design.logoUrl && (design.errorCorrection === 'L' || design.errorCorrection === 'M')) warnings.push({ level: 'warning', message: 'Logos hide part of the code. Use error correction Q or H when adding a logo.' })

  if (design.logoUrl && design.logoSize > 0.3) warnings.push({ level: 'danger', message: 'The logo covers too much of the code. Keep it under 30% of the width.' })

  if (design.margin < 8) warnings.push({ level: 'warning', message: 'Small quiet zone. Leave clear space around the code so it can be detected.' })

  if (design.transparent) warnings.push({ level: 'warning', message: 'Transparent backgrounds only scan well when printed on a plain, light surface.' })

  if (payloadLength > 900) warnings.push({ level: 'warning', message: 'This content creates a very dense code. Shorten it or use a dynamic code.' })

  return warnings
}
