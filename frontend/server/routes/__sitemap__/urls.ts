import { QR_TYPE_LIST } from '../../../app/utils/qr/catalog'
import { USE_CASES } from '../../../app/utils/content/useCases'

// Dynamic public routes for the sitemap. Static pages are discovered automatically.
export default defineEventHandler(() => [
  ...QR_TYPE_LIST.map(t => ({ loc: `/qr-codes/${t.slug}`, changefreq: 'monthly', priority: 0.8 })),
  ...USE_CASES.map(u => ({ loc: `/use-cases/${u.slug}`, changefreq: 'monthly', priority: 0.6 })),
  ...['terms', 'privacy', 'cookies', 'acceptable-use'].map(s => ({ loc: `/legal/${s}`, changefreq: 'yearly', priority: 0.2 })),
])
