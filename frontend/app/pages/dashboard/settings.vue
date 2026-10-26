<script setup lang="ts">
import type { Item, User } from '~/types/api'
import { ApiError } from '~/composables/useApi'

definePageMeta({ layout: 'dashboard', middleware: 'auth' })
useSeoMeta({ title: 'Settings', robots: 'noindex' })

const api = useApi()
const toast = useToast()
const { user } = useAuth()

const profile = reactive({ name: user.value?.name ?? '', email: user.value?.email ?? '', current_password: '' })
const profileErrors = ref<Record<string, string>>({})
const savingProfile = ref(false)
const emailChanged = computed(() => profile.email.trim().toLowerCase() !== user.value?.email)

async function saveProfile() {
  savingProfile.value = true
  profileErrors.value = {}
  try {
    const body: Record<string, string> = { name: profile.name }
    if (emailChanged.value) Object.assign(body, { email: profile.email, current_password: profile.current_password })
    user.value = (await api.patch<Item<User>>('/account/profile', body)).data
    profile.current_password = ''
    toast.success(emailChanged.value ? 'Saved. Check your inbox to verify the new address.' : 'Profile saved.')
  } catch (e) {
    if (e instanceof ApiError) profileErrors.value = e.fieldErrors
    toast.error((e as Error).message)
  } finally { savingProfile.value = false }
}

const pw = reactive({ current_password: '', password: '', password_confirmation: '' })
const pwErrors = ref<Record<string, string>>({})
const savingPw = ref(false)
async function savePassword() {
  savingPw.value = true
  pwErrors.value = {}
  try {
    await api.put('/account/password', pw)
    Object.assign(pw, { current_password: '', password: '', password_confirmation: '' })
    toast.success('Password updated. Other sessions were signed out.')
  } catch (e) {
    if (e instanceof ApiError) pwErrors.value = e.fieldErrors
    toast.error((e as Error).message)
  } finally { savingPw.value = false }
}

const { data: prefs } = await useAsyncData('notif-prefs', async () => (await api.get<{ data: Record<string, boolean> }>('/account/notifications')).data, { server: false })
const prefLabels: Record<string, [string, string]> = {
  billing: ['Billing', 'Receipts, failed payments and renewal reminders'],
  usage_alerts: ['Usage alerts', 'When you approach plan limits'],
  weekly_report: ['Weekly report', 'A summary of scans across your codes'],
  product_updates: ['Product updates', 'New features and tips (occasional)'],
}
async function togglePref(key: string, value: boolean) {
  try { prefs.value = (await api.put<{ data: Record<string, boolean> }>('/account/notifications', { [key]: value })).data } catch (e) { toast.error((e as Error).message) }
}

const deleteOpen = ref(false)
const deletePassword = ref('')
const deleting = ref(false)
async function deleteAccount() {
  deleting.value = true
  try {
    await api.raw('/account', { method: 'DELETE', body: { password: deletePassword.value } })
    user.value = null
    await navigateTo('/')
  } catch (e) { toast.error((e as Error).message) } finally { deleting.value = false }
}
</script>

<template>
  <div class="max-w-3xl">
    <h1 class="text-2xl font-extrabold sm:text-3xl">Settings</h1>

    <section class="card mt-6 p-6">
      <h2 class="font-bold">Profile</h2>
      <form class="mt-5 grid gap-4 sm:grid-cols-2" @submit.prevent="saveProfile">
        <UiField label="Name" id="s-name" :error="profileErrors.name"><input id="s-name" v-model="profile.name" class="field-input" autocomplete="name" maxlength="120"></UiField>
        <UiField label="Email" id="s-email" :error="profileErrors.email"><input id="s-email" v-model.trim="profile.email" type="email" class="field-input" autocomplete="email"></UiField>
        <UiField v-if="emailChanged" label="Current password" id="s-cur" :error="profileErrors.current_password" class="sm:col-span-2" hint="Required to change your email address.">
          <input id="s-cur" v-model="profile.current_password" type="password" class="field-input" autocomplete="current-password">
        </UiField>
        <div class="sm:col-span-2"><UiButton type="submit" :loading="savingProfile">Save profile</UiButton></div>
      </form>
    </section>

    <section class="card mt-6 p-6">
      <h2 class="font-bold">Password</h2>
      <form class="mt-5 grid gap-4 sm:grid-cols-3" @submit.prevent="savePassword">
        <UiField label="Current" id="p-cur" :error="pwErrors.current_password"><input id="p-cur" v-model="pw.current_password" type="password" class="field-input" autocomplete="current-password"></UiField>
        <UiField label="New" id="p-new" :error="pwErrors.password"><input id="p-new" v-model="pw.password" type="password" class="field-input" autocomplete="new-password"></UiField>
        <UiField label="Confirm" id="p-conf"><input id="p-conf" v-model="pw.password_confirmation" type="password" class="field-input" autocomplete="new-password"></UiField>
        <div class="sm:col-span-3"><UiButton type="submit" variant="secondary" :loading="savingPw">Update password</UiButton></div>
      </form>
    </section>

    <section class="card mt-6 p-6">
      <h2 class="font-bold">Email notifications</h2>
      <div v-if="prefs" class="mt-5 space-y-5">
        <UiSwitch v-for="(label, key) in prefLabels" :key="key" :model-value="!!prefs[key]" :label="label[0]" :description="label[1]" @update:model-value="v => togglePref(key, v)" />
      </div>
    </section>

    <section class="mt-6 rounded-[var(--radius-card)] border border-orange-200 bg-orange-50/60 p-6">
      <h2 class="font-bold text-orange-900">Close account</h2>
      <p class="mt-1 text-sm text-orange-900/80">Deletes your QR codes and stops all dynamic codes from working. Cancel any paid subscription first.</p>
      <UiButton variant="danger" class="mt-4" @click="deleteOpen = true">Close my account</UiButton>
    </section>

    <UiModal v-model:open="deleteOpen" title="Close your account?" description="This cannot be undone. Printed dynamic codes will stop working." size="sm">
      <UiField label="Confirm with your password" id="del-pass"><input id="del-pass" v-model="deletePassword" type="password" class="field-input" autocomplete="current-password"></UiField>
      <template #footer>
        <UiButton variant="secondary" @click="deleteOpen = false">Keep account</UiButton>
        <UiButton variant="danger" :loading="deleting" :disabled="!deletePassword" @click="deleteAccount">Close account</UiButton>
      </template>
    </UiModal>
  </div>
</template>
