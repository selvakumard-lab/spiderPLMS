export type RoleName = 'org-admin' | 'project-manager' | 'team-leader' | 'developer' | 'client'
export type TenantStatus = 'trial' | 'active' | 'past_due' | 'suspended' | 'cancelled'

export interface Plan {
  id: number
  code: string
  name: string
  description: string | null
  price_monthly: number
  price_yearly: number
  currency: string
  max_users: number | null
  max_projects: number | null
  storage_gb: number
  features: string[]
  is_active?: boolean
  tenants_count?: number
  prices?: PlanCountryPrice[]
}

export interface PlanCountryPrice {
  country_code: string
  currency_code: string
  price_monthly: number
  price_yearly: number
}

export interface Country {
  id?: number
  code: string
  name: string
  currency_code: string
  dial_code: string | null
  timezone?: string | null
  is_active?: boolean
  is_default?: boolean
  tenants_count?: number
}

export interface Currency {
  id?: number
  code: string
  name: string
  symbol: string
  rate: number
  decimals: number
  is_active?: boolean
  is_default?: boolean
  countries_count?: number
}

export interface PublicConfig {
  app: {
    name: string
    tagline: string
    support_email: string
    default_theme: 'light' | 'dark' | 'system'
    registration_open: boolean
    trial_days: number
    maintenance_message: string
    default_country: string
    default_currency: string
  }
  countries: Country[]
  currencies: Currency[]
}

export interface Pricing {
  requested: string | null
  is_fallback: boolean
  country: { code: string; name: string } | null
  currency: { code: string; symbol: string; decimals: number } | null
  plans: Plan[]
}

export interface Tenant {
  id: number
  uuid: string
  name: string
  slug: string
  custom_domain: string | null
  status: TenantStatus
  trial_ends_at: string | null
  timezone: string
  currency: string
  country_code: string | null
  contact_email: string | null
  phone: string | null
  address: string | null
  tax_id: string | null
  settings: Record<string, unknown>
  admin_notes?: string | null
  plan?: Plan | null
  users_count?: number
  projects_count?: number
  created_at: string
}

export interface User {
  id: number
  name: string
  email: string
  job_title: string | null
  phone: string | null
  status: 'active' | 'invited' | 'disabled'
  is_super_admin: boolean
  /** A client portal account: sees only its own projects. */
  is_client?: boolean
  roles?: RoleName[]
  last_login_at: string | null
  created_at: string
}

export interface Paginated<T> {
  data: T[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
  links: { next: string | null; prev: string | null }
}

export interface Session {
  token: string
  user: User
  tenant: Tenant | null
  permissions: string[]
}

export interface PlatformStats {
  totals: { tenants: number; active: number; trial: number; suspended: number; users: number; projects: number; mrr_usd_cents: number }
  by_country: { code: string | null; name: string; tenants: number }[]
  by_plan: { code: string; name: string; tenants: number }[]
  new_tenants: { month: string; tenants: number }[]
}

export interface TenantDetail {
  data: Tenant
  stats: { users: number; projects: number; last_activity_at: string | null }
  users: User[]
  activity: { id: number; event: string; user: string | null; created_at: string }[]
}

