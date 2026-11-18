import { Link } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { ArrowRight, FolderKanban } from 'lucide-react'
import { Card, EmptyState, PageHeader, Progress, Skeleton } from '@/components/ui'
import { STATUS_META, TrackingBadge } from '@/components/tracker/shared'
import { useAuth } from '@/context/AuthContext'
import { portalApi } from '@/lib/endpoints'
import { formatDate } from '@/lib/utils'

/** The client's home: every project the team is delivering for them, with its current status. */
export default function PortalProjectsPage() {
  const { user, tenant } = useAuth()
  const { data, isLoading } = useQuery({ queryKey: ['portal', 'projects'], queryFn: portalApi.projects })

  return (
    <>
      <PageHeader eyebrow={tenant?.name} title={`Welcome, ${user?.name.split(' ')[0] ?? ''}`} description="Follow your projects: where each one stands, what the team is working on, and anything we need from you." />

      {isLoading ? (
        <div className="grid gap-4 md:grid-cols-2">{[0, 1].map((i) => <Skeleton key={i} className="h-44" />)}</div>
      ) : !data?.length ? (
        <Card className="p-6"><EmptyState icon={<FolderKanban className="size-6" />} title="No projects yet" description="Your projects will appear here as soon as the team links them to your account." /></Card>
      ) : (
        <div className="grid gap-4 md:grid-cols-2">
          {data.map((p) => (
            <Link key={p.id} to={`/app/portal/projects/${p.id}`} className="card group block p-5 transition-shadow hover:shadow-pop">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <div className="text-[12px] font-semibold text-ink-400">{p.code}</div>
                  <h2 className="truncate text-[16px] font-bold text-ink-900">{p.name}</h2>
                </div>
                <TrackingBadge status={p.tracking_status} />
              </div>
              {p.description && <p className="mt-2 line-clamp-2 text-[13px] text-ink-500">{p.description}</p>}
              <div className="mt-4">
                <div className="mb-1.5 flex justify-between text-[12.5px] font-semibold"><span className="text-ink-500">Progress</span><span className="tabular text-ink-900">{p.progress}%</span></div>
                <Progress value={p.progress} color={STATUS_META[p.tracking_status]?.bar} />
              </div>
              <div className="mt-4 flex items-center justify-between text-[12.5px] text-ink-500">
                <span>{formatDate(p.start_date)} → {formatDate(p.revised_due_date ?? p.due_date)}</span>
                <span className="inline-flex items-center gap-1 font-semibold text-brand-600">Open <ArrowRight className="size-3.5 transition-transform group-hover:translate-x-0.5" /></span>
              </div>
            </Link>
          ))}
        </div>
      )}
    </>
  )
}
