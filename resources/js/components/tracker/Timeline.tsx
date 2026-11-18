import { useState } from 'react'
import { History } from 'lucide-react'
import { Badge, Card, CardHeader, EmptyState } from '@/components/ui'
import { NotificationIcon } from '@/components/notificationMeta'
import { formatDateTime } from '@/lib/utils'
import type { ProjectEvent } from '@/types/tracker'

/** Everything either side did on the project, newest first. */
export function Timeline({ events }: { events: ProjectEvent[] }) {
  const [all, setAll] = useState(false)
  const shown = all ? events : events.slice(0, 12)

  return (
    <Card>
      <CardHeader title="Activity timeline" subtitle="Every change by the team or the client, recorded here" />
      <div className="p-5 sm:p-6">
        {events.length === 0 ? (
          <EmptyState icon={<History className="size-6" />} title="Nothing recorded yet" description="Updates, uploads and stage changes will appear here." />
        ) : (
          <>
            <ul className="space-y-4">
              {shown.map((e) => (
                <li key={e.id} className="flex gap-3">
                  <NotificationIcon kind={e.kind} />
                  <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                      <span className="text-[13.5px] font-semibold text-ink-900">{e.title}</span>
                      <Badge className={e.actor === 'client' ? 'bg-violet-50 text-violet-700 ring-violet-200' : 'bg-slate-100 text-ink-700 ring-slate-200'}>{e.actor === 'client' ? 'Client' : 'Team'}</Badge>
                    </div>
                    {e.body && <p className="mt-0.5 text-[13px] whitespace-pre-line text-ink-700">{e.body}</p>}
                    <div className="mt-0.5 text-[12px] text-ink-400">{e.actor_name} · {formatDateTime(e.created_at)}</div>
                  </div>
                </li>
              ))}
            </ul>
            {events.length > 12 && !all && <button onClick={() => setAll(true)} className="mt-4 text-[13px] font-semibold text-brand-600 hover:text-brand-700">Show all {events.length} entries</button>}
          </>
        )}
      </div>
    </Card>
  )
}
