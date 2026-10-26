import { z } from 'zod'
import type { FaqItem, Plan } from '~/types/api'

// Runtime validation at the public-data boundary: pricing renders on SSR pages,
// so a malformed API response should fail loudly here rather than in a template.
const PriceSchema = z.object({
  id: z.number(), interval: z.enum(['month', 'year']), currency: z.string().length(3), amount: z.number().int(),
  promoAmount: z.number().int().nullable(), promoEndsAt: z.string().nullable(), effectiveAmount: z.number().int(),
})
const PlanSchema = z.object({
  id: z.number(), slug: z.string(), name: z.string(), description: z.string().nullable(), isCustom: z.boolean(),
  highlight: z.boolean(), trialDays: z.number(), sortOrder: z.number(), ctaLabel: z.string().nullable(),
  prices: z.array(PriceSchema),
  features: z.array(z.object({ key: z.string(), label: z.string(), value: z.string(), type: z.enum(['boolean', 'limit', 'text']), highlighted: z.boolean() }).passthrough()),
})

export function usePlans() {
  const api = useApi()
  return useAsyncData('public-plans', async () => {
    const res = await api.get<{ data: unknown }>('/plans')
    return z.array(PlanSchema).parse(res.data) as Plan[]
  }, { default: () => [] as Plan[] })
}

export function usePublicSettings() {
  const api = useApi()
  return useAsyncData('public-settings', async () => (await api.get<{ data: Record<string, unknown> }>('/settings/public')).data, {
    default: () => ({}) as Record<string, unknown>,
  })
}

export function useFaqs(category?: string) {
  const api = useApi()
  return useAsyncData(`faqs-${category ?? 'all'}`, async () => (await api.get<{ data: FaqItem[] }>('/faqs', category ? { category } : undefined)).data, {
    default: () => [] as FaqItem[],
  })
}
