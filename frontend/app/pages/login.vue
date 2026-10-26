<script setup lang="ts">
import { ApiError } from '~/composables/useApi'

definePageMeta({ layout: 'auth', middleware: 'guest' })
useSeoMeta({ title: 'Sign in', robots: 'noindex' })

const route = useRoute()
const { login } = useAuth()
const form = reactive({ email: '', password: '', remember: true })
const errors = ref<Record<string, string>>({})
const loading = ref(false)

/** Only same-site paths are honoured, preventing open redirects through ?redirect=. */
function safeRedirect(fallback: string) {
  const r = String(route.query.redirect ?? '')
  return r.startsWith('/') && !r.startsWith('//') ? r : fallback
}

async function submit() {
  loading.value = true
  errors.value = {}
  try {
    const user = await login(form.email, form.password, form.remember)
    await navigateTo(safeRedirect(user.isAdmin ? '/admin' : '/dashboard'))
  } catch (e) {
    errors.value = e instanceof ApiError && Object.keys(e.errors).length ? e.fieldErrors : { email: (e as Error).message }
  } finally { loading.value = false }
}
</script>

<template>
  <div>
    <h1 class="text-3xl font-extrabold">Welcome back</h1>
    <p class="mt-2 text-muted">Sign in to manage your QR codes.</p>
    <form class="mt-8 space-y-5" novalidate @submit.prevent="submit">
      <UiField label="Email" id="l-email" :error="errors.email">
        <input id="l-email" v-model.trim="form.email" type="email" class="field-input" autocomplete="email" required autofocus :aria-invalid="!!errors.email">
      </UiField>
      <UiField label="Password" id="l-password" :error="errors.password">
        <input id="l-password" v-model="form.password" type="password" class="field-input" autocomplete="current-password" required>
      </UiField>
      <div class="flex items-center justify-between text-sm">
        <label class="flex items-center gap-2 text-muted"><input v-model="form.remember" type="checkbox" class="size-4 accent-[var(--color-brand)]"> Keep me signed in</label>
        <NuxtLink to="/forgot-password" class="font-semibold text-brand-700 hover:underline">Forgot password?</NuxtLink>
      </div>
      <UiButton type="submit" block :loading="loading">Sign in</UiButton>
    </form>
    <p class="mt-8 text-center text-sm text-muted">New here? <NuxtLink :to="{ path: '/register', query: route.query }" class="font-semibold text-brand-700 hover:underline">Create a free account</NuxtLink></p>
  </div>
</template>
