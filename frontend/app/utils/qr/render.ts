import type { QrDesign } from '~/types/qr'

/**
 * Client-side QR rendering built on qr-code-styling. Produces a self-contained
 * SVG (optionally wrapped in a frame) that is reused for preview, SVG, PNG and
 * PDF exports so every format looks identical.
 */

const BASE = 320

function escapeXml(s: string): string {
  return s.replace(/[<>&'"]/g, c => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;', '\'': '&apos;', '"': '&quot;' }[c] as string))
}

async function rawQrSvg(data: string, design: QrDesign, size: number): Promise<string> {
  const { default: QRCodeStyling } = await import('qr-code-styling')
  const scale = size / BASE
  const background = design.transparent ? 'rgba(0,0,0,0)' : design.background
  const qr = new QRCodeStyling({
    width: size,
    height: size,
    type: 'svg',
    data,
    margin: Math.round(design.margin * scale),
    qrOptions: { errorCorrectionLevel: design.errorCorrection },
    dotsOptions: { color: design.foreground, type: design.dotStyle },
    backgroundOptions: { color: background },
    cornersSquareOptions: { type: design.cornerSquareStyle, color: design.cornerColor || design.foreground },
    cornersDotOptions: { type: design.cornerDotStyle, color: design.cornerColor || design.foreground },
    ...(design.logoUrl
      ? { image: design.logoUrl, imageOptions: { imageSize: design.logoSize * 1.6, margin: Math.round(4 * scale), hideBackgroundDots: true, crossOrigin: 'anonymous', saveAsBlob: true } }
      : {}),
  })
  const blob = await qr.getRawData('svg')
  if (!blob) throw new Error('QR generation failed')
  const text = typeof (blob as Blob).text === 'function' ? await (blob as Blob).text() : String(blob)
  return text.replace(/^<\?xml[^>]*>\s*/, '')
}

function nest(svg: string, x: number, y: number): string {
  return svg.replace('<svg ', `<svg x="${x}" y="${y}" `)
}

export interface RenderResult { svg: string; width: number; height: number }

export async function renderQrSvg(data: string, design: QrDesign, size = BASE): Promise<RenderResult> {
  const inner = await rawQrSvg(data, design, size)
  if (design.frame === 'none') return { svg: inner, width: size, height: size }

  const s = size / BASE
  const pad = Math.round(16 * s)
  const labelH = Math.round(56 * s)
  const radius = Math.round(22 * s)
  const font = Math.round(20 * s)
  const label = escapeXml(design.frameLabel.slice(0, 28) || 'Scan me')
  const width = size + pad * 2
  const fontFamily = 'Manrope, Inter, Arial, sans-serif'

  if (design.frame === 'badge') {
    const height = size + pad * 2 + labelH + pad
    const pillW = Math.min(width - pad * 2, Math.round(label.length * font * 0.62 + 48 * s))
    return {
      width, height,
      svg: `<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}">`
        + `<rect x="1" y="1" width="${width - 2}" height="${height - 2}" rx="${radius}" fill="#FFFFFF" stroke="${design.frameColor}" stroke-width="${Math.max(2, 2 * s)}"/>`
        + nest(inner, pad, pad)
        + `<rect x="${(width - pillW) / 2}" y="${size + pad * 1.5}" width="${pillW}" height="${labelH - pad / 2}" rx="${(labelH - pad / 2) / 2}" fill="${design.frameColor}"/>`
        + `<text x="${width / 2}" y="${size + pad * 1.5 + (labelH - pad / 2) / 2}" fill="#FFFFFF" font-family="${fontFamily}" font-size="${font}" font-weight="700" text-anchor="middle" dominant-baseline="central">${label}</text>`
        + `</svg>`,
    }
  }

  const height = size + pad * 2 + labelH
  const top = design.frame === 'label-top'
  const qrY = top ? labelH + pad : pad
  const textY = top ? labelH / 2 + pad / 2 : size + pad * 2 + labelH / 2 - pad / 2
  return {
    width, height,
    svg: `<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}">`
      + `<rect width="${width}" height="${height}" rx="${radius}" fill="${design.frameColor}"/>`
      + `<rect x="${pad / 2}" y="${qrY - pad / 2}" width="${size + pad}" height="${size + pad}" rx="${radius * 0.7}" fill="${design.transparent ? '#FFFFFF' : design.background}"/>`
      + nest(inner, pad, qrY)
      + `<text x="${width / 2}" y="${textY}" fill="#FFFFFF" font-family="${fontFamily}" font-size="${font}" font-weight="700" text-anchor="middle" dominant-baseline="central">${label}</text>`
      + `</svg>`,
  }
}

export async function svgToPngBlob(result: RenderResult, scale = 1): Promise<Blob> {
  const url = URL.createObjectURL(new Blob([result.svg], { type: 'image/svg+xml;charset=utf-8' }))
  try {
    const img = new Image()
    img.decoding = 'async'
    await new Promise<void>((resolve, reject) => {
      img.onload = () => resolve()
      img.onerror = () => reject(new Error('Could not rasterise QR code'))
      img.src = url
    })
    const canvas = document.createElement('canvas')
    canvas.width = Math.round(result.width * scale)
    canvas.height = Math.round(result.height * scale)
    const ctx = canvas.getContext('2d')
    if (!ctx) throw new Error('Canvas unavailable')
    ctx.drawImage(img, 0, 0, canvas.width, canvas.height)
    return await new Promise<Blob>((resolve, reject) => canvas.toBlob(b => (b ? resolve(b) : reject(new Error('PNG export failed'))), 'image/png'))
  } finally {
    URL.revokeObjectURL(url)
  }
}

function triggerDownload(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  a.remove()
  setTimeout(() => URL.revokeObjectURL(url), 1000)
}

export type ExportFormat = 'png' | 'svg' | 'pdf'

export function safeFilename(name: string): string {
  return (name || 'qr-code').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 60) || 'qr-code'
}

export async function exportQr(data: string, design: QrDesign, format: ExportFormat, name = 'qr-code', size = 1024) {
  const file = safeFilename(name)
  const result = await renderQrSvg(data, design, size)

  if (format === 'svg') {
    triggerDownload(new Blob([`<?xml version="1.0" encoding="UTF-8"?>\n${result.svg}`], { type: 'image/svg+xml' }), `${file}.svg`)
    return
  }

  const png = await svgToPngBlob(result)
  if (format === 'png') {
    triggerDownload(png, `${file}.png`)
    return
  }

  // PDF: centre the high-resolution PNG on an A6-ish page sized to the code.
  const { jsPDF } = await import('jspdf')
  const mm = 80
  const ratio = result.height / result.width
  const doc = new jsPDF({ unit: 'mm', format: [mm + 20, mm * ratio + 20] })
  const dataUrl = await new Promise<string>((resolve) => {
    const reader = new FileReader()
    reader.onload = () => resolve(reader.result as string)
    reader.readAsDataURL(png)
  })
  doc.addImage(dataUrl, 'PNG', 10, 10, mm, mm * ratio)
  doc.save(`${file}.pdf`)
}
