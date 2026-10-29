import type { H3Event } from 'h3'

/**
 * Forwards a request to the Laravel API. The browser only ever talks to the
 * Nuxt origin, so Sanctum session cookies stay first-party and no API
 * credentials are exposed to the client. In production Nginx may route these
 * paths straight to Laravel; this proxy keeps dev and SSR working the same way.
 */
export function proxyToApi(event: H3Event) {
  const config = useRuntimeConfig(event)
  // The base is Laravel's origin. Drop a trailing "/api" in case the env var was
  // set to the API prefix, which would otherwise yield /api/api/v1 and /api/sanctum.
  const target = config.apiInternalUrl.replace(/\/+$/, '').replace(/\/api$/, '') + event.path
  const siteUrl = config.public.siteUrl.replace(/\/$/, '')
  const headers = getRequestHeaders(event)

  return proxyRequest(event, target, {
    // Never follow redirects server-side: /r/* 302s must reach the browser, and
    // following them would make this server fetch user-supplied URLs (SSRF).
    fetchOptions: { redirect: 'manual' },
    headers: {
      // Sanctum treats a request as stateful only when Origin/Referer match a
      // stateful domain. Internal SSR requests have neither, so supply ours.
      origin: headers.origin ?? siteUrl,
      referer: headers.referer ?? `${siteUrl}/`,
      'x-forwarded-host': getRequestHost(event, { xForwardedHost: true }),
      'x-forwarded-proto': getRequestProtocol(event, { xForwardedProto: true }),
    },
  })
}
