import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Check, FilePlus2, Pencil, Plus, RotateCcw, Trash2, X } from 'lucide-react'
import { Alert, Badge, Button, Card, CardHeader, EmptyState, Field, Input, Modal } from '@/components/ui'
import { ConfirmButton, Textarea } from '@/components/ui/extra'
import { todayISO } from '@/components/tracker/shared'
import { useToast } from '@/context/ToastContext'
import { errorMessage, fieldErrors } from '@/lib/api'
import { trackerApi } from '@/lib/endpoints'
import { formatDate } from '@/lib/utils'
import type { ChangeRequest, ChangeStatus } from '@/types/tracker'

const STYLE: Record<ChangeStatus, string> = {
  pending: 'bg-amber-50 text-amber-700 ring-amber-200',
  approved: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
  rejected: 'bg-rose-50 text-rose-700 ring-rose-200',
}
const blank = () => ({ title: '', description: '', submitted_on: todayISO(), additional_days: '', additional_hours: '' })

/** New client requirements, their approval status and the extra time they add. */
export function ChangeSection({ projectId, changes }: { projectId: number; changes: ChangeRequest[] }) {
  const toast = useToast()
  const queryClient = useQueryClient()
  const [editing, setEditing] = useState<ChangeRequest | 'new' | null>(null)
  const [form, setForm] = useState(blank())
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [error, setError] = useState<string | null>(null)

  const refresh = () => queryClient.invalidateQueries({ queryKey: ['tracker'] })
  const set = (key: keyof ReturnType<typeof blank>, value: string) => setForm((f) => ({ ...f, [key]: value }))

  function open(target: ChangeRequest | 'new') {
    setErrors({})
    setError(null)
    setForm(target === 'new' ? blank() : { title: target.title, description: target.description ?? '', submitted_on: target.submitted_on, additional_days: String(target.additional_days), additional_hours: String(target.additional_hours) })
    setEditing(target)
  }

  const save = useMutation({
    mutationFn: () => {
      const body = { ...form, additional_days: Number(form.additional_days || 0), additional_hours: Number(form.additional_hours || 0) }
      return editing === 'new' ? trackerApi.addChange(projectId, body) : trackerApi.updateChange((editing as ChangeRequest).id, body)
    },
    onSuccess: () => {
      toast.success('Requirement saved.')
      setEditing(null)
      refresh()
    },
    onError: (err) => {
      setErrors(fieldErrors(err))
      if (!Object.keys(fieldErrors(err)).length) setError(errorMessage(err))
    },
  })

  const decide = useMutation({
    mutationFn: ({ id, status }: { id: number; status: ChangeStatus }) => trackerApi.updateChange(id, { status }),
    onSuccess: refresh,
    onError: (err) => toast.error(errorMessage(err)),
  })

  return (
    <Card>
      <CardHeader title="New client requirements" subtitle="Extra requests and whether they are approved. Approved days move the deadline." action={<Button size="sm" icon={<Plus className="size-4" />} onClick={() => open('new')}>Add requirement</Button>} />
      <div className="p-5 sm:p-6">
        {changes.length === 0 ? (
          <EmptyState icon={<FilePlus2 className="size-6" />} title="No new requirements" description="When the client asks for something extra, record it here." />
        ) : (
          <ul className="space-y-3">
            {changes.map((c) => (
              <li key={c.id} className="rounded-xl border border-line p-4">
                <div className="flex flex-wrap items-start justify-between gap-2">
                  <div>
                    <h4 className="text-[15px] font-bold text-ink-900">{c.title}</h4>
                    <div className="text-xs font-medium text-ink-500">Submitted {formatDate(c.submitted_on)}{c.decided_on ? ` · ${c.status === 'approved' ? 'Approved' : 'Rejected'} ${formatDate(c.decided_on)}` : ''}</div>
                  </div>
                  <div className="flex items-center gap-1">
                    <Badge className={STYLE[c.status]}>{c.status[0].toUpperCase() + c.status.slice(1)}</Badge>
                    <Button size="sm" variant="ghost" aria-label="Edit requirement" onClick={() => open(c)}><Pencil className="size-4" /></Button>
                    <ConfirmButton title="Delete requirement" message="Remove this requirement? The revised deadline is recalculated." confirmLabel="Delete" danger onConfirm={async () => { await trackerApi.deleteChange(c.id); refresh() }}
                      trigger={(o) => <Button size="sm" variant="ghost" aria-label="Delete requirement" onClick={o}><Trash2 className="size-4" /></Button>} />
                  </div>
                </div>
                {c.description && <p className="mt-2 text-[13.5px] whitespace-pre-line text-ink-700">{c.description}</p>}
                <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
                  <div className="text-[13px] text-ink-500">Additional time: <span className="font-bold text-ink-900">{c.additional_days} working days</span> · <span className="font-bold text-ink-900">{c.additional_hours} h</span></div>
                  <div className="flex gap-2">
                    {c.status !== 'approved' && <Button size="sm" variant="secondary" icon={<Check className="size-3.5" />} onClick={() => decide.mutate({ id: c.id, status: 'approved' })}>Approve</Button>}
                    {c.status !== 'rejected' && <Button size="sm" variant="secondary" icon={<X className="size-3.5" />} onClick={() => decide.mutate({ id: c.id, status: 'rejected' })}>Reject</Button>}
                    {c.status !== 'pending' && <Button size="sm" variant="ghost" icon={<RotateCcw className="size-3.5" />} onClick={() => decide.mutate({ id: c.id, status: 'pending' })}>Reopen</Button>}
                  </div>
                </div>
              </li>
            ))}
          </ul>
        )}
      </div>

      <Modal open={editing !== null} onClose={() => setEditing(null)} title={editing === 'new' ? 'Add new requirement' : 'Edit requirement'}
        footer={<><Button variant="secondary" onClick={() => setEditing(null)}>Cancel</Button><Button loading={save.isPending} onClick={() => save.mutate()}>Save requirement</Button></>}>
        <div className="space-y-4">
          {error && <Alert>{error}</Alert>}
          <Field label="Requirement" htmlFor="c-title" error={errors.title}><Input id="c-title" autoFocus value={form.title} invalid={!!errors.title} onChange={(e) => set('title', e.target.value)} /></Field>
          <Field label="Requested changes" htmlFor="c-desc"><Textarea id="c-desc" rows={3} value={form.description} onChange={(e) => set('description', e.target.value)} /></Field>
          <div className="grid gap-4 sm:grid-cols-3">
            <Field label="Submitted on" htmlFor="c-date" error={errors.submitted_on}><Input id="c-date" type="date" value={form.submitted_on} onChange={(e) => set('submitted_on', e.target.value)} /></Field>
            <Field label="Extra working days" htmlFor="c-days" error={errors.additional_days}><Input id="c-days" type="number" min={0} value={form.additional_days} onChange={(e) => set('additional_days', e.target.value)} /></Field>
            <Field label="Extra hours" htmlFor="c-hours" error={errors.additional_hours}><Input id="c-hours" type="number" min={0} step="0.5" value={form.additional_hours} onChange={(e) => set('additional_hours', e.target.value)} /></Field>
          </div>
        </div>
      </Modal>
    </Card>
  )
}
