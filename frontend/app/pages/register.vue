<script setup lang="ts">
import { ApiError } from '~/composables/useApi'

definePageMeta({ layout: 'auth', middleware: 'guest' })
useSeoMeta({ title: 'Create your account', robots: 'noindex' })

const route = useRoute()
const { register } = useAuth()
const form = reactive({ name: '', email: '', password: '', password_confirmation: '', terms: false })
const errors = ref<Record<string, string>>({})
const loading = ref(false)

async function submit() {
  errors.value = {}
  if (form.password !== form.password_confirmation) { errors.value.password_confirmation = 'Passwords do not match.'; return }
  loading.value = true
  try {
    await register(form)
    const r = String(route.query.redirect ?? '')
    await navigateTo(r.startsWith('/') && !r.startsWith('//') ? r : '/dashboard')
  } catch (e) {
    errors.value = e instanceof ApiError ? e.fieldErrors : { email: (e as Error).message }
  } finally { loading.value = false }
}
</script>

<template>
  <div>
    <h1 class="text-3xl font-extrabold">Create your free account</h1>
    <p class="mt-2 text-muted">Save codes, create dynamic links and see your scans.</p>
    <form class="mt-8 space-y-5" novalidate @submit.prevent="submit">
      <UiField label="Name" id="r-name" :error="errors.name">
        <input id="r-name" v-model="form.name" class="field-input" autocomplete="name" required maxlength="120" autofocus>
      </UiField>
      <UiField label="Work email" id="r-email" :error="errors.email">
        <input id="r-email" v-model.trim="form.email" type="email" class="field-input" autocomplete="email" required>
      </UiField>
      <UiField label="Password" id="r-password" :error="errors.password" hint="At least 10 characters with letters and numbers.">
        <input id="r-password" v-model="form.password" type="password" class="field-input" autocomplete="new-password" required minlength="10">
      </UiField>
      <UiField label="Confirm password" id="r-password2" :error="errors.password_confirmation">
        <input id="r-password2" v-model="form.password_confirmation" type="password" class="field-input" autocomplete="new-password" required>
      </UiField>
      <div>
        <label class="flex items-start gap-2 text-sm text-muted">
          <input v-model="form.terms" type="checkbox" class="mt-0.5 size-4 accent-[var(--color-brand)]" required>
          <span>I agree to the <NuxtLink to="/legal/terms" target="_blank" class="font-semibold text-brand-700 hover:underline">Terms</NuxtLink> and <NuxtLink to="/legal/privacy" target="_blank" class="font-semibold text-brand-700 hover:underline">Privacy Policy</NuxtLink>.</span>
        </label>
        <p v-if="errors.terms" class="field-error">You need to accept the terms to continue.</p>
      </div>
      <UiButton type="submit" block :loading="loading" :disabled="!form.terms">Create account</UiButton>
    </form>
    <p class="mt-8 text-center text-sm text-muted">Already have an account? <NuxtLink :to="{ path: '/login', query: route.query }" class="font-semibold text-brand-700 hover:underline">Sign in</NuxtLink></p>
  </div>
</template>
