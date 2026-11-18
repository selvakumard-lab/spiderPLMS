import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Alert, Button, Card, CardHeader, Field, Input, Progress } from '@/components/ui'
import { STATUS_META, TrackingBadge } from '@/components/tracker/shared'
import { useToast } from '@/context/ToastContext'
import { errorMessage } from '@/lib/api'
import { trackerApi } from '@/lib/endpoints'
import { formatDate, formatDateTime } from '@/lib/utils'
import type { ProjectSummary, TrackedProject } from '@/types/tracker'

function Item({ label, value, tone }: { label: string; value: React.ReactNode; tone?: 'danger' }) {
  return (
    <div className="rounded-xl border border-line px-4 py-3">
      <div className="text-xs font-semibold text-ink-500">{label}</div>
      <div className={`mt-0.5 text-[17px] font-bold ${tone === 'danger' ? 'text-rose-600' : 'text-ink-900'}`}>{value}</div>
    </div>
  )
}

/** Bottom-of-page summary: timeline comparison, time used, pending work and the final status. */
export function OverallStatus({ project, summary }: { project: TrackedProject; summary: ProjectSummary }) {
  const toast = useToast()
  const queryClient = useQueryClient()
  const [revised, setRevised] = useState(project.revised_due_date ?? '')

  const adjust = useMutation({
    mutationFn: () => trackerApi.updateProject(project.id, { revised_due_date: revised || null }),
    onSuccess: () => {
      toast.success('Deadline updated.')
      queryClient.invalidateQueries({ queryKey: ['tracker'] })
    },
    onError: (err) => toast.error(errorMessage(err)),
  })

  const clientCaused = summary.client_delay_days > 0 && summary.client_delay_days >= summary.internal_delay_days
  const delayed = summary.status !== 'on_time' && summary.status !== 'completed'

  return (
    <Card>
      <CardHeader title="Overall project status" subtitle={`Last updated ${formatDateTime(summary.status_updated_at)}`} action={<TrackingBadge status={summary.status} />} />
      <div className="space-y-6 p-5 sm:p-6">
        <div>
          <div className="mb-1.5 flex justify-between text-[13px] font-semibold text-ink-700"><span>Overall progress</span><span>{summary.progress_percent}%</span></div>
          <Progress className="h-2.5" value={summary.progress_percent} color={STATUS_META[summary.status].bar} />
        </div>

        {delayed && (
          <Alert tone="info">
            {summary.client_delay_days === 0 && summary.internal_delay_days === 0
              ? 'Behind schedule. No client wait is recorded, so this is internal work.'
              : `Delay breakdown: ${summary.internal_delay_days} working day(s) internal, ${summary.client_delay_days} day(s) waiting on the client. Mostly ${clientCaused ? 'client-side' : 'internal'}.`}
          </Alert>
        )}

        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
          <Item label="Original start date" value={formatDate(summary.original_start_date)} />
          <Item label="Original completion" value={formatDate(summary.original_due_date)} />
          <Item label="Revised completion" value={summary.revised_due_date ? formatDate(summary.revised_due_date) : 'Not revised'} />
          <Item label="Total working days" value={summary.total_working_days ?? '—'} />
          <Item label="Actual days spent" value={summary.days_spent} />
          <Item label="Total time spent" value={`${summary.hours_spent} h`} />
          <Item label="Remaining working days" value={summary.remaining_working_days} tone={summary.overdue_working_days > 0 ? 'danger' : undefined} />
          <Item label="Past deadline (working days)" value={summary.overdue_working_days} tone={summary.overdue_working_days > 0 ? 'danger' : undefined} />
          <Item label="Completed activities" value={summary.completed_activities} />
          <Item label="Pending activities" value={summary.pending_activities} />
          <Item label="Client-side pending items" value={summary.client_pending_items} />
          <Item label="Requirements awaiting approval" value={summary.pending_requirements} />
        </div>

        <div className="rounded-xl border border-line p-4">
          <div className="text-[13px] font-bold text-ink-900">Adjust the completion date</div>
          <p className="mt-0.5 text-xs text-ink-500">
            Approved requirements add {summary.additional_days_approved} working day(s) automatically. You can also set the revised date by hand; the original date is always kept.
          </p>
          <div className="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end">
            <div className="sm:w-56"><Field label="Revised completion date" htmlFor="revised"><Input id="revised" type="date" value={revised} onChange={(e) => setRevised(e.target.value)} /></Field></div>
            <Button variant="secondary" loading={adjust.isPending} onClick={() => adjust.mutate()}>Save revised date</Button>
          </div>
        </div>
      </div>
    </Card>
  )
}
