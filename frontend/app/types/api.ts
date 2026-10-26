export interface ApiErrorBody {
  message: string
  code?: string
  errors?: Record<string, string[]>
}

export interface Paginated<T> {
  data: T[]
  meta: { current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null }
}

export interface Item<T> { data: T }

export interface User {
  id: number
  name: string
  email: string
  emailVerified: boolean
  isAdmin: boolean
  roles: string[]
  permissions: string[]
  createdAt: string
}

export interface Entitlements {
  [key: string]: number | boolean | string
}

export interface UsageSummary {
  plan: { id: number; name: string; slug: string }
  subscription: Subscription | null
  entitlements: Entitlements
  usage: Record<string, number>
  readOnly: boolean
  inGrace: boolean
  graceEndsAt: string | null
}

export interface PlanFeature { key: string; label: string; value: string; type: 'boolean' | 'limit' | 'text'; highlighted: boolean }
export interface PlanPrice {
  id: number
  interval: 'month' | 'year'
  currency: string
  amount: number
  promoAmount: number | null
  promoEndsAt: string | null
  effectiveAmount: number
}
export interface Plan {
  id: number
  slug: string
  name: string
  description: string | null
  isCustom: boolean
  highlight: boolean
  trialDays: number
  sortOrder: number
  ctaLabel: string | null
  prices: PlanPrice[]
  features: PlanFeature[]
}

export interface Subscription {
  id: number
  status: 'trialing' | 'active' | 'past_due' | 'canceled' | 'expired' | 'incomplete'
  plan: { id: number; name: string; slug: string }
  price: PlanPrice | null
  provider: string
  isManual?: boolean
  currentPeriodEnd: string | null
  trialEndsAt: string | null
  cancelAtPeriodEnd: boolean
  canceledAt: string | null
  endsAt: string | null
  graceEndsAt: string | null
  createdAt: string
}

export interface Invoice {
  id: number
  number: string
  status: string
  total: number
  currency: string
  issuedAt: string | null
  paidAt: string | null
  hostedUrl: string | null
  pdfUrl: string | null
}

export interface Campaign { id: number; name: string; description: string | null; color: string | null; qrCount: number; totalScans: number; createdAt: string }
export interface Folder { id: number; name: string; qrCount: number }

export interface FaqItem { id: number; question: string; answer: string; category: string | null }

export interface AnalyticsBreakdownRow { value: string; scans: number; uniqueScans: number }
export interface AnalyticsReport {
  range: { from: string; to: string }
  totals: { scans: number; uniqueScans: number; botScansExcluded: number }
  series: { date: string; scans: number; uniqueScans: number }[]
  devices: AnalyticsBreakdownRow[]
  os: AnalyticsBreakdownRow[]
  browsers: AnalyticsBreakdownRow[]
  countries: AnalyticsBreakdownRow[]
  referrers: AnalyticsBreakdownRow[]
}
