<script setup lang="ts">
import { CheckCircle2, Clock } from 'lucide-vue-next'

/**
 * Landing page after the provider's hosted checkout. It never grants a plan
 * itself: it polls the API, which confirms the payment with the provider.
 */
definePageMeta({ layout: 'auth', middleware: 'auth' })
useSeoMeta({ title: 'Confirming your subscription', robots: 'noindex' })

const route = useRoute()
const api = useApi()
const { refresh: refreshUsage } = useUsage()
const sessionId = String(route.query.session_id ?? '')
const state = ref<'checking' | 'active' | 'pending' | 'missing'>(sessionId ? 'checking' : 'missing')
let attempts = 0
let timer: ReturnType<typeof setTimeout> | undefined

async function poll() {
  attempts++
  try {
    const res = await api.get<{ data: { active: boolean } }>(`/billing/checkout/${encodeURIComponent(sessionId)}/status`)
    if (res.data.active) { state.value = 'active'; refreshUsage().catch(() => {}); return }
  } catch { /* keep polling */ }
  if (attempts < 15) timer = setTimeout(poll, 2000)
  else state.value = 'pending'
}
onMounted(() => { if (sessionId) poll() })
onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
  <div class="text-center">
    <template v-if="state === 'active'">
      <CheckCircle2 class="mx-auto size-14 text-brand" />
      <h1 class="mt-6 text-3xl font-extrabold">You’re all set</h1>
      <p class="mt-2 text-muted">Your subscription is active and your new limits apply now.</p>
      <UiButton to="/dashboard" block class="mt-8">Go to dashboard</UiButton>
    </template>
    <template v-else-if="state === 'checking'">
      <UiSpinner label="Confirming your payment with our provider…" />
    </template>
    <template v-else>
      <Clock class="mx-auto size-14 text-muted" />
      <h1 class="mt-6 text-3xl font-extrabold">{{ state === 'missing' ? 'Nothing to confirm' : 'Still processing' }}</h1>
      <p class="mt-2 text-muted">{{ state === 'missing' ? 'This page is opened automatically after checkout.' : 'Your payment is taking longer than usual to confirm. We’ll update your plan as soon as the provider confirms it — no need to pay again.' }}</p>
      <UiButton to="/dashboard/billing" variant="secondary" block class="mt-8">View billing</UiButton>
    </template>
  </div>
</template>
