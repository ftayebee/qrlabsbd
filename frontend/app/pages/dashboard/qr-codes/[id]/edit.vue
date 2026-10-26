<script setup lang="ts">
import { ArrowLeft } from 'lucide-vue-next'
import type { Item } from '~/types/api'
import type { QrCodeRecord } from '~/types/qr'

definePageMeta({ layout: 'dashboard', middleware: 'auth' })
useSeoMeta({ title: 'Edit QR code', robots: 'noindex' })

const route = useRoute()
const id = Number(route.params.id)
const api = useApi()
const { data: qr, error } = await useAsyncData(`qr-edit-${id}`, async () => (await api.get<Item<QrCodeRecord>>(`/qr-codes/${id}`)).data, { server: false })

async function onSaved() {
  await navigateTo(`/dashboard/qr-codes/${id}`)
}
</script>

<template>
  <div>
    <NuxtLink :to="`/dashboard/qr-codes/${id}`" class="inline-flex items-center gap-1.5 text-sm font-semibold text-muted hover:text-ink"><ArrowLeft class="size-4" /> Back to code</NuxtLink>
    <UiAlert v-if="error" tone="danger" class="mt-6">This QR code could not be loaded.</UiAlert>
    <UiSpinner v-else-if="!qr" />
    <template v-else>
      <h1 class="mt-3 mb-2 text-2xl font-extrabold sm:text-3xl">Edit “{{ qr.name }}”</h1>
      <p class="mb-6 text-muted">{{ qr.mode === 'dynamic' ? 'Changes go live instantly. The printed code stays the same.' : 'Static codes store their content directly; after editing, download and reprint the new version.' }}</p>
      <QrGenerator :record="qr" @saved="onSaved" />
    </template>
  </div>
</template>
