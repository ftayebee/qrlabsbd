<script setup lang="ts">
import { Lock, Trash2, UserPlus } from 'lucide-vue-next'
import { ApiError } from '~/composables/useApi'
import { formatRelative } from '~/utils/format'

definePageMeta({ layout: 'dashboard', middleware: 'auth' })
useSeoMeta({ title: 'Team', robots: 'noindex' })

interface TeamData {
  limit: number
  members: { id: number; email: string; name: string | null; role: string; acceptedAt: string | null; invitedAt: string }[]
  workspaces: { ownerId: number; ownerName: string; role: string }[]
}

const api = useApi()
const toast = useToast()
const { confirm } = useConfirm()
const route = useRoute()
const { data, refresh } = await useAsyncData('team', async () => (await api.get<{ data: TeamData }>('/team')).data, { server: false })

const form = reactive({ email: '', role: 'editor' })
const errors = ref<Record<string, string>>({})
const inviting = ref(false)
async function invite() {
  inviting.value = true
  errors.value = {}
  try {
    await api.post('/team/invite', form)
    toast.success(`Invitation sent to ${form.email}.`)
    form.email = ''
    await refresh()
  } catch (e) {
    if (e instanceof ApiError) errors.value = e.fieldErrors
    toast.error((e as Error).message)
  } finally { inviting.value = false }
}

async function remove(id: number, email: string) {
  if (!(await confirm({ title: 'Remove member?', message: `${email} will lose access to your QR codes.`, confirmLabel: 'Remove', tone: 'danger' }))) return
  await api.del(`/team/${id}`)
  await refresh()
}

onMounted(async () => {
  const token = route.query.invite
  if (typeof token === 'string' && token) {
    try { await api.post('/team/accept', { token }); toast.success('Invitation accepted.'); await refresh() } catch (e) { toast.error((e as Error).message) }
  }
})
</script>

<template>
  <div>
    <h1 class="text-2xl font-extrabold sm:text-3xl">Team</h1>
    <p class="mt-1 text-muted">Invite colleagues to view or edit your QR codes.</p>

    <UiSpinner v-if="!data" />
    <template v-else>
      <section v-if="data.workspaces.length" class="card mt-6 p-6">
        <h2 class="font-bold">Workspaces you belong to</h2>
        <ul class="mt-3 space-y-2 text-sm">
          <li v-for="w in data.workspaces" :key="w.ownerId" class="flex items-center justify-between">
            <span>{{ w.ownerName }} <span class="text-muted">· {{ w.role }}</span></span>
          </li>
        </ul>
      </section>

      <UiEmpty v-if="data.limit === 0" class="mt-6" title="Team seats are a paid feature" description="Upgrade to invite colleagues as viewers or editors.">
        <template #icon><Lock class="size-6" /></template>
        <UiButton to="/pricing">See plans</UiButton>
      </UiEmpty>

      <template v-else>
        <section class="card mt-6 p-6">
          <div class="flex items-center justify-between">
            <h2 class="font-bold">Invite a member</h2>
            <p class="text-sm text-muted">{{ data.members.length }} / {{ data.limit < 0 ? 'Unlimited' : data.limit }} seats</p>
          </div>
          <form class="mt-4 grid gap-3 sm:grid-cols-[1fr_160px_auto]" @submit.prevent="invite">
            <UiField id="t-email" :error="errors.email"><input id="t-email" v-model.trim="form.email" type="email" class="field-input" placeholder="colleague@company.com" aria-label="Email" required></UiField>
            <select v-model="form.role" class="field-input" aria-label="Role"><option value="editor">Editor</option><option value="viewer">Viewer</option></select>
            <UiButton type="submit" :loading="inviting"><UserPlus class="size-4" /> Invite</UiButton>
          </form>
        </section>
        <section class="card mt-6 overflow-x-auto">
          <table v-if="data.members.length" class="table-base min-w-[560px]">
            <thead><tr><th>Member</th><th>Role</th><th>Status</th><th class="w-12"><span class="sr-only">Remove</span></th></tr></thead>
            <tbody>
              <tr v-for="m in data.members" :key="m.id">
                <td><p class="font-medium">{{ m.name ?? m.email }}</p><p v-if="m.name" class="text-xs text-muted">{{ m.email }}</p></td>
                <td class="capitalize">{{ m.role }}</td>
                <td><UiBadge :tone="m.acceptedAt ? 'green' : 'amber'">{{ m.acceptedAt ? 'Active' : `Invited ${formatRelative(m.invitedAt)}` }}</UiBadge></td>
                <td><button type="button" class="rounded-lg p-1.5 text-muted hover:text-danger" :aria-label="`Remove ${m.email}`" @click="remove(m.id, m.email)"><Trash2 class="size-4" /></button></td>
              </tr>
            </tbody>
          </table>
          <p v-else class="p-6 text-sm text-muted">No members yet.</p>
        </section>
      </template>
    </template>
  </div>
</template>
