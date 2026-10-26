import type { Item, UsageSummary } from '~/types/api'

/** Plan, entitlements and usage for the signed-in customer, shared across dashboard pages. */
export function useUsage() {
  const api = useApi()
  const usage = useState<UsageSummary | null>('usage', () => null)
  const loading = useState('usage.loading', () => false)

  async function refresh() {
    loading.value = true
    try { usage.value = (await api.get<Item<UsageSummary>>('/account/usage')).data } finally { loading.value = false }
    return usage.value
  }

  const allows = (key: string) => !!usage.value?.entitlements[key]
  const limit = (key: string) => Number(usage.value?.entitlements[key] ?? 0)

  return { usage, loading, refresh, allows, limit }
}
