/**
 * Minimal, safe Markdown renderer for admin-managed pages. All input is
 * HTML-escaped first, so stored content can never inject markup or script;
 * only headings, paragraphs, lists, bold, italics and http(s)/mailto links
 * are produced.
 */
function escapeHtml(s: string): string {
  return s.replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', '\'': '&#39;' }[c]!))
}

function inline(s: string): string {
  return escapeHtml(s)
    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
    .replace(/(^|[^*])\*(?!\s)(.+?)\*/g, '$1<em>$2</em>')
    .replace(/\[([^\]]+)\]\(((?:https?:\/\/|mailto:|\/)[^\s)]+)\)/g, (_m, text, href) => {
      const external = /^https?:/.test(href)
      return `<a href="${href}"${external ? ' rel="noopener noreferrer" target="_blank"' : ''}>${text}</a>`
    })
}

export function renderMarkdown(md: string): string {
  const out: string[] = []
  let list: string[] = []
  const flush = () => { if (list.length) { out.push(`<ul>${list.join('')}</ul>`); list = [] } }

  for (const block of md.replace(/\r\n/g, '\n').split(/\n{2,}/)) {
    const lines = block.split('\n')
    if (lines.every(l => /^\s*[-*]\s+/.test(l))) {
      list.push(...lines.map(l => `<li>${inline(l.replace(/^\s*[-*]\s+/, ''))}</li>`))
      continue
    }
    flush()
    const h = block.match(/^(#{2,3})\s+(.+)$/)
    if (h) out.push(`<h${h[1]!.length}>${inline(h[2]!)}</h${h[1]!.length}>`)
    else if (block.trim()) out.push(`<p>${lines.map(inline).join('<br>')}</p>`)
  }
  flush()
  return out.join('\n')
}
