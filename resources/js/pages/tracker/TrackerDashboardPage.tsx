import { useState } from 'react'
import { Link, useNavigate } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { FolderPlus, FolderKanban } from 'lucide-react'
import { Button, Card, CardHeader, EmptyState, PageHeader, Progress, Skeleton } from '@/components/ui'
import { StatTile } from '@/components/ui/extra'
import { ReportButtons, STATUS_META, TrackingBadge } from '@/components/tracker/shared'
import { useAuth } from '@/context/AuthContext'
import { trackerApi } from '@/lib/endpoints'
import { formatDate } from '@/lib/utils'
import { ProjectFormModal } from './ProjectFormModal'
import type { TrackingStatus } from '@/types/tracker'

const ORDER: TrackingStatus[] = ['on_time', 'slightly_delayed', 'delayed', 'significantly_delayed', 'completed']

/** Tenant home: status overview, project list with progress, onboarding and reports. */
export default function TrackerDashboardPage() {
  const { tenant } = useAuth()
  const navigate = useNavigate()
  const [adding, setAdding] = useState(false)
  const { data, isLoading } = useQuery({ queryKey: ['tracker', 'dashboard'], queryFn: trackerApi.dashboard })

  return (
    <>
      <PageHeader
        eyebrow={tenant?.name}
        title="Dashboard"
        description="Every project, how far along it is, and whether it is on schedule."
        actions={<Button icon={<FolderPlus className="size-4" />} onClick={() => setAdding(true)}>Onboard project</Button>}
      />

      <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-6">
        <StatTile label="Projects" value={isLoading ? '–' : data?.total} hint={data ? `${data.average_progress}% avg. progress` : undefined} />
        {ORDER.map((s) => (
          <StatTile key={s} label={STATUS_META[s].label} value={isLoading ? '–' : (data?.by_status[s] ?? 0)}
            tone={s === 'significantly_delayed' && (data?.by_status[s] ?? 0) > 0 ? 'danger' : s === 'completed' ? 'success' : 'default'} />
        ))}
      </div>

      <Card className="mt-6">
        <CardHeader title="Projects" subtitle="Progress, status and timeline" />
        <div className="p-5 sm:p-6">
          {isLoading ? (
            <div className="space-y-3">{[0, 1, 2].map((i) => <Skeleton key={i} className="h-16" />)}</div>
          ) : !data?.projects.length ? (
            <EmptyState icon={<FolderKanban className="size-6" />} title="No projects yet" description="Onboard your first project to start tracking its activities and timeline."
              action={<Button onClick={() => setAdding(true)}>Onboard project</Button>} />
          ) : (
            <ul className="divide-y divide-line">
              {data.projects.map((p) => (
                <li key={p.id} className="flex flex-col gap-3 py-4 first:pt-0 last:pb-0 lg:flex-row lg:items-center lg:gap-6">
                  <div className="min-w-0 flex-1">
                    <Link to={`/app/projects/${p.id}`} className="text-[15px] font-bold text-ink-900 hover:text-brand-700">{p.name}</Link>
                    <div className="mt-0.5 text-[13px] text-ink-500">{p.code}{p.client_name ? ` · ${p.client_name}` : ''}</div>
                  </div>
                  <div className="w-full lg:w-56">
                    <div className="mb-1 flex justify-between text-xs font-semibold text-ink-500"><span>Progress</span><span>{p.progress}%</span></div>
                    <Progress value={p.progress} color={STATUS_META[p.tracking_status]?.bar} />
                  </div>
                  <div className="text-[13px] text-ink-500 lg:w-48">
                    {formatDate(p.start_date)} → {formatDate(p.revised_due_date ?? p.due_date)}
                    {p.revised_due_date && <div className="text-xs font-medium text-amber-600">Revised from {formatDate(p.due_date)}</div>}
                  </div>
                  <div className="lg:w-44"><TrackingBadge status={p.tracking_status} /></div>
                  <Button size="sm" variant="secondary" onClick={() => navigate(`/app/projects/${p.id}`)}>Open</Button>
                </li>
              ))}
            </ul>
          )}
        </div>
      </Card>

      {!!data?.projects.length && (
        <Card className="mt-6">
          <CardHeader title="Reports" subtitle="Download a project's reports as PDF or Excel" />
          <div className="space-y-4 p-5 sm:p-6">
            {data.projects.map((p) => (
              <div key={p.id} className="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                <span className="text-[13.5px] font-semibold text-ink-900">{p.name}</span>
                <ReportButtons projectId={p.id} projectCode={p.code} />
              </div>
            ))}
          </div>
        </Card>
      )}

      <ProjectFormModal open={adding} onClose={() => setAdding(false)} onSaved={(p) => navigate(`/app/projects/${p.id}`)} />
    </>
  )
}
