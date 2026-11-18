import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, CircleCheck, Pencil, RotateCcw, Sparkles, Trash2 } from 'lucide-react'
import { Button, Card, Skeleton } from '@/components/ui'
import { ConfirmButton } from '@/components/ui/extra'
import { ReportButtons, TrackingBadge } from '@/components/tracker/shared'
import { StageTracker } from '@/components/tracker/StageTracker'
import { ProjectThread } from '@/components/tracker/ProjectThread'
import { Timeline } from '@/components/tracker/Timeline'
import { useToast } from '@/context/ToastContext'
import { errorMessage } from '@/lib/api'
import { trackerApi } from '@/lib/endpoints'
import { formatDate } from '@/lib/utils'
import NotFoundPage from '@/pages/NotFoundPage'
import { ActivitySection } from './ActivitySection'
import { ChangeSection } from './ChangeSection'
import { ClientChatSection } from './ClientChatSection'
import { DependencySection } from './DependencySection'
import { DocumentSection } from './DocumentSection'
import { MeetingSection } from './MeetingSection'
import { OverallStatus } from './OverallStatus'
import { PlanSection } from './PlanSection'
import { ProjectFormModal } from './ProjectFormModal'

/** One project: activities, meetings, new requirements, client dependencies, then overall status. */
export default function ProjectPage() {
  const { id = '' } = useParams()
  const navigate = useNavigate()
  const toast = useToast()
  const queryClient = useQueryClient()
  const [editing, setEditing] = useState(false)
  const { data, isLoading, isError } = useQuery({ queryKey: ['tracker', 'project', id], queryFn: () => trackerApi.project(id), retry: false, refetchInterval: 20_000 })

  const refresh = () => queryClient.invalidateQueries({ queryKey: ['tracker'] })
  const setStatus = useMutation({
    mutationFn: (status: 'active' | 'completed') => trackerApi.updateProject(Number(id), { status }),
    onSuccess: refresh,
    onError: (err) => toast.error(errorMessage(err)),
  })
  const moveStage = useMutation({
    mutationFn: (stage: string) => trackerApi.updateProject(Number(id), { stage }),
    onSuccess: (_, stage) => {
      toast.success(`Moved to ${data?.stages.find((s) => s.value === stage)?.label ?? stage}. The client has been notified.`)
      refresh()
    },
    onError: (err) => toast.error(errorMessage(err)),
  })
  const analyse = useMutation({
    mutationFn: () => trackerApi.analyse(Number(id)),
    onSuccess: () => {
      toast.success('Analysis updated.')
      refresh()
    },
    onError: (err) => toast.error(errorMessage(err)),
  })

  if (isLoading) return <div className="space-y-4"><Skeleton className="h-24" /><Skeleton className="h-64" /></div>
  if (isError || !data) return <NotFoundPage />

  const { data: project, summary } = data
  const lastProgress = data.activities.length ? data.activities[data.activities.length - 1].progress_percent : 0

  return (
    <div className="space-y-6">
      <div>
        <Link to="/app" className="inline-flex items-center gap-1 text-[13px] font-semibold text-ink-500 hover:text-ink-900"><ArrowLeft className="size-3.5" /> Dashboard</Link>
        <div className="mt-3 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div className="min-w-0">
            <div className="flex flex-wrap items-center gap-3">
              <h1 className="text-2xl font-bold tracking-tight text-ink-900 sm:text-[28px]">{project.name}</h1>
              <TrackingBadge status={project.tracking_status} />
            </div>
            <p className="mt-1 text-sm text-ink-500">
              {project.code}{project.client_name ? ` · ${project.client_name}` : ''} · {formatDate(project.start_date)} → {formatDate(project.revised_due_date ?? project.due_date)}
            </p>
            {project.description && <p className="mt-2 max-w-3xl text-[13.5px] whitespace-pre-line text-ink-700">{project.description}</p>}
          </div>
          <div className="flex flex-wrap gap-2">
            <Button variant="secondary" size="sm" icon={<Pencil className="size-4" />} onClick={() => setEditing(true)}>Edit</Button>
            {project.status === 'completed'
              ? <Button variant="secondary" size="sm" icon={<RotateCcw className="size-4" />} loading={setStatus.isPending} onClick={() => setStatus.mutate('active')}>Reopen</Button>
              : <Button size="sm" icon={<CircleCheck className="size-4" />} loading={setStatus.isPending} onClick={() => setStatus.mutate('completed')}>Mark completed</Button>}
            <ConfirmButton title="Delete project" message="This permanently removes the project from your dashboard." confirmLabel="Delete" danger
              onConfirm={async () => { await trackerApi.deleteProject(project.id); queryClient.invalidateQueries({ queryKey: ['tracker'] }); navigate('/app') }}
              trigger={(o) => <Button variant="ghost" size="sm" aria-label="Delete project" icon={<Trash2 className="size-4" />} onClick={o} />} />
          </div>
        </div>
      </div>

      <Card className="p-4 sm:p-5">
        <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
          <div className="text-[13px] font-bold text-ink-900">Project stage <span className="font-medium text-ink-500">· click a stage to move the project there; the client is notified</span></div>
          <div className="flex gap-2">
            {(() => {
              const order = data.stages.map((s) => s.value)
              const at = order.indexOf(project.stage)
              return (
                <>
                  <Button size="sm" variant="secondary" disabled={at <= 0} loading={moveStage.isPending} onClick={() => moveStage.mutate(order[at - 1])}>Back a stage</Button>
                  <Button size="sm" disabled={at >= order.length - 1} loading={moveStage.isPending} onClick={() => moveStage.mutate(order[at + 1])}>Move to next stage</Button>
                </>
              )
            })()}
          </div>
        </div>
        <StageTracker stages={data.stages} current={project.stage} onMove={(stage) => moveStage.mutate(stage)} busy={moveStage.isPending} />
      </Card>

      <Card className="p-4 sm:p-5">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div className="flex gap-3">
            <Sparkles className="mt-0.5 size-5 shrink-0 text-brand-600" />
            <div>
              <div className="text-[13px] font-bold text-ink-900">AI progress analysis</div>
              <p className="mt-0.5 text-[13.5px] text-ink-700">{project.ai_summary ?? 'Log an activity and the analysis appears here.'}</p>
            </div>
          </div>
          <Button size="sm" variant="secondary" loading={analyse.isPending} disabled={!data.activities.length} onClick={() => analyse.mutate()}>Re-analyse</Button>
        </div>
      </Card>

      <PlanSection projectId={project.id} plan={data.plan} stages={data.stages} />
      <ActivitySection projectId={project.id} activities={data.activities} lastProgress={lastProgress} />
      <MeetingSection projectId={project.id} meetings={data.meetings} />
      <ChangeSection projectId={project.id} changes={data.change_requests} />
      <DependencySection projectId={project.id} items={data.client_dependencies} />
      <DocumentSection projectId={project.id} documents={data.documents ?? []} hasPortal={!!project.client_id} />
      <ProjectThread side="team" queryKey={['project-thread', project.id]} load={() => trackerApi.thread(project.id)} send={(text) => trackerApi.sendThreadMessage(project.id, text)}
        title="Client conversation" subtitle="Messages here are visible to the client in their portal" emptyText="Start the conversation. The client sees your message in their portal."
        disabledReason={project.client_id ? undefined : 'Link a client to this project (Edit project) to start a conversation.'} />
      <Timeline events={data.events ?? []} />
      <ClientChatSection projectId={project.id} clientName={project.client_name} />

      <Card className="p-4 sm:p-5">
        <div className="mb-3 text-[13px] font-bold text-ink-900">Download reports</div>
        <ReportButtons projectId={project.id} projectCode={project.code} />
      </Card>

      <OverallStatus key={`${project.revised_due_date}-${summary.status_updated_at}`} project={project} summary={summary} />

      <ProjectFormModal open={editing} onClose={() => setEditing(false)} project={project} />
    </div>
  )
}
