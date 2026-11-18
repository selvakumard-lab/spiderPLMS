import { CalendarDays, CircleHelp, FileText, ListChecks, Gauge, Bell, FilePlus2, Layers, MessageCircle, type LucideIcon } from 'lucide-react'
import type { PortalNotification } from '@/types/portal'

const ICONS: Record<PortalNotification['kind'], { icon: LucideIcon; tone: string }> = {
  step: { icon: ListChecks, tone: 'bg-brand-50 text-brand-600' },
  status: { icon: Gauge, tone: 'bg-amber-50 text-amber-700' },
  stage: { icon: Layers, tone: 'bg-teal-50 text-teal-700' },
  document: { icon: FileText, tone: 'bg-emerald-50 text-emerald-700' },
  request: { icon: CircleHelp, tone: 'bg-rose-50 text-rose-600' },
  requirement: { icon: FilePlus2, tone: 'bg-violet-50 text-violet-700' },
  meeting: { icon: CalendarDays, tone: 'bg-sky-50 text-sky-700' },
  message: { icon: MessageCircle, tone: 'bg-sky-50 text-sky-700' },
  update: { icon: Bell, tone: 'bg-slate-100 text-ink-700' },
}

export function NotificationIcon({ kind }: { kind: PortalNotification['kind'] }) {
  const { icon: Icon, tone } = ICONS[kind] ?? ICONS.update
  return <span className={`flex size-9 shrink-0 items-center justify-center rounded-xl ${tone}`}><Icon className="size-4" /></span>
}
