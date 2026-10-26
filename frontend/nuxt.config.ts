import tailwindcss from '@tailwindcss/vite'
import { brand } from './app/brand.config'

const siteUrl = process.env.NUXT_PUBLIC_SITE_URL || 'http://localhost:3000'

// Private routes are never indexed; the sitemap and robots modules both read this list.
const privateRoutes = ['/dashboard/**', '/admin/**', '/login', '/register', '/forgot-password', '/reset-password', '/verify-email', '/checkout/**', '/p/**']

export default defineNuxtConfig({
  compatibilityDate: '2026-10-01',
  devtools: { enabled: false },

  modules: ['@nuxt/fonts', '@nuxtjs/sitemap', '@nuxtjs/robots', 'nuxt-schema-org', 'motion-v/nuxt'],

  css: ['~/assets/css/main.css'],

  vite: {
    plugins: [tailwindcss()],
    optimizeDeps: { include: ['qr-code-styling', 'jspdf', 'zod', 'lucide-vue-next'] },
  },

  typescript: {
    strict: true,
    typeCheck: false,
  },

  app: {
    head: {
      htmlAttrs: { lang: 'en' },
      titleTemplate: `%s · ${brand.name}`,
      meta: [
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        { name: 'theme-color', content: brand.colors.primary },
      ],
      link: [{ rel: 'icon', type: 'image/svg+xml', href: '/favicon.svg' }],
    },
    pageTransition: { name: 'page', mode: 'out-in' },
  },

  site: {
    url: siteUrl,
    name: brand.name,
    description: brand.tagline,
    defaultLocale: 'en',
  },

  sitemap: {
    exclude: privateRoutes,
    sources: ['/__sitemap__/urls'],
  },

  robots: {
    disallow: ['/dashboard', '/admin', '/checkout', '/p/', '/api/'],
  },

  schemaOrg: {
    identity: { type: 'Organization', name: brand.name, url: siteUrl, logo: `${siteUrl}/favicon.svg` },
  },

  fonts: {
    families: [
      { name: 'Manrope', provider: 'google', weights: [500, 600, 700, 800] },
      { name: 'Inter', provider: 'google', weights: [400, 500, 600] },
    ],
  },

  routeRules: {
    '/dashboard/**': { ssr: false, robots: false },
    '/admin/**': { ssr: false, robots: false },
    '/checkout/**': { ssr: false, robots: false },
    '/login': { robots: false },
    '/register': { robots: false },
    '/forgot-password': { robots: false },
    '/reset-password': { robots: false },
    '/verify-email': { robots: false },
    '/p/**': { robots: false },
  },

  runtimeConfig: {
    // Server-only: where the Nuxt server reaches Laravel (internal Docker hostname in production).
    apiInternalUrl: process.env.NUXT_API_INTERNAL_URL || 'http://127.0.0.1:8000',
    public: {
      siteUrl,
      shortDomain: process.env.NUXT_PUBLIC_SHORT_DOMAIN || siteUrl,
    },
  },
})
