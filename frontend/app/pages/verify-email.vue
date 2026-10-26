<script setup lang="ts">
import { MailCheck } from 'lucide-vue-next'
import type { Item, User } from '~/types/api'

definePageMeta({ layout: 'auth', middleware: 'auth' })
useSeoMeta({ title: 'Verify your email', robots: 'noindex' })

const route = useRoute()
const api = useApi()
const { user } = useAuth()
const state = ref<'idle' | 'verifying' | 'verified' | 'error'>('idle')
const message = ref('')
const resending = ref(false)

onMounted(async () => {
  const { id, hash, expires, signature } = route.query
  if (!id || !hash || !signature) return
  state.value = 'verifying'
  try {
    const res = await api.get<Item<User>>(`/auth/email/verify/${id}/${hash}`, { expires, signature })
    user.value = res.data
    state.value = 'verified'
  } catch (e) {
    state.value = 'error'
    message.value = (e as Error).message
  }
})

async function resend() {
  resending.value = true
  try {
    message.value = (await api.post<{ message: string }>('/auth/email/verification-notification')).message
  } catch (e) { message.value = (e as Error).message } finally { resending.value = false }
}
</script>

<template>
  <div>
    <span class="grid size-12 place-items-center rounded-2xl bg-soft text-brand-700"><MailCheck class="size-6" /></span>
    <template v-if="state === 'verified' || user?.emailVerified">
      <h1 class="mt-6 text-3xl font-extrabold">Email verified</h1>
      <p class="mt-2 text-muted">Thanks! Your account is fully set up.</p>
      <UiButton to="/dashboard" block class="mt-8">Go to dashboard</UiButton>
    </template>
    <template v-else>
      <h1 class="mt-6 text-3xl font-extrabold">{{ state === 'verifying' ? 'Verifying…' : 'Check your inbox' }}</h1>
      <p class="mt-2 text-muted">We sent a verification link to <strong class="text-ink">{{ user?.email }}</strong>. Verification is required before you subscribe to a paid plan.</p>
      <UiAlert v-if="state === 'error'" tone="danger" class="mt-6">{{ message || 'This link is invalid or has expired.' }} Request a new one below.</UiAlert>
      <UiAlert v-else-if="message" tone="success" class="mt-6">{{ message }}</UiAlert>
      <div class="mt-8 grid gap-3">
        <UiButton :loading="resending" @click="resend">Resend verification email</UiButton>
        <UiButton to="/dashboard" variant="secondary">Continue to dashboard</UiButton>
      </div>
    </template>
  </div>
</template>
