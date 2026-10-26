<script setup lang="ts">
import { ArrowLeft } from 'lucide-vue-next'
import type { QrCodeRecord } from '~/types/qr'
import { QR_TYPES, type QrType } from '~/types/qr'

definePageMeta({ layout: 'dashboard', middleware: 'auth' })
useSeoMeta({ title: 'New QR code', robots: 'noindex' })

const route = useRoute()
const t = route.query.type as QrType
const { refresh } = useUsage()

async function onSaved(record: QrCodeRecord) {
  refresh().catch(() => {})
  await navigateTo(`/dashboard/qr-codes/${record.id}`)
}
</script>

<template>
  <div>
    <NuxtLink to="/dashboard/qr-codes" class="inline-flex items-center gap-1.5 text-sm font-semibold text-muted hover:text-ink"><ArrowLeft class="size-4" /> QR codes</NuxtLink>
    <h1 class="mt-3 mb-6 text-2xl font-extrabold sm:text-3xl">Create a QR code</h1>
    <QrGenerator :initial-type="QR_TYPES.includes(t) ? t : 'url'" :restore-draft="route.query.draft === '1'" @saved="onSaved" />
  </div>
</template>
