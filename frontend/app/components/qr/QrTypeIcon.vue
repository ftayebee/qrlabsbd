<script setup lang="ts">
import { AtSign, Contact, FileText, IdCard, Link2, ListTree, Mail, MessageSquare, Phone, Smartphone, Type, Wifi } from 'lucide-vue-next'
import type { QrType } from '~/types/qr'

defineProps<{ type: QrType }>()

// Explicit map keeps the icon set tree-shakeable.
const icons = {
  url: Link2, pdf: FileText, multi_url: ListTree, contact: Contact, text: Type, app: Smartphone,
  sms: MessageSquare, email: Mail, phone: Phone, social: AtSign, business_card: IdCard, wifi: Wifi,
} as const
</script>

<template>
  <component :is="icons[type]" aria-hidden="true" />
</template>
