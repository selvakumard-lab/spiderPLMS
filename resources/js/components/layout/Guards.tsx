import type { ReactNode } from 'react'
import { Navigate, useLocation } from 'react-router'
import { useAuth } from '@/context/AuthContext'
import { Button, Spinner } from '@/components/ui'

function FullPageLoader() {
  return (
    <div className="flex min-h-dvh items-center justify-center bg-canvas">
      <Spinner className="size-8" />
    </div>
  )
}

/** Shown when the server cannot be reached on page load. The user stays signed in and can retry. */
function ServerUnavailable({ onRetry }: { onRetry: () => void }) {
  return (
    <div className="flex min-h-dvh flex-col items-center justify-center gap-4 bg-canvas px-6 text-center" role="alert">
      <h1 className="text-xl font-bold text-ink-900">We could not reach the server</h1>
      <p className="max-w-sm text-sm text-ink-500">You are still signed in. Check your connection and try again.</p>
      <Button onClick={onRetry}>Try again</Button>
    </div>
  )
}

/** Only signed-in users; remembers where they were going. */
export function RequireAuth({ children }: { children: ReactNode }) {
  const { status, retrySession } = useAuth()
  const location = useLocation()
  if (status === 'loading') return <FullPageLoader />
  if (status === 'unavailable') return <ServerUnavailable onRetry={retrySession} />
  if (status === 'guest') return <Navigate to="/login" replace state={{ from: location.pathname }} />
  return children
}

/** Only guests (login / register); signed-in users go to their home screen. */
export function GuestOnly({ children }: { children: ReactNode }) {
  const { status, platformMode, user } = useAuth()
  if (status === 'loading') return <FullPageLoader />
  if (status === 'authenticated') return <Navigate to={platformMode ? '/app/admin' : user?.is_client ? '/app/portal' : '/app'} replace />
  return children
}

/** Workspace screens need a tenant: a Super Admin who has not entered one is sent to the platform area. */
export function RequireWorkspace({ children }: { children: ReactNode }) {
  const { platformMode, user } = useAuth()
  if (user?.is_client) return <Navigate to="/app/portal" replace />
  return platformMode ? <Navigate to="/app/admin" replace /> : children
}

/** The client portal is only for client accounts. */
export function RequireClient({ children }: { children: ReactNode }) {
  const { user } = useAuth()
  return user?.is_client ? children : <Navigate to="/app" replace />
}

/** Platform screens are for Super Admins only. */
export function RequireSuperAdmin({ children }: { children: ReactNode }) {
  const { user } = useAuth()
  return user?.is_super_admin ? children : <Navigate to="/app" replace />
}

/** Hide a route from users who lack a permission. */
export function RequirePermission({ permission, children }: { permission: string; children: ReactNode }) {
  const { can } = useAuth()
  return can(permission) ? children : <Navigate to="/app" replace />
}
