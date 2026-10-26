<script setup lang="ts">
definePageMeta({ layout: 'auth', middleware: 'guest' })
useSeoMeta({ title: 'Reset your password', robots: 'noindex' })

const api = useApi()
const email = ref('')
const sent = ref('')
const error = ref('')
const loading = ref(false)

async function submit() {
  loading.value = true
  error.value = ''
  try {
    sent.value = (await api.post<{ message: string }>('/auth/forgot-password', { email: email.value })).message
  } catch (e) { error.value = (e as Error).message } finally { loading.value = false }
}
</script>

<template>
  <div>
    <h1 class="text-3xl font-extrabold">Forgot your password?</h1>
    <p class="mt-2 text-muted">Enter your email and we’ll send a reset link.</p>
    <UiAlert v-if="sent" tone="success" class="mt-8">{{ sent }}</UiAlert>
    <form v-else class="mt-8 space-y-5" novalidate @submit.prevent="submit">
      <UiField label="Email" id="f-email" :error="error">
        <input id="f-email" v-model.trim="email" type="email" class="field-input" autocomplete="email" required autofocus>
      </UiField>
      <UiButton type="submit" block :loading="loading">Send reset link</UiButton>
    </form>
    <p class="mt-8 text-center text-sm"><NuxtLink to="/login" class="font-semibold text-brand-700 hover:underline">Back to sign in</NuxtLink></p>
  </div>
</template>
