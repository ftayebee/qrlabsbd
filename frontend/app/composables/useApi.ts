import type { FetchOptions } from 'ofetch'
import type { ApiErrorBody } from '~/types/api'

export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
    public errors: Record<string, string[]> = {},
    public code?: string,
  ) {
    super(message)
  }

  /** Flattens Laravel's `field => [messages]` into `field => first message`. */
  get fieldErrors(): Record<string, string> {
    return Object.fromEntries(Object.entries(this.errors).map(([k, v]) => [k.replace(/^content\./, ''), v[0] ?? '']))
  }
}

function readCookie(name: string): string | undefined {
  if (import.meta.server) return undefined
  const match = document.cookie.split('; ').find(row => row.startsWith(`${name}=`))
  return match ? decodeURIComponent(match.split('=')[1] ?? '') : undefined
}

let csrfPromise: Promise<unknown> | null = null

async function ensureCsrf() {
  if (import.meta.server || readCookie('XSRF-TOKEN')) return
  csrfPromise ??= $fetch('/sanctum/csrf-cookie', { credentials: 'include' }).finally(() => { csrfPromise = null })
  await csrfPromise
}

type Method = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'

/**
 * Typed client for the Laravel API. All calls go to the same origin and are
 * proxied by the Nuxt server, which forwards the session cookie during SSR.
 */
export function useApi() {
  const requestFetch = useRequestFetch()

  async function request<T>(path: string, options: FetchOptions<'json'> & { method?: Method } = {}): Promise<T> {
    const method = (options.method ?? 'GET').toUpperCase() as Method
    if (method !== 'GET') await ensureCsrf()

    const headers = new Headers(options.headers as HeadersInit | undefined)
    headers.set('Accept', 'application/json')
    const xsrf = readCookie('XSRF-TOKEN')
    if (xsrf) headers.set('X-XSRF-TOKEN', xsrf)

    try {
      return await (requestFetch as typeof $fetch)<T>(`/api/v1${path}`, {
        ...options,
        method,
        headers,
        credentials: 'include',
      } as never) as T
    } catch (err: unknown) {
      const e = err as { status?: number; statusCode?: number; data?: ApiErrorBody }
      const status = e.status ?? e.statusCode ?? 0
      const body = e.data
      throw new ApiError(status, body?.message ?? (status ? 'Request failed' : 'Network error. Check your connection.'), body?.errors ?? {}, body?.code)
    }
  }

  return {
    get: <T>(path: string, query?: Record<string, unknown>) => request<T>(path, { query }),
    post: <T>(path: string, body?: unknown) => request<T>(path, { method: 'POST', body: body as never }),
    put: <T>(path: string, body?: unknown) => request<T>(path, { method: 'PUT', body: body as never }),
    patch: <T>(path: string, body?: unknown) => request<T>(path, { method: 'PATCH', body: body as never }),
    del: <T>(path: string) => request<T>(path, { method: 'DELETE' }),
    upload: <T>(path: string, form: FormData) => request<T>(path, { method: 'POST', body: form }),
    raw: request,
  }
}
