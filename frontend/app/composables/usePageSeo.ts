/** Title, description, canonical URL and breadcrumb structured data for public pages. */
export function usePageSeo(opts: { title: string; description: string; path?: string; crumbs?: { label: string; to?: string }[] }) {
  const config = useRuntimeConfig()
  const route = useRoute()
  const url = `${String(config.public.siteUrl).replace(/\/$/, '')}${opts.path ?? route.path}`

  useSeoMeta({ title: opts.title, description: opts.description, ogTitle: opts.title, ogDescription: opts.description, ogUrl: url })
  useHead({ link: [{ rel: 'canonical', href: url }] })

  if (opts.crumbs?.length) {
    useSchemaOrg([defineBreadcrumb({
      itemListElement: [{ name: 'Home', item: '/' }, ...opts.crumbs.map(c => ({ name: c.label, item: c.to }))],
    })])
  }
}
