import { lazy, Suspense, type ReactNode } from 'react'
import { createBrowserRouter } from 'react-router'
import { AppLayout } from '@/components/layout/AppLayout'
import { GuestOnly, RequireAuth, RequireClient, RequireSuperAdmin, RequireWorkspace } from '@/components/layout/Guards'
import { Spinner } from '@/components/ui'
import NotFoundPage from '@/pages/NotFoundPage'

const LandingPage = lazy(() => import('@/pages/LandingPage'))
const LoginPage = lazy(() => import('@/pages/auth/LoginPage'))
const TenantLoginPage = lazy(() => import('@/pages/auth/TenantLoginPage'))
const AdminLoginPage = lazy(() => import('@/pages/auth/AdminLoginPage'))
const RegisterPage = lazy(() => import('@/pages/auth/RegisterPage'))

const TrackerDashboardPage = lazy(() => import('@/pages/tracker/TrackerDashboardPage'))
const ProjectPage = lazy(() => import('@/pages/tracker/ProjectPage'))
const ProjectsPage = lazy(() => import('@/pages/tracker/ProjectsPage'))
const ClientsPage = lazy(() => import('@/pages/tracker/ClientsPage'))
const RemindersPage = lazy(() => import('@/pages/tracker/RemindersPage'))
const WhatsAppPage = lazy(() => import('@/pages/tracker/WhatsAppPage'))
const WorkspaceSettingsPage = lazy(() => import('@/pages/tracker/WorkspaceSettingsPage'))
const ProfilePage = lazy(() => import('@/pages/ProfilePage'))

const PortalProjectsPage = lazy(() => import('@/pages/portal/PortalProjectsPage'))
const PortalProjectPage = lazy(() => import('@/pages/portal/PortalProjectPage'))
const NotificationsPage = lazy(() => import('@/pages/NotificationsPage'))

const AdminOverviewPage = lazy(() => import('@/pages/admin/AdminOverviewPage'))
const TenantsPage = lazy(() => import('@/pages/admin/TenantsPage'))
const TenantDetailPage = lazy(() => import('@/pages/admin/TenantDetailPage'))
const CountriesPage = lazy(() => import('@/pages/admin/CountriesPage'))
const CurrenciesPage = lazy(() => import('@/pages/admin/CurrenciesPage'))
const PlansPage = lazy(() => import('@/pages/admin/PlansPage'))
const SystemSettingsPage = lazy(() => import('@/pages/admin/SystemSettingsPage'))

const page = (node: ReactNode) => (
  <Suspense fallback={<div className="flex min-h-[50vh] items-center justify-center"><Spinner className="size-7" /></div>}>{node}</Suspense>
)

/** A workspace screen: needs a tenant. */
const workspace = (node: ReactNode) => <RequireWorkspace>{page(node)}</RequireWorkspace>

/** A client portal screen: client accounts only. */
const portal = (node: ReactNode) => <RequireClient>{page(node)}</RequireClient>

/** A platform screen: Super Admin only. */
const platform = (node: ReactNode) => <RequireSuperAdmin>{page(node)}</RequireSuperAdmin>

export const router = createBrowserRouter([
  { path: '/', element: page(<LandingPage />) },
  { path: '/login', element: <GuestOnly>{page(<LoginPage />)}</GuestOnly> },
  { path: '/register', element: <GuestOnly>{page(<RegisterPage />)}</GuestOnly> },
  { path: '/superAdmin', element: <GuestOnly>{page(<AdminLoginPage />)}</GuestOnly> },
  {
    path: '/app',
    element: <RequireAuth><AppLayout /></RequireAuth>,
    children: [
      { index: true, element: workspace(<TrackerDashboardPage />) },
      { path: 'projects', element: workspace(<ProjectsPage />) },
      { path: 'projects/:id', element: workspace(<ProjectPage />) },
      { path: 'clients', element: workspace(<ClientsPage />) },
      { path: 'whatsapp', element: workspace(<WhatsAppPage />) },
      { path: 'reminders', element: workspace(<RemindersPage />) },
      { path: 'settings', element: workspace(<WorkspaceSettingsPage />) },
      { path: 'profile', element: page(<ProfilePage />) },

      { path: 'portal', element: portal(<PortalProjectsPage />) },
      { path: 'portal/projects/:id', element: portal(<PortalProjectPage />) },
      { path: 'notifications', element: page(<NotificationsPage />) },

      { path: 'admin', element: platform(<AdminOverviewPage />) },
      { path: 'admin/tenants', element: platform(<TenantsPage />) },
      { path: 'admin/tenants/:id', element: platform(<TenantDetailPage />) },
      { path: 'admin/countries', element: platform(<CountriesPage />) },
      { path: 'admin/currencies', element: platform(<CurrenciesPage />) },
      { path: 'admin/plans', element: platform(<PlansPage />) },
      { path: 'admin/settings', element: platform(<SystemSettingsPage />) },
    ],
  },
  // A tenant's own sign-in page, e.g. /acme-corp
  { path: '/:slug', element: <GuestOnly>{page(<TenantLoginPage />)}</GuestOnly> },
  { path: '*', element: <NotFoundPage /> },
])
