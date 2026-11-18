import type { ReactNode } from 'react'
import { AlertTriangle, ArrowRight, CalendarDays, Check, Flag, Loader2, Timer } from 'lucide-react'
import { Badge, Progress } from '@/components/ui'
import { cn, formatDate } from '@/lib/utils'
import type { Milestone, MilestoneDisplayStatus, ProjectPlan as Plan } from '@/types/tracker'

const STATUS: Record<MilestoneDisplayStatus, { label: string; badge: string; dot: string; bar: string }> = {
  pending: { label: 'Pending', badge: 'bg-slate-100 text-ink-700 ring-slate-200', dot: 'bg-slate-200 text-ink-700', bar: '#94a3b8' },
  in_progress: { label: 'In progress', badge: 'bg-amber-50 text-amber-700 ring-amber-200', dot: 'bg-amber-500 text-white', bar: '#f59e0b' },
  completed: { label: 'Completed', badge: 'bg-emerald-50 text-emerald-700 ring-emerald-200', dot: 'bg-emerald-600 text-white', bar: '#10b981' },
  delayed: { label: 'Delayed', badge: 'bg-rose-50 text-rose-700 ring-rose-200', dot: 'bg-rose-600 text-white', bar: '#e11d48' },
}

const days = (n: number) => `${n} working day${n === 1 ? '' : 's'}`

/** The one-line verdict on a step's timing: on time, late, overdue, or how long is left. */
function timingNote(m: Milestone): { text: string; tone: 'good' | 'bad' | 'neutral' } | null {
  switch (m.timing) {
    case 'completed_on_time': return { text: 'Finished on time', tone: 'good' }
    case 'completed_late': return { text: `Finished ${days(m.days_late)} late`, tone: 'bad' }
    case 'overdue': return { text: `${days(m.days_late)} overdue`, tone: 'bad' }
    case 'in_progress': return m.days_until_due !== null ? { text: m.days_until_due === 0 ? 'Due today' : `Due in ${days(m.days_until_due)}`, tone: 'neutral' } : null
    default: return m.days_until_due !== null && m.due_date ? { text: m.days_until_due === 0 ? 'Due today' : `Due in ${days(m.days_until_due)}`, tone: 'neutral' } : null
  }
}

/**
 * The step-by-step plan from kickoff to delivery: status, deadline, days taken against days planned, and what is next.
 * Shown to the team (with `renderActions`) and to the client (read-only).
 */
export function ProjectPlan({ plan, renderActions }: { plan: Plan; renderActions?: (step: Milestone) => ReactNode }) {
  const { milestones, summary } = plan

  return (
    <div>
      <div className="rounded-xl border border-line bg-canvas/60 p-4">
        <div className="flex flex-wrap items-center justify-between gap-2 text-[13px]">
          <span className="font-bold text-ink-900">{summary.completed} of {summary.total} steps complete</span>
          <span className="tabular font-bold text-ink-700">{summary.percent}%</span>
        </div>
        <Progress className="mt-2 h-2.5" value={summary.percent} color={summary.overdue > 0 ? STATUS.delayed.bar : STATUS.completed.bar} />
        <div className="mt-3 flex flex-wrap items-center gap-2 text-xs font-semibold">
          {summary.in_progress > 0 && <Badge className={STATUS.in_progress.badge}>{summary.in_progress} in progress</Badge>}
          {summary.overdue > 0 && <Badge className={STATUS.delayed.badge}><AlertTriangle className="mr-1 size-3" />{summary.overdue} delayed</Badge>}
          {summary.delayed > summary.overdue && <Badge className="bg-orange-50 text-orange-700 ring-orange-200">{summary.delayed - summary.overdue} finished late</Badge>}
          {summary.overdue === 0 && summary.total > 0 && summary.completed < summary.total && <Badge className={STATUS.completed.badge}>No step is behind</Badge>}
          {summary.next ? (
            <span className="inline-flex items-center gap-1.5 text-ink-700"><ArrowRight className="size-3.5 text-brand-600" /> Next: <span className="font-bold text-ink-900">{summary.next.title}</span>{summary.next.due_date && <span className="font-medium text-ink-500">· due {formatDate(summary.next.due_date)}</span>}</span>
          ) : summary.total > 0 ? (
            <span className="inline-flex items-center gap-1.5 text-emerald-700"><Flag className="size-3.5" /> Every step is complete</span>
          ) : null}
        </div>
      </div>

      <ol className="mt-6 space-y-1">
        {milestones.map((m, i) => {
          const meta = STATUS[m.display_status]
          const note = timingNote(m)
          const taken = m.days_taken
          const barPercent = taken !== null && m.planned_days ? Math.min(100, Math.round((taken / m.planned_days) * 100)) : 0
          const over = taken !== null && m.planned_days !== null && taken > m.planned_days

          return (
            <li key={m.id} className="relative flex gap-4 pb-6 last:pb-0">
              {i < milestones.length - 1 && <span className={cn('absolute top-10 bottom-0 left-[17px] w-0.5', m.display_status === 'completed' ? 'bg-emerald-300' : 'bg-line')} aria-hidden />}
              <span className={cn('z-10 flex size-9 shrink-0 items-center justify-center rounded-full text-[13px] font-bold', meta.dot, m.display_status === 'in_progress' && 'motion-safe:animate-pulse-ring')}>
                {m.display_status === 'completed' ? <Check className="size-4" strokeWidth={3} /> : m.display_status === 'delayed' ? <AlertTriangle className="size-4" /> : m.display_status === 'in_progress' ? <Loader2 className="size-4 animate-spin" /> : m.number}
              </span>

              <div className={cn('min-w-0 flex-1 rounded-xl border p-4', m.display_status === 'delayed' ? 'border-rose-200 bg-rose-50/40' : 'border-line bg-surface')}>
                <div className="flex flex-wrap items-start justify-between gap-2">
                  <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                      <h4 className="text-[14.5px] font-bold text-ink-900">{m.title}</h4>
                      <Badge className={meta.badge}>{meta.label}</Badge>
                    </div>
                    {m.description && <p className="mt-1 text-[13px] text-ink-500">{m.description}</p>}
                  </div>
                  {renderActions && <div className="flex shrink-0 items-center gap-1">{renderActions(m)}</div>}
                </div>

                <dl className="mt-3 grid gap-x-6 gap-y-2 text-[12.5px] sm:grid-cols-3">
                  <div>
                    <dt className="flex items-center gap-1 text-ink-500"><CalendarDays className="size-3.5" /> Planned</dt>
                    <dd className="font-semibold text-ink-900">{m.planned_start || m.due_date ? `${m.planned_start ? formatDate(m.planned_start, { day: 'numeric', month: 'short' }) : '?'} → ${m.due_date ? formatDate(m.due_date, { day: 'numeric', month: 'short' }) : '?'}` : 'No dates set'}{m.planned_days !== null && <span className="font-medium text-ink-500"> · {days(m.planned_days)}</span>}</dd>
                  </div>
                  <div>
                    <dt className="flex items-center gap-1 text-ink-500"><Timer className="size-3.5" /> Actual</dt>
                    <dd className="font-semibold text-ink-900">
                      {m.started_on ? `Started ${formatDate(m.started_on, { day: 'numeric', month: 'short' })}` : 'Not started'}
                      {m.completed_on && ` · done ${formatDate(m.completed_on, { day: 'numeric', month: 'short' })}`}
                    </dd>
                  </div>
                  <div>
                    <dt className="text-ink-500">Time taken</dt>
                    <dd className={cn('font-semibold', over ? 'text-rose-600' : 'text-ink-900')}>{taken !== null ? days(taken) : '—'}</dd>
                  </div>
                </dl>

                {taken !== null && m.planned_days !== null && (
                  <div className="mt-3 flex items-center gap-3">
                    <Progress className="h-1.5 flex-1" value={barPercent} color={over ? STATUS.delayed.bar : meta.bar} />
                    <span className="tabular w-24 shrink-0 text-right text-[11.5px] font-semibold text-ink-500">{taken} of {m.planned_days} days</span>
                  </div>
                )}

                {note && (
                  <p className={cn('mt-2.5 text-[12.5px] font-semibold', note.tone === 'good' ? 'text-emerald-600' : note.tone === 'bad' ? 'text-rose-600' : 'text-ink-500')}>{note.text}</p>
                )}
              </div>
            </li>
          )
        })}
      </ol>
    </div>
  )
}
