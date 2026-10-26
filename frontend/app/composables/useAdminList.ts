import type { Paginated } from '~/types/api'

/**
 * Server-side paginated, filtered list for admin tables. Filtering, sorting
 * and pagination always happen in the API so large datasets stay fast.
 */
export function useAdminList<T>(key: string, path: string, initial: Record<string, string> = {}) {
  const api = useApi()
  const filters = reactive<Record<string, string>>({ ...initial })
  const page = ref(1)
  const search = ref(filters.search ?? '')
  let timer: ReturnType<typeof setTimeout> | undefined
  watch(search, (v) => { clearTimeout(timer); timer = setTimeout(() => { filters.search = v; page.value = 1 }, 300) })
  watch(() => ({ ...filters }), () => { page.value = 1 }, { deep: true })

  const query = computed(() => ({ ...Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== '')), page: page.value }))
  const result = useAsyncData(key, () => api.get<Paginated<T>>(path, query.value), { server: false, watch: [query] })

  return { ...result, filters, page, search }
}
