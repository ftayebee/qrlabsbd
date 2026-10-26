<script setup lang="ts">
import { ApiError } from '~/composables/useApi'

definePageMeta({ layout: 'auth' })
useSeoMeta({ title: 'Choose a new password', robots: 'noindex' })

const route = useRoute()
const api = useApi()
const form = reactive({ token: String(route.query.token ?? ''), email: String(route.query.email ?? ''), password: '', password_confirmation: '' })
const errors = ref<Record<string, string>>({})
const done = ref('')
const loading = ref(false)

async function submit() {
  loading.value = true
  errors.value = {}
  try {
    done.value = (await api.post<{ message: string }>('/auth/reset-password', form)).message
  } catch (e) {
    errors.value = e instanceof ApiError ? e.fieldErrors : { password: (e as Error).message }
  } finally { loading.value = false }
}
</script>

<template>
  <div>
    <h1 class="text-3xl font-extrabold">Choose a new password</h1>
    <UiAlert v-if="!form.token" tone="danger" class="mt-8">This reset link is incomplete. Request a new one from the <NuxtLink to="/forgot-password" class="font-semibold underline">forgot password</NuxtLink> page.</UiAlert>
    <template v-else-if="done">
      <UiAlert tone="success" class="mt-8">{{ done }}</UiAlert>
      <UiButton to="/login" block class="mt-6">Sign in</UiButton>
    </template>
    <form v-else class="mt-8 space-y-5" novalidate @submit.prevent="submit">
      <UiField label="Email" id="rp-email" :error="errors.email">
        <input id="rp-email" v-model.trim="form.email" type="email" class="field-input" autocomplete="email" required>
      </UiField>
      <UiField label="New password" id="rp-pass" :error="errors.password" hint="At least 10 characters with letters and numbers.">
        <input id="rp-pass" v-model="form.password" type="password" class="field-input" autocomplete="new-password" required>
      </UiField>
      <UiField label="Confirm password" id="rp-pass2">
        <input id="rp-pass2" v-model="form.password_confirmation" type="password" class="field-input" autocomplete="new-password" required>
      </UiField>
      <UiButton type="submit" block :loading="loading">Update password</UiButton>
    </form>
  </div>
</template>
