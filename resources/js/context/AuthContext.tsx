import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { tenantStore, tokenStore } from '@/lib/api'
import { authApi } from '@/lib/endpoints'
import { formatMoney } from '@/lib/utils'
import { useConfig } from '@/context/ConfigContext'
import type { Session, Tenant, User } from '@/types'

interface AuthState {
  user: User | null
  tenant: Tenant | null
  permissions: string[]
  /** `unavailable`: a token exists but the server could not be reached; the user stays signed in and can retry. */
  status: 'loading' | 'authenticated' | 'guest' | 'unavailable'
  retrySession: () => void
  /** A Super Admin who has not entered a workspace sees only the Platform area. */
  platformMode: boolean
  /** Store a session returned by OTP verification, admin login or registration. */
  signIn: (session: Session) => void
  register: (payment: Parameters<typeof authApi.registerVerify>[0]) => Promise<void>
  logout: () => Promise<void>
  can: (permission: string) => boolean
  /** Super Admin: open a tenant's workspace (sends X-Tenant on workspace requests). */
  enterTenant: (slug: string) => Promise<void>
  exitTenant: () => Promise<void>
  refresh: () => Promise<void>
  setUser: (user: User) => void
  /** Currency of the current workspace (falls back to the platform default). */
  currency: string
  /** Format an amount in the workspace currency. */
  money: (amount: number | string | null | undefined, opts?: { currency?: string; minor?: boolean }) => string
}

const AuthContext = createContext<AuthState | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient()
  const { config } = useConfig()
  const [session, setSession] = useState<Omit<Session, 'token'> | null>(null)
  const [status, setStatus] = useState<AuthState['status']>(tokenStore.get() ? 'loading' : 'guest')
  const [restoreAttempt, setRestoreAttempt] = useState(0)

  const applySession = useCallback((data: Omit<Session, 'token'> & { token?: string }) => {
    if (data.token) tokenStore.set(data.token)
    setSession({ user: data.user, tenant: data.tenant, permissions: data.permissions })
    setStatus('authenticated')
  }, [])

  const clear = useCallback(() => {
    tokenStore.clear()
    tenantStore.set(null)
    setSession(null)
    setStatus('guest')
    queryClient.clear()
  }, [queryClient])

  const refresh = useCallback(async () => applySession(await authApi.me()), [applySession])

  // Restore the session on page load. Only a definite answer from the server (401, 402, 403 ...) signs the user out;
  // a network error, a server error or rate limiting is retried and never costs the user their login.
  useEffect(() => {
    if (!tokenStore.get()) return
    let cancelled = false

    async function restore() {
      for (let attempt = 0; attempt < 3; attempt++) {
        try {
          const data = await authApi.me()
          if (!cancelled) applySession(data)
          return
        } catch (err) {
          const transient = axios.isAxiosError(err) && (!err.response || err.response.status >= 500 || err.response.status === 429)
          if (!transient) {
            if (!cancelled) clear()
            return
          }
          await new Promise((resolve) => setTimeout(resolve, 700 * (attempt + 1)))
          if (cancelled) return
        }
      }
      if (!cancelled) setStatus('unavailable')
    }

    void restore()
    return () => {
      cancelled = true
    }
  }, [applySession, clear, restoreAttempt])

  const retrySession = useCallback(() => {
    setStatus('loading')
    setRestoreAttempt((n) => n + 1)
  }, [])

  useEffect(() => {
    const onUnauthorized = () => clear()
    window.addEventListener('plms:unauthorized', onUnauthorized)
    return () => window.removeEventListener('plms:unauthorized', onUnauthorized)
  }, [clear])

  const value = useMemo<AuthState>(() => {
    const tenant = session?.tenant ?? null
    const currency = tenant?.currency ?? config.app.default_currency
    return {
      user: session?.user ?? null,
      tenant,
      permissions: session?.permissions ?? [],
      status,
      retrySession,
      platformMode: !!session?.user.is_super_admin && !tenant,
      signIn: (data) => {
        tenantStore.set(null)
        applySession(data)
      },
      register: async (payment) => {
        tenantStore.set(null)
        applySession(await authApi.registerVerify(payment))
      },
      logout: async () => {
        await authApi.logout().catch(() => undefined)
        clear()
      },
      can: (permission) => {
        const perms = session?.permissions ?? []
        return perms.includes('*') || perms.includes(permission)
      },
      enterTenant: async (slug) => {
        tenantStore.set(slug)
        queryClient.clear()
        await refresh()
      },
      exitTenant: async () => {
        tenantStore.set(null)
        queryClient.clear()
        await refresh()
      },
      refresh,
      setUser: (user) => setSession((s) => (s ? { ...s, user } : s)),
      currency,
      money: (amount, opts) => formatMoney(amount, opts?.currency ?? currency, opts?.minor ?? false),
    }
  }, [session, status, applySession, clear, refresh, queryClient, config.app.default_currency])

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

// eslint-disable-next-line react-refresh/only-export-components
export function useAuth(): AuthState {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used inside <AuthProvider>')
  return ctx
}
