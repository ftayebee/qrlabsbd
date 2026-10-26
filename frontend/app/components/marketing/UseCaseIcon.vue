<script setup lang="ts">
import { Briefcase, GraduationCap, Megaphone, ShoppingBag, Store, Ticket, UtensilsCrossed } from 'lucide-vue-next'
import type { UseCase } from '~/utils/content/useCases'

defineProps<{ icon: UseCase['icon'] }>()
const icons = { UtensilsCrossed, Store, ShoppingBag, Ticket, Megaphone, GraduationCap, Briefcase }
</script>

<template>
  <component :is="icons[icon]" aria-hidden="true" />
</template>
