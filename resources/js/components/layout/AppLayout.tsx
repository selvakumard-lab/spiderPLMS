import { useEffect, useRef, useState } from 'react'
import { Link, NavLink, Outlet, useNavigate } from 'react-router'
import { ChevronDown, Eye, Lock, LogOut, Megaphone, Menu, Settings2, UserRound, X } from 'lucide-react'
import { useAuth } from '@/context/AuthContext'
import { useConfig } from '@/context/ConfigContext'
import { ROLE_LABELS } from '@/lib/constants'
import { cn } from '@/lib/utils'
import { Avatar } from '@/components/ui'
import { ThemeToggle } from '@/components/ui/extra'
import { Logo } from '@/components/Logo'
import { NotificationBell } from '@/components/NotificationBell'
import { NAV_SECTIONS } from './navigation'

export function AppLayout() {
  const [drawerOpen, setDrawerOpen] = useState(false)
  const { config } = useConfig()
  const { user, tenant, exitTenant } = useAuth()
  const navigate = useNavigate()

  return (
    <div className="min-h-dvh bg-canvas">
      {/* Desktop sidebar */}
      <aside className="no-print fixed inset-y-0 left-0 z-30 hidden w-[264px] border-r border-line bg-surface lg:flex">
        <Sidebar />
      </aside>

      {/* Mobile / tablet drawer */}
      {drawerOpen && (
        <div className="no-print fixed inset-0 z-40 lg:hidden" role="dialog" aria-modal="true">
          <div className="absolute inset-0 animate-fade-in bg-black/50" onClick={() => setDrawerOpen(false)} />
          <aside className="relative flex h-full w-[280px] max-w-[85vw] animate-slide-in bg-surface shadow-pop">
            <button onClick={() => setDrawerOpen(false)} className="absolute top-4 right-3 rounded-lg p-2 text-ink-400 hover:bg-slate-100" aria-label="Close menu">
              <X className="size-5" />
            </button>
            <Sidebar onNavigate={() => setDrawerOpen(false)} />
          </aside>
        </div>
      )}

      <div className="lg:pl-[264px] print:pl-0">
        {config.app.maintenance_message && (
          <div className="no-print flex items-center gap-2.5 bg-amber-50 px-4 py-2.5 text-[13px] font-semibold text-amber-800 sm:px-6 lg:px-8" role="status">
            <Megaphone className="size-4 shrink-0" /> {config.app.maintenance_message}
          </div>
        )}
        {user?.is_super_admin && tenant && (
          <div className="no-print flex flex-wrap items-center gap-x-3 gap-y-1 bg-brand-600 px-4 py-2 text-[13px] font-semibold text-white sm:px-6 lg:px-8">
            <Eye className="size-4 shrink-0" />
            <span className="min-w-0 flex-1">Viewing <b>{tenant.name}</b> as Super Admin</span>
            <button
              onClick={async () => {
                await exitTenant()
                navigate('/app/admin/tenants')
              }}
              className="rounded-lg bg-white/15 px-3 py-1 hover:bg-white/25"
            >
              Exit workspace
            </button>
          </div>
        )}
        <Topbar onMenu={() => setDrawerOpen(true)} />
        <main className="mx-auto w-full max-w-[1400px] px-4 pt-6 pb-16 sm:px-6 lg:px-8 lg:pt-8">
          <Outlet />
        </main>
      </div>
    </div>
  )
}

function Sidebar({ onNavigate }: { onNavigate?: () => void }) {
  const { user, tenant, can, platformMode } = useAuth()

  return (
    <div className="flex h-full w-full flex-col">
      <div className="flex h-16 shrink-0 items-center px-5">
        <Logo />
      </div>

      {tenant ? (
        <div className="mx-4 mb-2 flex items-center gap-3 rounded-xl border border-line bg-slate-50/70 px-3 py-2.5">
          <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand-600 text-xs font-bold text-white">{tenant.name.slice(0, 2).toUpperCase()}</span>
          <div className="min-w-0">
            <div className="truncate text-[13px] font-bold text-ink-900">{tenant.name}</div>
            <div className="truncate text-[11px] font-medium text-ink-500">{tenant.slug} · {tenant.currency}</div>
          </div>
        </div>
      ) : (
        platformMode && (
          <div className="mx-4 mb-2 rounded-xl border border-dashed border-line px-3 py-2.5 text-[12px] font-semibold text-ink-500">Platform administration</div>
        )
      )}

      <nav className="scrollbar-thin flex-1 overflow-y-auto px-3 py-3">
        {NAV_SECTIONS.map((section) => {
          if (section.scope === 'platform' && !user?.is_super_admin) return null
          if (section.scope === 'workspace' && (!tenant || user?.is_client)) return null
          if (section.scope === 'client' && !user?.is_client) return null
          const items = section.items.filter((item) => section.scope !== 'workspace' || item.always || !item.permission || can(item.permission))
          if (items.length === 0) return null
          return (
            <div key={section.title} className="mb-5">
              <div className="px-3 pb-1.5 text-[11px] font-bold tracking-wider text-ink-400 uppercase">{section.title}</div>
              <ul className="space-y-0.5">
                {items.map(({ to, label, icon: Icon, end, feature }) => (
                  <li key={to}>
                    <NavLink
                      to={to}
                      end={end}
                      onClick={onNavigate}
                      className={({ isActive }) =>
                        cn(
                          'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-[13.5px] font-semibold transition-colors',
                          isActive ? 'bg-brand-50 text-brand-700' : 'text-ink-700 hover:bg-slate-100 hover:text-ink-900',
                        )
                      }
                    >
                      {({ isActive }) => (
                        <>
                          <Icon className={cn('size-[18px] shrink-0', isActive ? 'text-brand-600' : 'text-ink-400 group-hover:text-ink-700')} />
                          <span className="flex-1">{label}</span>
                          {feature && !tenant?.plan?.[feature] && <Lock className="size-3.5 text-ink-400" aria-label="Not in your plan" />}
                        </>
                      )}
                    </NavLink>
                  </li>
                ))}
              </ul>
            </div>
          )
        })}
      </nav>

    </div>
  )
}

function Topbar({ onMenu }: { onMenu: () => void }) {
  const { user, tenant, logout } = useAuth()
  const navigate = useNavigate()
  const [menuOpen, setMenuOpen] = useState(false)
  const menuRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const close = (e: MouseEvent) => menuRef.current && !menuRef.current.contains(e.target as Node) && setMenuOpen(false)
    document.addEventListener('mousedown', close)
    return () => document.removeEventListener('mousedown', close)
  }, [])

  if (!user) return null
  const role = user.is_super_admin ? 'Super Admin' : user.is_client ? 'Client' : user.roles?.[0] ? ROLE_LABELS[user.roles[0]] : 'Member'

  return (
    <header className="no-print sticky top-0 z-20 flex h-16 items-center gap-2 border-b border-line bg-white/85 px-4 backdrop-blur-md sm:gap-3 sm:px-6 lg:px-8">
      <button onClick={onMenu} className="-ml-1.5 rounded-lg p-2 text-ink-700 hover:bg-slate-100 lg:hidden" aria-label="Open menu">
        <Menu className="size-5" />
      </button>
      <Logo compact className="lg:hidden" />

      <div className="ml-auto flex items-center gap-0.5 sm:gap-1.5">
        {(user.is_client || tenant) && <NotificationBell />}
        <ThemeToggle />

        <div className="relative" ref={menuRef}>
          <button onClick={() => setMenuOpen((v) => !v)} className="flex items-center gap-2.5 rounded-xl py-1.5 pr-2 pl-1.5 hover:bg-slate-100" aria-haspopup="menu" aria-expanded={menuOpen}>
            <Avatar name={user.name} size="sm" />
            <span className="hidden text-left sm:block">
              <span className="block max-w-[160px] truncate text-[13px] leading-tight font-bold text-ink-900">{user.name}</span>
              <span className="block text-[11px] leading-tight font-medium text-ink-500">{role}</span>
            </span>
            <ChevronDown className="hidden size-4 text-ink-400 sm:block" />
          </button>
          {menuOpen && (
            <div className="absolute right-0 mt-2 w-60 animate-fade-in overflow-hidden rounded-2xl border border-line bg-surface p-1.5 shadow-pop" role="menu">
              <div className="px-3 py-2.5">
                <div className="truncate text-[13px] font-bold">{user.name}</div>
                <div className="truncate text-xs text-ink-500">{user.email}</div>
              </div>
              <div className="my-1 h-px bg-line" />
              <Link to="/app/profile" onClick={() => setMenuOpen(false)} className="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-[13px] font-semibold text-ink-700 hover:bg-slate-100" role="menuitem">
                <UserRound className="size-4 text-ink-400" /> My profile
              </Link>
              <Link to="/app/profile#appearance" onClick={() => setMenuOpen(false)} className="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-[13px] font-semibold text-ink-700 hover:bg-slate-100" role="menuitem">
                <Settings2 className="size-4 text-ink-400" /> Appearance
              </Link>
              <button
                onClick={async () => {
                  await logout()
                  navigate('/login')
                }}
                className="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-[13px] font-semibold text-rose-600 hover:bg-rose-50"
                role="menuitem"
              >
                <LogOut className="size-4" /> Sign out
              </button>
            </div>
          )}
        </div>
      </div>
    </header>
  )
}
