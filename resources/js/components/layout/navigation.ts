import { Bell, BellRing, Building2, Coins, FolderKanban, Globe, LayoutDashboard, LayoutGrid, MessageCircle, Settings, SlidersHorizontal, Tags, Users, type LucideIcon } from 'lucide-react'

export interface NavItem {
  label: string
  to: string
  icon: LucideIcon
  permission?: string
  /** Visible to everyone signed in to a workspace (no permission needed). */
  always?: boolean
  end?: boolean
  /** A plan capability the item belongs to; the item stays visible but shows a lock when the plan lacks it. */
  feature?: 'whatsapp_chat'
}

export interface NavSection {
  title: string
  /** 'workspace' sections need a tenant; 'platform' sections are Super Admin only; 'client' sections are the client portal. */
  scope: 'workspace' | 'platform' | 'client'
  items: NavItem[]
}

export const NAV_SECTIONS: NavSection[] = [
  {
    title: 'Client portal',
    scope: 'client',
    items: [
      { label: 'My projects', to: '/app/portal', icon: FolderKanban, end: true },
      { label: 'Notifications', to: '/app/notifications', icon: Bell },
    ],
  },
  {
    title: 'Platform',
    scope: 'platform',
    items: [
      { label: 'Overview', to: '/app/admin', icon: LayoutGrid, end: true },
      { label: 'Tenants', to: '/app/admin/tenants', icon: Building2 },
      { label: 'Countries', to: '/app/admin/countries', icon: Globe },
      { label: 'Currencies', to: '/app/admin/currencies', icon: Coins },
      { label: 'Plans & pricing', to: '/app/admin/plans', icon: Tags },
      { label: 'System settings', to: '/app/admin/settings', icon: SlidersHorizontal },
    ],
  },
  {
    title: 'Workspace',
    scope: 'workspace',
    items: [
      { label: 'Dashboard', to: '/app', icon: LayoutDashboard, end: true },
      { label: 'Projects', to: '/app/projects', icon: FolderKanban },
      { label: 'Clients', to: '/app/clients', icon: Users },
      { label: 'WhatsApp', to: '/app/whatsapp', icon: MessageCircle, feature: 'whatsapp_chat', always: true },
      { label: 'Notifications', to: '/app/notifications', icon: Bell },
      { label: 'Reminders & Alerts', to: '/app/reminders', icon: BellRing },
      { label: 'Settings', to: '/app/settings', icon: Settings },
    ],
  },
]
