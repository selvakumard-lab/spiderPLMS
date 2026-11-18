import axios, { AxiosError } from 'axios'

const TOKEN_KEY = 'plms.token'
const TENANT_KEY = 'plms.tenant'

function safe<T>(fn: () => T): T | null {
  try {
    return fn()
  } catch {
    return null
  }
}

/*
 * The sign-in lives in sessionStorage, which is private to each browser tab. That lets one Chrome window hold an
 * admin in one tab and a client in another without the two overwriting each other. (localStorage is shared by every
 * tab, so a second sign-in would silently replace the first.) The cost: closing a tab signs that tab out.
 */
safe(() => {
  // Older builds kept the sign-in in localStorage; drop it so it cannot leak into new tabs.
  localStorage.removeItem(TOKEN_KEY)
  localStorage.removeItem(TENANT_KEY)
})

export const tokenStore = {
  get: (): string | null => safe(() => sessionStorage.getItem(TOKEN_KEY)),
  set: (token: string) => safe(() => sessionStorage.setItem(TOKEN_KEY, token)),
  clear: () => safe(() => sessionStorage.removeItem(TOKEN_KEY)),
}

/** Super admins can "enter" a tenant; the slug is sent as X-Tenant on workspace calls. */
export const tenantStore = {
  get: (): string | null => safe(() => sessionStorage.getItem(TENANT_KEY)),
  set: (slug: string | null) =>
    safe(() => (slug ? sessionStorage.setItem(TENANT_KEY, slug) : sessionStorage.removeItem(TENANT_KEY))),
}

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? '/api/v1',
  headers: { Accept: 'application/json' },
  timeout: 30000,
})

api.interceptors.request.use((config) => {
  const token = tokenStore.get()
  const tenant = tenantStore.get()
  if (token) config.headers.Authorization = `Bearer ${token}`
  // Platform (admin) endpoints never run inside a workspace.
  if (tenant && !config.url?.startsWith('/admin')) config.headers['X-Tenant'] = tenant
  return config
})

api.interceptors.response.use(
  (response) => response,
  (error: AxiosError) => {
    if (error.response?.status === 401) {
      tokenStore.clear()
      window.dispatchEvent(new Event('plms:unauthorized'))
    }
    return Promise.reject(error)
  },
)

export interface ApiErrorBody {
  message?: string
  errors?: Record<string, string[]>
  code?: string
}

/** Human-readable message from any API error. */
export function errorMessage(error: unknown, fallback = 'Something went wrong. Please try again.'): string {
  if (axios.isAxiosError<ApiErrorBody>(error)) {
    if (!error.response) return 'Cannot reach the server. Check your connection.'
    return error.response.data?.message ?? fallback
  }
  return fallback
}

/** Field → first validation message, for inline form errors. */
export function fieldErrors(error: unknown): Record<string, string> {
  if (axios.isAxiosError<ApiErrorBody>(error) && error.response?.status === 422) {
    const errors = error.response.data?.errors ?? {}
    return Object.fromEntries(Object.entries(errors).map(([key, messages]) => [key, messages[0]]))
  }
  return {}
}
