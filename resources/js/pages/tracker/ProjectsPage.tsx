import { useState } from 'react'
import { Link, useNavigate } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { FolderKanban, FolderPlus, Search } from 'lucide-react'
import { Button, Card, EmptyState, Input, PageHeader, Progress, Select, Skeleton } from '@/components/ui'
import { STATUS_META, TrackingBadge } from '@/components/tracker/shared'
import { useAuth } from '@/context/AuthContext'
import { trackerApi } from '@/lib/endpoints'
import { formatDate } from '@/lib/utils'
import { ProjectFormModal } from './ProjectFormModal'
import type { TrackingStatus } from '@/types/tracker'

/** Every project of the workspace, searchable and filterable by status. */
export default function ProjectsPage() {
  const { tenant } = useAuth()
  const navigate = useNavigate()
  const [adding, setAdding] = useState(false)
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState<TrackingStatus | ''>('')
  const { data, isLoading } = useQuery({ queryKey: ['tracker', 'projects', search, status], queryFn: () => trackerApi.projects({ search, status }) })

  return (
    <>
      <PageHeader
        eyebrow={tenant?.name}
        title="Projects"
        description="All projects with their progress, status and delivery dates."
        actions={<Button icon={<FolderPlus className="size-4" />} onClick={() => setAdding(true)}>Onboard project</Button>}
      />

      <Card>
        <div className="flex flex-col gap-3 border-b border-line p-4 sm:flex-row sm:items-center sm:p-5">
          <div className="relative flex-1">
            <Search className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-ink-400" />
            <Input className="pl-10" placeholder="Search projects" value={search} onChange={(e) => setSearch(e.target.value)} aria-label="Search projects" />
          </div>
          <Select className="sm:w-60" value={status} onChange={(e) => setStatus(e.target.value as TrackingStatus | '')} aria-label="Filter by status">
            <option value="">All statuses</option>
            {(Object.keys(STATUS_META) as TrackingStatus[]).map((s) => <option key={s} value={s}>{STATUS_META[s].label}</option>)}
          </Select>
        </div>

        <div className="p-4 sm:p-5">
          {isLoading ? (
            <div className="space-y-3">{[0, 1, 2].map((i) => <Skeleton key={i} className="h-16" />)}</div>
          ) : !data?.length ? (
            <EmptyState icon={<FolderKanban className="size-6" />} title={search || status ? 'No matching projects' : 'No projects yet'}
              description={search || status ? 'Try a different search or status.' : 'Onboard your first project to start tracking.'}
              action={!search && !status ? <Button onClick={() => setAdding(true)}>Onboard project</Button> : undefined} />
          ) : (
            <ul className="divide-y divide-line">
              {data.map((p) => (
                <li key={p.id} className="flex flex-col gap-3 py-4 first:pt-0 last:pb-0 lg:flex-row lg:items-center lg:gap-6">
                  <div className="min-w-0 flex-1">
                    <Link to={`/app/projects/${p.id}`} className="text-[15px] font-bold text-ink-900 hover:text-brand-700">{p.name}</Link>
                    <div className="mt-0.5 text-[13px] text-ink-500">{p.code}{p.client_name ? ` · ${p.client_name}` : ''}</div>
                  </div>
                  <div className="w-full lg:w-56">
                    <div className="mb-1 flex justify-between text-xs font-semibold text-ink-500"><span>Progress</span><span>{p.progress}%</span></div>
                    <Progress value={p.progress} color={STATUS_META[p.tracking_status]?.bar} />
                  </div>
                  <div className="text-[13px] text-ink-500 lg:w-48">{formatDate(p.start_date)} → {formatDate(p.revised_due_date ?? p.due_date)}</div>
                  <div className="lg:w-44"><TrackingBadge status={p.tracking_status} /></div>
                  <Button size="sm" variant="secondary" onClick={() => navigate(`/app/projects/${p.id}`)}>Open</Button>
                </li>
              ))}
            </ul>
          )}
        </div>
      </Card>

      <ProjectFormModal open={adding} onClose={() => setAdding(false)} onSaved={(p) => navigate(`/app/projects/${p.id}`)} />
    </>
  )
}
