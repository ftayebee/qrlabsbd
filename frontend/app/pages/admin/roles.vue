<script setup lang="ts">
import { Plus } from 'lucide-vue-next'

definePageMeta({ layout: 'admin', middleware: 'admin' })
useSeoMeta({ title: 'Roles', robots: 'noindex' })

interface Role { id: number; slug: string; name: string; description: string | null; isAdmin: boolean; permissions: string[]; usersCount: number }
interface Permission { id: number; slug: string; name: string; group: string }

const api = useApi()
const toast = useToast()
const { confirm } = useConfirm()
const { data, refresh } = await useAsyncData('admin-roles-full', async () => (await api.get<{ data: { roles: Role[]; permissions: Permission[] } }>('/admin/roles')).data, { server: false })
const groups = computed(() => [...new Set(data.value?.permissions.map(p => p.group) ?? [])])

const editing = ref<{ id?: number; slug: string; name: string; description: string; is_admin: boolean; permissions: string[] } | null>(null)
async function save() {
  if (!editing.value) return
  try {
    if (editing.value.id) await api.put(`/admin/roles/${editing.value.id}`, editing.value)
    else await api.post('/admin/roles', editing.value)
    toast.success('Role saved.')
    editing.value = null
    await refresh()
  } catch (e) { toast.error((e as Error).message) }
}
async function remove(r: Role) {
  if (!(await confirm({ title: `Delete role “${r.name}”?`, message: 'Only roles with no users can be deleted.', confirmLabel: 'Delete', tone: 'danger' }))) return
  try { await api.del(`/admin/roles/${r.id}`); await refresh() } catch (e) { toast.error((e as Error).message) }
}
</script>

<template>
  <div>
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 class="text-2xl font-extrabold sm:text-3xl">Roles &amp; permissions</h1>
        <p class="mt-1 text-muted">Assign roles to people from their customer page.</p>
      </div>
      <UiButton @click="editing = { slug: '', name: '', description: '', is_admin: true, permissions: [] }"><Plus class="size-4" /> New role</UiButton>
    </div>
    <div class="mt-6 grid gap-4 lg:grid-cols-2">
      <div v-for="r in data?.roles" :key="r.id" class="card p-5">
        <div class="flex items-start justify-between gap-3">
          <div><h2 class="font-bold">{{ r.name }} <UiBadge v-if="r.isAdmin" tone="dark">Admin</UiBadge></h2><p class="text-xs text-muted">{{ r.slug }} · {{ r.usersCount }} users</p></div>
          <div v-if="r.slug !== 'super-admin'" class="flex gap-1">
            <UiButton size="sm" variant="ghost" @click="editing = { id: r.id, slug: r.slug, name: r.name, description: r.description ?? '', is_admin: r.isAdmin, permissions: [...r.permissions] }">Edit</UiButton>
            <UiButton v-if="r.slug !== 'customer'" size="sm" variant="ghost" class="!text-danger" @click="remove(r)">Delete</UiButton>
          </div>
        </div>
        <p v-if="r.description" class="mt-2 text-sm text-muted">{{ r.description }}</p>
        <div class="mt-3 flex flex-wrap gap-1"><UiBadge v-for="p in r.slug === 'super-admin' ? ['all permissions'] : r.permissions" :key="p">{{ p }}</UiBadge></div>
      </div>
    </div>

    <UiModal :open="!!editing" :title="editing?.id ? 'Edit role' : 'New role'" size="lg" @update:open="v => { if (!v) editing = null }">
      <form v-if="editing" id="role-form" class="space-y-4" @submit.prevent="save">
        <div class="grid gap-4 sm:grid-cols-2">
          <UiField label="Name" id="ro-n"><input id="ro-n" v-model="editing.name" class="field-input" required maxlength="100"></UiField>
          <UiField label="Slug" id="ro-s"><input id="ro-s" v-model="editing.slug" class="field-input font-mono" required maxlength="64"></UiField>
        </div>
        <UiField label="Description" id="ro-d"><input id="ro-d" v-model="editing.description" class="field-input" maxlength="255"></UiField>
        <UiSwitch v-model="editing.is_admin" label="Admin panel access" />
        <div v-for="g in groups" :key="g">
          <p class="field-label capitalize">{{ g }}</p>
          <div class="grid gap-2 sm:grid-cols-2">
            <label v-for="p in data?.permissions.filter(x => x.group === g)" :key="p.slug" class="flex items-center gap-2 text-sm"><input v-model="editing.permissions" type="checkbox" :value="p.slug" class="size-4 accent-[var(--color-brand)]"> {{ p.name }}</label>
          </div>
        </div>
      </form>
      <template #footer><UiButton variant="secondary" @click="editing = null">Cancel</UiButton><UiButton type="submit" form="role-form">Save role</UiButton></template>
    </UiModal>
  </div>
</template>
