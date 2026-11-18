import { Link, useParams } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { ArrowLeft, CalendarDays, Check, CircleHelp, Clock, Download, FileText, Loader2 } from 'lucide-react'
import { Badge, Card, CardHeader, EmptyState, Progress, Skeleton } from '@/components/ui'
import { StatTile } from '@/components/ui/extra'
import { STATUS_META, TrackingBadge } from '@/components/tracker/shared'
import { useToast } from '@/context/ToastContext'
import { errorMessage } from '@/lib/api'
import { portalApi } from '@/lib/endpoints'
import { cn, formatBytes, formatDate, saveBlob } from '@/lib/utils'
import { ProjectPlan } from '@/components/tracker/ProjectPlan'
import { StageTracker } from '@/components/tracker/StageTracker'
import { Timeline } from '@/components/tracker/Timeline'
import NotFoundPage from '@/pages/NotFoundPage'
import { ProjectThread } from '@/components/tracker/ProjectThread'
import { PortalActions } from './PortalActions'
import type { ActivityStatus } from '@/types/tracker'

const STEP_STATUS: Record<ActivityStatus, { label: string; badge: string; dot: string }> = {
  completed: { label: 'Completed', badge: 'bg-emerald-50 text-emerald-700 ring-emerald-200', dot: 'bg-emerald-600 text-white' },
  in_progress: { label: 'In progress', badge: 'bg-brand-50 text-brand-700 ring-brand-200', dot: 'bg-brand-600 text-white' },
  pending: { label: 'Up next', badge: 'bg-slate-100 text-ink-700 ring-slate-200', dot: 'bg-slate-200 text-ink-700' },
}

const hours = (n: number) => `${Number.isInteger(n) ? n : n.toFixed(1)} h`

/** One project, as the client sees it: status, step-by-step workflow with time, documents, and what is needed from them. */
export default function PortalProjectPage() {
  const { id = '' } = useParams()
  const toast = useToast()
  const { data, isLoading, isError } = useQuery({ queryKey: ['portal', 'project', id], queryFn: () => portalApi.project(id), retry: false, refetchInterval: 15_000 })

  async function download(docId: number, name: string) {
    try {
      saveBlob(await portalApi.downloadDocument(docId), name)
    } catch (err) {
      toast.error(errorMessage(err, 'Could not download the file.'))
    }
  }

  if (isLoading) return <div className="space-y-4"><Skeleton className="h-24" /><Skeleton className="h-64" /></div>
  if (isError || !data) return <NotFoundPage />

  const { data: project, summary, steps } = data
  const pendingAsks = data.client_requests.filter((r) => r.is_pending)
  const due = project.revised_due_date ?? project.due_date

  return (
    <div className="space-y-6">
      <div>
        <Link to="/app/portal" className="inline-flex items-center gap-1 text-[13px] font-semibold text-ink-500 hover:text-ink-900"><ArrowLeft className="size-3.5" /> My projects</Link>
        <div className="mt-3 flex flex-wrap items-center gap-3">
          <h1 className="text-2xl font-bold tracking-tight text-ink-900 sm:text-[28px]">{project.name}</h1>
          <TrackingBadge status={project.tracking_status} />
        </div>
        <p className="mt-1 text-sm text-ink-500">{project.code} · {formatDate(project.start_date)} → {formatDate(due)}{project.status_updated_at ? ` · Updated ${formatDate(project.status_updated_at)}` : ''}</p>
        {project.description && <p className="mt-2 max-w-3xl text-[13.5px] whitespace-pre-line text-ink-700">{project.description}</p>}
      </div>

      <Card className="p-5">
        <div className="mb-3 text-[13px] font-bold text-ink-900">Project stage</div>
        <StageTracker stages={data.stages} current={project.stage} />
      </Card>

      {pendingAsks.length > 0 && (
        <Card className="border-rose-200 bg-rose-50/60 p-5">
          <div className="flex items-center gap-2 text-[14px] font-bold text-rose-700"><CircleHelp className="size-4" /> What we need from you</div>
          <ul className="mt-3 space-y-2.5">
            {pendingAsks.map((r) => (
              <li key={r.id} className="text-[13.5px] text-ink-900">
                <span className="font-semibold">{r.title}</span>
                {r.expected_on && <span className="text-ink-500"> · needed by {formatDate(r.expected_on)}</span>}
                {r.days_delayed > 0 && <span className="font-semibold text-rose-700"> · {r.days_delayed} day{r.days_delayed === 1 ? '' : 's'} overdue</span>}
                {r.description && <span className="block text-[13px] text-ink-700">{r.description}</span>}
              </li>
            ))}
          </ul>
        </Card>
      )}

      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <StatTile label="Progress" value={`${summary.progress_percent}%`} hint={`${summary.completed_activities} of ${steps.length} steps done`} />
        <StatTile label="Time spent" value={hours(summary.hours_spent)} hint={`${summary.days_spent} working day${summary.days_spent === 1 ? '' : 's'}${summary.estimated_hours ? ` · estimate ${hours(summary.estimated_hours)}` : ''}`} />
        <StatTile label="Working days left" value={summary.remaining_working_days} hint={summary.total_working_days ? `of ${summary.total_working_days} planned` : 'to the deadline'} />
        <StatTile label={summary.overdue_working_days > 0 ? 'Past deadline' : 'Delivery date'} value={summary.overdue_working_days > 0 ? `${summary.overdue_working_days} d` : formatDate(due)} tone={summary.overdue_working_days > 0 ? 'danger' : 'default'}
          hint={summary.additional_days_approved ? `Includes ${summary.additional_days_approved} extra days for new requirements` : undefined} />
      </div>

      <Card className="p-5">
        <div className="mb-2 flex justify-between text-[13px] font-semibold"><span className="text-ink-500">Overall progress</span><span className="tabular text-ink-900">{summary.progress_percent}%</span></div>
        <Progress value={summary.progress_percent} color={STATUS_META[summary.status]?.bar} className="h-2.5" />
      </Card>

      {data.plan.milestones.length > 0 && (
        <Card>
          <CardHeader title="Project plan" subtitle="Every step from kickoff to delivery, with its deadline and how long it took" />
          <div className="p-5 sm:p-6"><ProjectPlan plan={data.plan} /></div>
        </Card>
      )}

      <Card>
        <CardHeader title="Step-by-step workflow" subtitle="Each step the team has worked on, with the time it took" />
        <div className="p-5 sm:p-6">
          {steps.length === 0 ? (
            <EmptyState icon={<Clock className="size-6" />} title="Work has not been logged yet" description="Steps appear here as the team starts on your project." />
          ) : (
            <ol className="relative space-y-6">
              {steps.map((s, i) => {
                const meta = STEP_STATUS[s.status]
                return (
                  <li key={s.id} className="relative flex gap-4">
                    {i < steps.length - 1 && <span className="absolute top-9 bottom-[-1.5rem] left-[17px] w-px bg-line" aria-hidden />}
                    <span className={cn('z-10 flex size-9 shrink-0 items-center justify-center rounded-full text-[13px] font-bold', meta.dot)}>
                      {s.status === 'completed' ? <Check className="size-4" /> : s.status === 'in_progress' ? <Loader2 className="size-4 animate-spin" /> : s.number}
                    </span>
                    <div className="min-w-0 flex-1 pb-1">
                      <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <h3 className="text-[14.5px] font-bold text-ink-900">{s.title}</h3>
                        <Badge className={meta.badge}>{meta.label}</Badge>
                      </div>
                      <div className="mt-0.5 flex flex-wrap gap-x-4 gap-y-0.5 text-[12.5px] text-ink-500">
                        <span className="inline-flex items-center gap-1"><CalendarDays className="size-3.5" />{formatDate(s.date)}</span>
                        <span className="inline-flex items-center gap-1"><Clock className="size-3.5" />{hours(s.hours_spent)} spent{s.estimated_hours ? ` of ${hours(s.estimated_hours)} estimated` : ''}</span>
                        <span>Project at {s.progress_percent}%</span>
                      </div>
                      {s.description && <p className="mt-1.5 text-[13.5px] whitespace-pre-line text-ink-700">{s.description}</p>}
                    </div>
                  </li>
                )
              })}
            </ol>
          )}
        </div>
      </Card>

      <Card>
        <CardHeader title="Documents" subtitle="Files the team has shared with you" />
        <div className="p-5 sm:p-6">
          {data.documents.length === 0 ? (
            <p className="text-[13.5px] text-ink-500">No documents have been shared yet.</p>
          ) : (
            <ul className="divide-y divide-line">
              {data.documents.map((d) => (
                <li key={d.id} className="flex items-center gap-3 py-3 first:pt-0 last:pb-0">
                  <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><FileText className="size-[18px]" /></span>
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2 text-[14px] font-semibold text-ink-900"><span className="truncate">{d.title}</span>{d.from_client && <Badge className="bg-violet-50 text-violet-700 ring-violet-200">Sent by you</Badge>}</div>
                    <div className="truncate text-[12.5px] text-ink-500">{d.name} · {formatBytes(d.size)} · {formatDate(d.created_at)}</div>
                    {d.note && <div className="mt-0.5 text-[13px] text-ink-700">{d.note}</div>}
                  </div>
                  <button onClick={() => download(d.id, d.name)} className="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-[13px] font-semibold text-brand-600 hover:bg-brand-50"><Download className="size-4" /> Download</button>
                </li>
              ))}
            </ul>
          )}
        </div>
      </Card>

      <ProjectThread side="client" queryKey={['portal', 'chat', project.id]} load={() => portalApi.chat(project.id)} send={(text) => portalApi.sendChatMessage(project.id, text)}
        title="Messages" subtitle="Chat with the team. Their replies appear here" emptyText="Ask a question or share news. The team is notified straight away." />

      <PortalActions projectId={project.id} />

      {(data.requirements.length > 0 || data.meetings.length > 0) && (
        <div className="grid gap-6 lg:grid-cols-2">
          {data.requirements.length > 0 && (
            <Card>
              <CardHeader title="New requirements" subtitle="Changes requested and what was decided" />
              <ul className="divide-y divide-line px-5 sm:px-6">
                {data.requirements.map((c) => (
                  <li key={c.id} className="py-3">
                    <div className="flex items-center justify-between gap-3"><span className="text-[14px] font-semibold text-ink-900">{c.title}</span><Badge className={c.status === 'approved' ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : c.status === 'rejected' ? 'bg-rose-50 text-rose-700 ring-rose-200' : 'bg-amber-50 text-amber-700 ring-amber-200'}>{c.status}</Badge></div>
                    <div className="text-[12.5px] text-ink-500">Raised {formatDate(c.submitted_on)}{c.additional_days > 0 ? ` · +${c.additional_days} working days` : ''}</div>
                  </li>
                ))}
              </ul>
            </Card>
          )}
          {data.meetings.length > 0 && (
            <Card>
              <CardHeader title="Meetings" subtitle="What was discussed" />
              <ul className="divide-y divide-line px-5 sm:px-6">
                {data.meetings.map((m) => (
                  <li key={m.id} className="py-3">
                    <div className="text-[14px] font-semibold text-ink-900">{m.title}</div>
                    <div className="text-[12.5px] text-ink-500">{formatDate(m.date)}</div>
                    {m.discussion_points && <p className="mt-1 text-[13px] whitespace-pre-line text-ink-700">{m.discussion_points}</p>}
                  </li>
                ))}
              </ul>
            </Card>
          )}
        </div>
      )}

      <Timeline events={data.events} />
    </div>
  )
}
