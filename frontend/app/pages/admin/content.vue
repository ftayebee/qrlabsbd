<script setup lang="ts">
import { Plus } from 'lucide-vue-next'
import type { Paginated } from '~/types/api'
import { formatRelative } from '~/utils/format'
import { renderMarkdown } from '~/utils/markdown'

definePageMeta({ layout: 'admin', middleware: 'admin' })
useSeoMeta({ title: 'Content', robots: 'noindex' })

interface Faq { id?: number; question: string; answer: string; category: string | null; sort_order: number; is_published: boolean }
interface Page { id?: number; slug: string; type: 'legal' | 'landing'; title: string; body: string; meta_title: string | null; meta_description: string | null; is_published: boolean; updated_at?: string }
interface Template { id: number; key: string; name: string; subject: string; body: string; variables: string[] | null; is_active: boolean }
interface Message { id: number; name: string; email: string; topic: string; message: string; status: string; created_at: string }

const api = useApi()
const toast = useToast()
const { confirm } = useConfirm()
const tab = ref('faqs')

const { data: faqs, refresh: refreshFaqs } = await useAsyncData('admin-faqs', async () => (await api.get<{ data: Faq[] }>('/admin/faqs')).data, { server: false, default: () => [] })
const { data: pages, refresh: refreshPages } = await useAsyncData('admin-pages', async () => (await api.get<{ data: Page[] }>('/admin/content-pages')).data, { server: false, default: () => [] })
const { data: templates, refresh: refreshTemplates } = await useAsyncData('admin-templates', async () => (await api.get<{ data: Template[] }>('/admin/email-templates')).data, { server: false, default: () => [] })
const { data: messages, refresh: refreshMessages } = await useAsyncData('admin-messages', () => api.get<Paginated<Message>>('/admin/contact-messages'), { server: false })

const faq = ref<Faq | null>(null)
async function saveFaq() {
  if (!faq.value) return
  try {
    if (faq.value.id) await api.put(`/admin/faqs/${faq.value.id}`, faq.value)
    else await api.post('/admin/faqs', faq.value)
    faq.value = null
    toast.success('FAQ saved.')
    await refreshFaqs()
  } catch (e) { toast.error((e as Error).message) }
}
async function deleteFaq(f: Faq) {
  if (!(await confirm({ title: 'Delete this question?', message: f.question, confirmLabel: 'Delete', tone: 'danger' }))) return
  await api.del(`/admin/faqs/${f.id}`)
  await refreshFaqs()
}

const page = ref<Page | null>(null)
const preview = computed(() => (page.value ? renderMarkdown(page.value.body) : ''))
// Non-null view of the page being edited; only used inside the v-if'd form.
const pf = computed(() => page.value as Page)
async function savePage() {
  if (!page.value) return
  try {
    if (page.value.id) await api.put(`/admin/content-pages/${page.value.id}`, page.value)
    else await api.post('/admin/content-pages', page.value)
    page.value = null
    toast.success('Page saved.')
    await refreshPages()
  } catch (e) { toast.error((e as Error).message) }
}

const tpl = ref<Template | null>(null)
async function saveTemplate() {
  if (!tpl.value) return
  try { await api.put(`/admin/email-templates/${tpl.value.id}`, tpl.value); tpl.value = null; toast.success('Template saved.'); await refreshTemplates() } catch (e) { toast.error((e as Error).message) }
}

const token = (v: string) => `{{ ${v} }}`

async function markMessage(m: Message, status: string) {
  await api.patch(`/admin/contact-messages/${m.id}`, { status })
  await refreshMessages()
}
</script>

<template>
  <div>
    <h1 class="text-2xl font-extrabold sm:text-3xl">Content</h1>
    <UiTabs v-model="tab" class="mt-6" :tabs="[{ value: 'faqs', label: 'FAQ' }, { value: 'pages', label: 'Legal & SEO pages' }, { value: 'emails', label: 'Email templates' }, { value: 'messages', label: 'Contact messages' }]" />

    <template v-if="tab === 'faqs'">
      <div class="mt-4 flex justify-end"><UiButton size="sm" @click="faq = { question: '', answer: '', category: 'basics', sort_order: faqs.length, is_published: true }"><Plus class="size-4" /> Add question</UiButton></div>
      <div class="card mt-3 divide-y divide-line">
        <div v-for="f in faqs" :key="f.id" class="flex items-start justify-between gap-4 p-4">
          <div><p class="font-semibold">{{ f.question }}</p><p class="mt-1 line-clamp-2 text-sm text-muted">{{ f.answer }}</p><p class="mt-1 text-xs text-muted">{{ f.category }} · order {{ f.sort_order }} <span v-if="!f.is_published" class="text-warning">· hidden</span></p></div>
          <div class="flex shrink-0 gap-2"><UiButton size="sm" variant="ghost" @click="faq = { ...f }">Edit</UiButton><UiButton size="sm" variant="ghost" class="!text-danger" @click="deleteFaq(f)">Delete</UiButton></div>
        </div>
      </div>
    </template>

    <template v-else-if="tab === 'pages'">
      <div class="mt-4 flex justify-end"><UiButton size="sm" @click="page = { slug: '', type: 'legal', title: '', body: '## Heading\n\nText', meta_title: null, meta_description: null, is_published: true }"><Plus class="size-4" /> New page</UiButton></div>
      <div class="card mt-3 divide-y divide-line">
        <div v-for="p in pages" :key="p.id" class="flex items-center justify-between gap-4 p-4">
          <div><p class="font-semibold">{{ p.title }} <UiBadge>{{ p.type }}</UiBadge></p><p class="text-xs text-muted">/legal/{{ p.slug }} · updated {{ formatRelative(p.updated_at) }}<span v-if="!p.is_published" class="text-warning"> · unpublished</span></p></div>
          <UiButton size="sm" variant="ghost" @click="page = { ...p }">Edit</UiButton>
        </div>
      </div>
    </template>

    <template v-else-if="tab === 'emails'">
      <div class="card mt-4 divide-y divide-line">
        <div v-for="t in templates" :key="t.id" class="flex items-center justify-between gap-4 p-4">
          <div><p class="font-semibold">{{ t.name }}</p><p class="text-xs text-muted">{{ t.subject }}</p></div>
          <UiButton size="sm" variant="ghost" @click="tpl = { ...t }">Edit</UiButton>
        </div>
      </div>
    </template>

    <template v-else>
      <div class="mt-4 space-y-3">
        <div v-for="m in messages?.data" :key="m.id" class="card p-5">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div><p class="font-semibold">{{ m.name }} <span class="font-normal text-muted">&lt;{{ m.email }}&gt;</span></p><p class="text-xs text-muted">{{ m.topic }} · {{ formatRelative(m.created_at) }}</p></div>
            <div class="flex items-center gap-2"><DashboardStatusBadge :status="m.status === 'new' ? 'received' : m.status === 'replied' ? 'resolved' : 'dismissed'" />
              <select :value="m.status" class="field-input !w-auto !py-1 text-xs" aria-label="Message status" @change="markMessage(m, ($event.target as HTMLSelectElement).value)"><option value="new">New</option><option value="read">Read</option><option value="replied">Replied</option><option value="spam">Spam</option></select>
            </div>
          </div>
          <p class="mt-3 whitespace-pre-line text-sm">{{ m.message }}</p>
          <a :href="`mailto:${m.email}`" class="mt-3 inline-block text-sm font-semibold text-brand-700 hover:underline">Reply by email</a>
        </div>
        <UiEmpty v-if="messages && !messages.data.length" title="No messages" />
      </div>
    </template>

    <UiModal :open="!!faq" :title="faq?.id ? 'Edit question' : 'New question'" size="lg" @update:open="v => { if (!v) faq = null }">
      <form v-if="faq" id="faq-form" class="space-y-4" @submit.prevent="saveFaq">
        <UiField label="Question" id="fq-q"><input id="fq-q" v-model="faq.question" class="field-input" maxlength="255" required></UiField>
        <UiField label="Answer" id="fq-a"><textarea id="fq-a" v-model="faq.answer" rows="5" class="field-input" maxlength="5000" required /></UiField>
        <div class="grid gap-4 sm:grid-cols-3">
          <UiField label="Category" id="fq-c"><input id="fq-c" v-model="faq.category" class="field-input" maxlength="60"></UiField>
          <UiField label="Order" id="fq-o"><input id="fq-o" v-model.number="faq.sort_order" type="number" min="0" class="field-input"></UiField>
          <div class="pt-7"><UiSwitch v-model="faq.is_published" label="Published" /></div>
        </div>
      </form>
      <template #footer><UiButton variant="secondary" @click="faq = null">Cancel</UiButton><UiButton type="submit" form="faq-form">Save</UiButton></template>
    </UiModal>

    <UiModal :open="!!page" :title="page?.id ? `Edit ${page.title}` : 'New page'" size="lg" @update:open="v => { if (!v) page = null }">
      <form v-if="page" id="page-form" class="space-y-4" @submit.prevent="savePage">
        <div class="grid gap-4 sm:grid-cols-3">
          <UiField label="Title" id="pg-t" class="sm:col-span-2"><input id="pg-t" v-model="pf.title" class="field-input" required maxlength="200"></UiField>
          <UiField label="Slug" id="pg-s"><input id="pg-s" v-model="pf.slug" class="field-input font-mono" required maxlength="100"></UiField>
        </div>
        <UiField label="Body (Markdown)" id="pg-b" hint="Supports ## headings, - lists, **bold** and [links](https://example.com)."><textarea id="pg-b" v-model="pf.body" rows="10" class="field-input font-mono text-xs" required /></UiField>
        <details class="rounded-xl bg-canvas p-3 text-sm"><summary class="cursor-pointer font-semibold">Preview</summary><div class="prose-content mt-3" v-html="preview" /></details>
        <div class="grid gap-4 sm:grid-cols-2">
          <UiField label="SEO title" id="pg-mt"><input id="pg-mt" v-model="pf.meta_title" class="field-input" maxlength="200"></UiField>
          <UiField label="SEO description" id="pg-md"><input id="pg-md" v-model="pf.meta_description" class="field-input" maxlength="320"></UiField>
        </div>
        <UiSwitch v-model="pf.is_published" label="Published" />
      </form>
      <template #footer><UiButton variant="secondary" @click="page = null">Cancel</UiButton><UiButton type="submit" form="page-form">Save page</UiButton></template>
    </UiModal>

    <UiModal :open="!!tpl" :title="tpl ? `Edit “${tpl.name}”` : ''" size="lg" @update:open="v => { if (!v) tpl = null }">
      <form v-if="tpl" id="tpl-form" class="space-y-4" @submit.prevent="saveTemplate">
        <p class="text-xs text-muted">Available variables: <code v-for="v in tpl.variables ?? []" :key="v" class="mr-1 rounded bg-canvas px-1.5 py-0.5">{{ token(v) }}</code>. Paragraphs are separated by blank lines; the action button is added automatically.</p>
        <UiField label="Subject" id="tp-s"><input id="tp-s" v-model="tpl.subject" class="field-input" maxlength="200" required></UiField>
        <UiField label="Body" id="tp-b"><textarea id="tp-b" v-model="tpl.body" rows="8" class="field-input" required /></UiField>
        <UiSwitch v-model="tpl.is_active" label="Use this template" description="When off, the built-in default text is sent." />
      </form>
      <template #footer><UiButton variant="secondary" @click="tpl = null">Cancel</UiButton><UiButton type="submit" form="tpl-form">Save template</UiButton></template>
    </UiModal>
  </div>
</template>
