import { cn } from '@/lib/utils'
import { Badge } from '@/components/ui'
import type { TenantStatus } from '@/types'

const TONES: Record<TenantStatus, string> = {
  trial: 'bg-orange-50 text-orange-700 ring-orange-200',
  active: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
  past_due: 'bg-amber-50 text-amber-800 ring-amber-200',
  suspended: 'bg-rose-50 text-rose-700 ring-rose-200',
  cancelled: 'bg-slate-100 text-slate-600 ring-slate-200',
}

export function TenantStatusBadge({ status }: { status: TenantStatus }) {
  return <Badge className={cn('capitalize', TONES[status])}>{status.replace('_', ' ')}</Badge>
}
