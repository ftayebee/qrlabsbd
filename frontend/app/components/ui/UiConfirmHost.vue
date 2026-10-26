<script setup lang="ts">
const { state, settle } = useConfirm()
const reason = ref('')
const open = computed({
  get: () => state.value.open,
  set: (v) => { if (!v) settle(false) },
})
watch(open, (v) => { if (v) reason.value = '' })

function accept() {
  if (state.value.requireReason) {
    if (reason.value.trim().length < 3) return
    settle(reason.value.trim())
  } else {
    settle(true)
  }
}
</script>

<template>
  <UiModal v-model:open="open" :title="state.title" size="sm">
    <p class="text-sm text-muted">{{ state.message }}</p>
    <UiField v-if="state.requireReason" label="Reason (recorded in the audit log)" class="mt-4" id="confirm-reason">
      <textarea id="confirm-reason" v-model="reason" rows="3" class="field-input" placeholder="Explain why this change is being made" />
    </UiField>
    <template #footer>
      <UiButton variant="secondary" @click="settle(false)">Cancel</UiButton>
      <UiButton :variant="state.tone === 'danger' ? 'danger' : 'primary'" :disabled="state.requireReason && reason.trim().length < 3" @click="accept">
        {{ state.confirmLabel ?? 'Confirm' }}
      </UiButton>
    </template>
  </UiModal>
</template>
