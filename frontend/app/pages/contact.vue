<script setup lang="ts">
import { ApiError } from '~/composables/useApi'

const crumbs = [{ label: 'Contact' }]
usePageSeo({ title: 'Contact Us', description: 'Talk to our team about sales, support, billing or privacy.', path: '/contact', crumbs })

const route = useRoute()
const api = useApi()
const form = reactive({
  name: '', email: '',
  topic: (['sales', 'support', 'billing', 'privacy', 'other'].includes(String(route.query.topic)) ? String(route.query.topic) : 'support'),
  message: route.query.plan ? `I'm interested in the ${route.query.plan} plan.` : '',
  website: '',
})
const errors = ref<Record<string, string>>({})
const sending = ref(false)
const sent = ref('')

async function submit() {
  sending.value = true
  errors.value = {}
  try {
    sent.value = (await api.post<{ message: string }>('/contact', form)).message
  } catch (e) {
    if (e instanceof ApiError) errors.value = e.fieldErrors
    useToast().error((e as Error).message)
  } finally { sending.value = false }
}
</script>

<template>
  <div>
    <MarketingPageHero eyebrow="Contact" title="Talk to a human" lead="We reply within one business day. For account issues, sign in first so we can find your details faster." :crumbs="crumbs" />
    <div class="container-page pb-24">
      <div class="card mx-auto max-w-2xl p-6 sm:p-8">
        <UiAlert v-if="sent" tone="success" title="Message sent">{{ sent }}</UiAlert>
        <form v-else class="grid gap-5 sm:grid-cols-2" novalidate @submit.prevent="submit">
          <UiField label="Name" id="c-name" :error="errors.name" required>
            <input id="c-name" v-model="form.name" class="field-input" autocomplete="name" required maxlength="120">
          </UiField>
          <UiField label="Email" id="c-email" :error="errors.email" required>
            <input id="c-email" v-model="form.email" type="email" class="field-input" autocomplete="email" required>
          </UiField>
          <UiField label="Topic" id="c-topic" class="sm:col-span-2">
            <select id="c-topic" v-model="form.topic" class="field-input">
              <option value="sales">Sales &amp; Enterprise</option>
              <option value="support">Product support</option>
              <option value="billing">Billing</option>
              <option value="privacy">Privacy &amp; data requests</option>
              <option value="other">Something else</option>
            </select>
          </UiField>
          <UiField label="Message" id="c-message" :error="errors.message" class="sm:col-span-2" required>
            <textarea id="c-message" v-model="form.message" rows="6" class="field-input" maxlength="4000" required />
          </UiField>
          <!-- Honeypot field: hidden from people, filled by bots. -->
          <div class="hidden" aria-hidden="true"><label>Website <input v-model="form.website" tabindex="-1" autocomplete="off"></label></div>
          <div class="sm:col-span-2"><UiButton type="submit" :loading="sending">Send message</UiButton></div>
        </form>
      </div>
    </div>
  </div>
</template>
