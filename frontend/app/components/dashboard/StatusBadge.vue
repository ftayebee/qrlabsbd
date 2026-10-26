<script setup lang="ts">
const props = defineProps<{ status: string }>()
const tone = computed(() => ({
  active: 'green', paused: 'amber', archived: 'neutral', trialing: 'blue', past_due: 'amber', canceled: 'neutral',
  expired: 'neutral', incomplete: 'neutral', succeeded: 'green', failed: 'red', refunded: 'neutral', partially_refunded: 'amber',
  pending: 'neutral', paid: 'green', open: 'amber', void: 'neutral', uncollectible: 'red', blocked: 'red', flagged: 'amber', clear: 'green',
  suspended: 'red', processed: 'green', ignored: 'neutral', received: 'blue', resolved: 'green', dismissed: 'neutral',
}[props.status] ?? 'neutral') as 'green' | 'amber' | 'neutral' | 'blue' | 'red')
const label = computed(() => props.status.replace(/_/g, ' ').replace(/^\w/, c => c.toUpperCase()))
</script>

<template>
  <UiBadge :tone="tone"><span class="size-1.5 rounded-full bg-current opacity-70" aria-hidden="true" />{{ label }}</UiBadge>
</template>
