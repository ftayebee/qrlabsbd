<script setup lang="ts">
import { Check, Minus } from 'lucide-vue-next'
import type { Plan, PlanPrice } from '~/types/api'
import { formatMoney } from '~/utils/format'

const { data: plans, status, refresh } = usePlans()
const { user, fetchUser } = useAuth()
const api = useApi()
const toast = useToast()
const route = useRoute()

const interval = ref<'month' | 'year'>('month')
const currencies = computed(() => [...new Set(plans.value.flatMap(p => p.prices.map(pr => pr.currency)))])
const currency = ref<string>('')
watchEffect(() => { if (!currency.value || !currencies.value.includes(currency.value)) currency.value = currencies.value[0] ?? 'USD' })

function priceFor(plan: Plan): PlanPrice | undefined {
  return plan.prices.find(p => p.interval === interval.value && p.currency === currency.value)
}
const yearlySaving = computed(() => {
  const savings = plans.value.map((p) => {
    const m = p.prices.find(x => x.interval === 'month' && x.currency === currency.value)
    const y = p.prices.find(x => x.interval === 'year' && x.currency === currency.value)
    return m && y && m.amount > 0 ? Math.round((1 - y.amount / (m.amount * 12)) * 100) : 0
  })
  return Math.max(0, ...savings)
})

const loadingPlan = ref<number | null>(null)
async function choose(plan: Plan) {
  if (plan.isCustom) return navigateTo({ path: '/contact', query: { topic: 'sales', plan: plan.slug } })
  const price = priceFor(plan)
  if (!price || price.effectiveAmount === 0) return navigateTo(user.value ? '/dashboard' : '/register')
  if (!(await fetchUser())) return navigateTo({ path: '/register', query: { redirect: `/pricing?plan=${plan.slug}&interval=${interval.value}` } })

  loadingPlan.value = plan.id
  try {
    const res = await api.post<{ data: { url: string } }>('/billing/checkout', { price_id: price.id })
    window.location.assign(res.data.url)
  } catch (e) {
    const err = e as { status?: number; message: string }
    if (err.status === 422 && /already have/i.test(err.message)) {
      toast.info('You already have a subscription. Change plans from Billing.')
      await navigateTo('/dashboard/billing')
    } else toast.error(err.message)
  } finally { loadingPlan.value = null }
}

onMounted(() => {
  if (route.query.interval === 'year') interval.value = 'year'
  if (route.query.checkout === 'cancelled') toast.info('Checkout cancelled. You have not been charged.')
})

const featureKeys = computed(() => {
  const seen = new Map<string, string>()
  plans.value.forEach(p => p.features.forEach(f => { if (!seen.has(f.key)) seen.set(f.key, f.label) }))
  return [...seen.entries()]
})
function featureValue(plan: Plan, key: string) {
  const f = plan.features.find(x => x.key === key)
  if (!f) return null
  if (f.type === 'boolean') return f.value === 'true'
  if (f.type === 'limit') return Number(f.value) < 0 ? 'Unlimited' : Number(f.value).toLocaleString()
  return f.value
}
defineProps<{ showTable?: boolean }>()
</script>

<template>
  <div>
    <div class="flex flex-col items-center gap-3 sm:flex-row sm:justify-center">
      <UiTabs v-model="interval" :tabs="[{ value: 'month', label: 'Monthly' }, { value: 'year', label: 'Yearly' }]" />
      <UiBadge v-if="yearlySaving > 0" tone="green">Save up to {{ yearlySaving }}% yearly</UiBadge>
      <select v-if="currencies.length > 1" v-model="currency" class="field-input !w-auto !py-2" aria-label="Currency">
        <option v-for="c in currencies" :key="c" :value="c">{{ c }}</option>
      </select>
    </div>

    <UiSpinner v-if="status === 'pending' && !plans.length" label="Loading plans…" />
    <UiAlert v-else-if="status === 'error'" tone="danger" class="mx-auto mt-10 max-w-md">
      Plans could not be loaded. <button type="button" class="font-semibold underline" @click="refresh()">Try again</button>
    </UiAlert>

    <div v-else class="mt-10 grid gap-5 md:grid-cols-2 xl:grid-cols-4">
      <AppReveal v-for="(plan, i) in plans" :key="plan.id" :delay="i * 0.06">
        <div :class="['relative flex h-full flex-col rounded-[var(--radius-card)] border p-6 transition-shadow', plan.highlight ? 'border-charcoal bg-charcoal text-white shadow-[var(--shadow-lift)]' : 'border-line bg-white shadow-[var(--shadow-card)]']">
          <UiBadge v-if="plan.highlight" tone="green" class="absolute -top-3 left-6">Most popular</UiBadge>
          <h3 :class="['text-lg font-bold', plan.highlight ? '!text-white' : '']">{{ plan.name }}</h3>
          <p :class="['mt-1 min-h-10 text-sm', plan.highlight ? 'text-white/65' : 'text-muted']">{{ plan.description }}</p>

          <div class="mt-6 min-h-16">
            <template v-if="plan.isCustom">
              <p class="font-display text-3xl font-extrabold">Custom</p>
              <p :class="['text-xs', plan.highlight ? 'text-white/60' : 'text-muted']">Tailored limits and invoicing</p>
            </template>
            <template v-else-if="priceFor(plan)">
              <div class="flex items-baseline gap-2">
                <AnimatePresence mode="wait">
                  <Motion :key="`${interval}-${currency}`" as="p" class="font-display text-4xl font-extrabold tracking-tight" :initial="{ opacity: 0, y: 6 }" :animate="{ opacity: 1, y: 0 }" :exit="{ opacity: 0, y: -6 }" :transition="{ duration: 0.18 }">
                    {{ formatMoney(interval === 'year' ? Math.round(priceFor(plan)!.effectiveAmount / 12) : priceFor(plan)!.effectiveAmount, currency, { compact: true }) }}
                  </Motion>
                </AnimatePresence>
                <span v-if="priceFor(plan)!.promoAmount !== null" :class="['text-sm line-through', plan.highlight ? 'text-white/45' : 'text-muted']">{{ formatMoney(interval === 'year' ? Math.round(priceFor(plan)!.amount / 12) : priceFor(plan)!.amount, currency, { compact: true }) }}</span>
              </div>
              <p :class="['text-xs', plan.highlight ? 'text-white/60' : 'text-muted']">
                per month{{ interval === 'year' ? `, ${formatMoney(priceFor(plan)!.effectiveAmount, currency)} billed yearly` : '' }}
              </p>
            </template>
            <template v-else>
              <p class="font-display text-4xl font-extrabold">{{ formatMoney(0, currency, { compact: true }) }}</p>
              <p :class="['text-xs', plan.highlight ? 'text-white/60' : 'text-muted']">Free forever</p>
            </template>
          </div>

          <UiButton class="mt-6" block :variant="plan.highlight ? 'primary' : 'secondary'" :loading="loadingPlan === plan.id" @click="choose(plan)">
            {{ plan.ctaLabel ?? (plan.isCustom ? 'Contact sales' : 'Get started') }}
          </UiButton>
          <p v-if="plan.trialDays > 0 && priceFor(plan)" :class="['mt-2 text-center text-xs', plan.highlight ? 'text-white/55' : 'text-muted']">{{ plan.trialDays }}-day free trial for new subscribers</p>

          <ul class="mt-6 space-y-3 border-t pt-6 text-sm" :class="plan.highlight ? 'border-white/10' : 'border-line'">
            <li v-for="f in plan.features.filter(f => f.highlighted)" :key="f.key" class="flex gap-2.5">
              <Check class="mt-0.5 size-4 shrink-0 text-brand" aria-hidden="true" />
              <span><strong v-if="f.type === 'limit'">{{ Number(f.value) < 0 ? 'Unlimited' : Number(f.value).toLocaleString() }}</strong> {{ f.type === 'limit' ? f.label.toLowerCase() : f.type === 'text' ? `${f.label}: ${f.value}` : f.label }}</span>
            </li>
          </ul>
        </div>
      </AppReveal>
    </div>

    <AppReveal v-if="showTable && plans.length" class="mt-16 overflow-x-auto">
      <table class="table-base min-w-[720px] rounded-[var(--radius-card)] bg-white">
        <caption class="sr-only">Plan comparison</caption>
        <thead>
          <tr>
            <th scope="col">Feature</th>
            <th v-for="p in plans" :key="p.id" scope="col" class="text-center">{{ p.name }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="[key, label] in featureKeys" :key="key">
            <th scope="row" class="!text-sm !font-medium !normal-case !tracking-normal !text-ink">{{ label }}</th>
            <td v-for="p in plans" :key="p.id" class="text-center">
              <template v-if="featureValue(p, key) === true"><Check class="mx-auto size-4 text-brand" aria-label="Included" /></template>
              <template v-else-if="featureValue(p, key) === false || featureValue(p, key) === null"><Minus class="mx-auto size-4 text-line-strong" aria-label="Not included" /></template>
              <template v-else>{{ featureValue(p, key) }}</template>
            </td>
          </tr>
        </tbody>
      </table>
    </AppReveal>
  </div>
</template>
