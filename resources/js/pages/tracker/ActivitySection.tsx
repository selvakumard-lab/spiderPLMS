import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Pencil, Plus, Sparkles, Trash2 } from 'lucide-react'
import { Alert, Badge, Button, Card, CardHeader, DatePicker, EmptyState, Field, Input, Modal, Progress, Select } from '@/components/ui'
import { ConfirmButton, Textarea } from '@/components/ui/extra'
import { todayISO } from '@/components/tracker/shared'
import { useToast } from '@/context/ToastContext'
import { errorMessage, fieldErrors } from '@/lib/api'
import { trackerApi } from '@/lib/endpoints'
import { cn, formatDate } from '@/lib/utils'
import type { Activity, ActivityStatus } from '@/types/tracker'

const STATUS_STYLE: Record<ActivityStatus, string> = {
  pending: 'bg-slate-100 text-ink-600 ring-slate-200',
  in_progress: 'bg-amber-50 text-amber-700 ring-amber-200',
  completed: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
}
const STATUS_LABEL: Record<ActivityStatus, string> = { pending: 'Pending', in_progress: 'In progress', completed: 'Completed' }

const blank = () => ({ activity_date: todayISO(), title: '', description: '', hours_spent: '', days_spent: '', estimated_hours: '', status: 'in_progress' as ActivityStatus, progress_percent: '' })

/** Chronological work log. Each save triggers the AI estimate for the next step. */
export function ActivitySection({ projectId, activities, lastProgress }: { projectId: number; activities: Activity[]; lastProgress: number }) {
  const toast = useToast()
  const queryClient = useQueryClient()
  const [editing, setEditing] = useState<Activity | 'new' | null>(null)
  const [form, setForm] = useState(blank())
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [error, setError] = useState<string | null>(null)

  const refresh = () => queryClient.invalidateQueries({ queryKey: ['tracker'] })

  function open(target: Activity | 'new') {
    setErrors({})
    setError(null)
    setForm(target === 'new'
      ? { ...blank(), progress_percent: String(lastProgress) }
      : {
          activity_date: target.activity_date, title: target.title, description: target.description ?? '', hours_spent: String(target.hours_spent),
          days_spent: String(target.days_spent), estimated_hours: target.estimated_hours?.toString() ?? '', status: target.status, progress_percent: String(target.progress_percent),
        })
    setEditing(target)
  }

  const save = useMutation({
    mutationFn: () => {
      const body = {
        ...form,
        hours_spent: form.hours_spent === '' ? null : Number(form.hours_spent),
        days_spent: form.days_spent === '' ? null : Number(form.days_spent),
        estimated_hours: form.estimated_hours === '' ? null : Number(form.estimated_hours),
        progress_percent: form.progress_percent === '' ? null : Number(form.progress_percent),
      }
      return editing === 'new' ? trackerApi.addActivity(projectId, body) : trackerApi.updateActivity((editing as Activity).id, body)
    },
    onSuccess: () => {
      toast.success('Activity saved and re-analysed.')
      setEditing(null)
      refresh()
    },
    onError: (err) => {
      setErrors(fieldErrors(err))
      if (!Object.keys(fieldErrors(err)).length) setError(errorMessage(err))
    },
  })

  const set = (key: keyof ReturnType<typeof blank>, value: string) => setForm((f) => ({ ...f, [key]: value }))

  return (
    <Card>
      <CardHeader title="Activity tracking" subtitle="What was done on each date, and how long it took" action={<Button size="sm" icon={<Plus className="size-4" />} onClick={() => open('new')}>Add activity</Button>} />
      <div className="p-5 sm:p-6">
        {activities.length === 0 ? (
          <EmptyState icon={<Plus className="size-6" />} title="No activity yet" description="Log the first piece of work to start tracking time and progress." action={<Button onClick={() => open('new')}>Add activity</Button>} />
        ) : (
          <ol className="relative space-y-5 border-l-2 border-line pl-5">
            {activities.map((a) => (
              <li key={a.id} className="relative">
                <span className={cn('absolute top-1.5 -left-[27px] size-3 rounded-full ring-4 ring-white', a.status === 'completed' ? 'bg-emerald-500' : a.status === 'in_progress' ? 'bg-amber-500' : 'bg-slate-300')} />
                <div className="rounded-xl border border-line p-4">
                  <div className="flex flex-wrap items-start justify-between gap-2">
                    <div>
                      <div className="text-xs font-semibold text-ink-500">{formatDate(a.activity_date)}</div>
                      <h4 className="text-[15px] font-bold text-ink-900">{a.title}</h4>
                    </div>
                    <div className="flex items-center gap-1">
                      <Badge className={STATUS_STYLE[a.status]}>{STATUS_LABEL[a.status]}</Badge>
                      <Button size="sm" variant="ghost" aria-label="Edit activity" onClick={() => open(a)}><Pencil className="size-4" /></Button>
                      <ConfirmButton title="Delete activity" message="This removes the activity and its time from the project." confirmLabel="Delete" danger onConfirm={async () => { await trackerApi.deleteActivity(a.id); refresh() }}
                        trigger={(openConfirm) => <Button size="sm" variant="ghost" aria-label="Delete activity" onClick={openConfirm}><Trash2 className="size-4" /></Button>} />
                    </div>
                  </div>
                  {a.description && <p className="mt-2 text-[13.5px] whitespace-pre-line text-ink-700">{a.description}</p>}
                  <dl className="mt-3 grid grid-cols-2 gap-3 text-[13px] sm:grid-cols-4">
                    <div><dt className="text-ink-500">Time taken</dt><dd className="font-bold text-ink-900">{a.hours_spent} h</dd></div>
                    <div><dt className="text-ink-500">Days spent</dt><dd className="font-bold text-ink-900">{a.days_spent}</dd></div>
                    <div><dt className="text-ink-500">Estimated</dt><dd className={cn('font-bold', a.estimated_hours !== null && a.hours_spent > a.estimated_hours ? 'text-rose-600' : 'text-ink-900')}>{a.estimated_hours !== null ? `${a.estimated_hours} h` : '—'}</dd></div>
                    <div><dt className="text-ink-500">Progress after</dt><dd className="font-bold text-ink-900">{a.progress_percent}%</dd></div>
                  </dl>
                  <Progress className="mt-3" value={a.progress_percent} />
                  {(a.next_step_estimate_hours !== null || a.ai_analysis) && (
                    <div className="mt-3 flex gap-2 rounded-lg bg-brand-50 px-3 py-2 text-[13px] text-brand-900">
                      <Sparkles className="mt-0.5 size-4 shrink-0 text-brand-600" />
                      <div>
                        {a.next_step_estimate_hours !== null && <span className="font-bold">Next step estimate: {a.next_step_estimate_hours} h. </span>}
                        {a.ai_analysis}
                      </div>
                    </div>
                  )}
                </div>
              </li>
            ))}
          </ol>
        )}
      </div>

      <Modal open={editing !== null} onClose={() => setEditing(null)} size="lg" title={editing === 'new' ? 'Add activity' : 'Edit activity'} description="Saving runs the AI analysis for this project."
        footer={<><Button variant="secondary" onClick={() => setEditing(null)}>Cancel</Button><Button loading={save.isPending} onClick={() => save.mutate()}>Save activity</Button></>}>
        <div className="space-y-4">
          {error && <Alert>{error}</Alert>}
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Date" htmlFor="a-date" error={errors.activity_date}><DatePicker id="a-date" value={form.activity_date} onChange={(e) => set('activity_date', e.target.value)} /></Field>
            <Field label="Status" htmlFor="a-status">
              <Select id="a-status" value={form.status} onChange={(e) => set('status', e.target.value)}>
                <option value="pending">Pending</option><option value="in_progress">In progress</option><option value="completed">Completed</option>
              </Select>
            </Field>
          </div>
          <Field label="Activity" htmlFor="a-title" error={errors.title}><Input id="a-title" autoFocus value={form.title} invalid={!!errors.title} onChange={(e) => set('title', e.target.value)} placeholder="e.g. Homepage design" /></Field>
          <Field label="Work completed" htmlFor="a-desc" error={errors.description}><Textarea id="a-desc" rows={3} value={form.description} onChange={(e) => set('description', e.target.value)} /></Field>
          <div className="grid gap-4 sm:grid-cols-3">
            <Field label="Hours spent" htmlFor="a-hours" error={errors.hours_spent}><Input id="a-hours" type="number" min={0} step="0.25" value={form.hours_spent} onChange={(e) => set('hours_spent', e.target.value)} /></Field>
            <Field label="Days spent" htmlFor="a-days" hint="Blank = hours ÷ 8" error={errors.days_spent}><Input id="a-days" type="number" min={0} step="0.25" value={form.days_spent} onChange={(e) => set('days_spent', e.target.value)} /></Field>
            <Field label="Estimated hours" htmlFor="a-est" error={errors.estimated_hours}><Input id="a-est" type="number" min={0} step="0.25" value={form.estimated_hours} onChange={(e) => set('estimated_hours', e.target.value)} /></Field>
          </div>
          <Field label="Overall project progress after this step (%)" htmlFor="a-prog" error={errors.progress_percent}>
            <Input id="a-prog" type="number" min={0} max={100} value={form.progress_percent} onChange={(e) => set('progress_percent', e.target.value)} />
          </Field>
        </div>
      </Modal>
    </Card>
  )
}
