/**
 * Single source of truth for brand identity. Rename the product or change the
 * palette here; components read from this file and from the CSS tokens in
 * assets/css/main.css (keep the two colour sets in sync).
 */
export const brand = {
  name: 'Qrio',
  tagline: 'Create, customise and track QR codes that make every scan count.',
  supportEmail: 'support@qrio.app',
  colors: {
    primary: '#1DB45A',
    charcoal: '#14251A',
    background: '#F7F8F2',
    soft: '#E7F5E9',
  },
  social: {
    x: 'https://x.com/',
    linkedin: 'https://www.linkedin.com/',
    github: 'https://github.com/',
  },
} as const

export type Brand = typeof brand
