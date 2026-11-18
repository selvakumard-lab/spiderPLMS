import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Check, ListPlus, Pencil, Play, Plus, RotateCcw, Trash2, Undo2 } from 'lucide-react'
import { Alert, Button, Card, CardHeader, DatePicker, EmptyState, Field, Input, Modal, Select } from '@/components/ui'
import { ConfirmButton, Textarea } from '@/components/ui/extra'
import { ProjectPlan } from '@/components/tracker/ProjectPlan'
import { useToast } from '@/context/ToastContext'
import { errorMessage, fieldErrors } from '@/lib/api'
import { trackerApi } from '@/lib/endpoints'
import type { Milestone, ProjectPlan as Plan } from '@/types/tracker'

const blank = { title: '', stage: '', description: '', planned_start: '', due_date: '' }

/** The project plan for the team: every step from kickoff to delivery, with controls to start, finish, re-date or add steps. */
export function PlanSection({ projectId, plan, stages }: { projectId: number; plan: Plan; stages: { value: string; label: string }[] }) {
  const toast = useToast()
  const queryClient = useQueryClient()
  const [editing, setEditing] = useState<Milestone | 'new' | null>(null)
  const [form, setForm] = useState(blank)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [error, setError] = useState<string | null>(null)

  const refresh = () => queryClient.invalidateQueries({ queryKey: ['tracker'] })
  const set = (key: keyof typeof blank, value: string) => setForm((f) => ({ ...f, [key]: value }))

  function open(target: Milestone | 'new') {
    setErrors({})
    setError(null)
    setForm(target === 'new' ? blank : { title: target.title, stage: target.stage ?? '', description: target.description ?? '', planned_start: target.planned_start ?? '', due_date: target.due_date ?? '' })
    setEditing(target)
  }

  const onError = (err: unknown) => {
    const fe = fieldErrors(err)
    setErrors(fe)
    if (!Object.keys(fe).length) setError(errorMessage(err))
    else toast.error(Object.values(fe)[0])
  }

  const save = useMutation({
    mutationFn: () => {
      const body = { ...form, stage: form.stage || null, planned_start: form.planned_start || null, due_date: form.due_date || null, description: form.description || null }
      return editing === 'new' ? trackerApi.addMilestone(projectId, body) : trackerApi.updateMilestone((editing as Milestone).id, body)
    },
    onSuccess: () => {
      toast.success(editing === 'new' ? 'Step added. The client has been told.' : 'Step updated.')
      setEditing(null)
      refresh()
    },
    onError,
  })

  const move = useMutation({
    mutationFn: ({ step, status }: { step: Milestone; status: 'pending' | 'in_progress' | 'completed' }) => trackerApi.updateMilestone(step.id, { status }),
    onSuccess: () => refresh(),
    onError: (err) => toast.error(errorMessage(err)),
  })

  const generate = useMutation({
    mutationFn: () => trackerApi.generateMilestones(projectId),
    onSuccess: () => {
      toast.success('Step plan created from the project dates.')
      refresh()
    },
    onError: (err) => toast.error(errorMessage(err)),
  })

  const busy = (step: Milestone) => move.isPending && move.variables?.step.id === step.id

  return (
    <Card>
      <CardHeader title="Project plan" subtitle="Every step from kickoff to delivery, with its deadline and how long it really took"
        action={plan.milestones.length > 0 ? <Button size="sm" icon={<Plus className="size-4" />} onClick={() => open('new')}>Add step</Button> : undefined} />
      <div className="p-5 sm:p-6">
        {plan.milestones.length === 0 ? (
          <EmptyState icon={<ListPlus className="size-6" />} title="No step plan yet" description="Create the standard steps (Requirements to Handover), spread across your project dates. You can edit or add to them afterwards."
            action={<Button loading={generate.isPending} onClick={() => generate.mutate()}>Create step plan</Button>} />
        ) : (
          <ProjectPlan plan={plan} renderActions={(step) => (
            <>
              {step.status === 'pending' && <Button size="sm" variant="secondary" icon={<Play className="size-3.5" />} loading={busy(step)} onClick={() => move.mutate({ step, status: 'in_progress' })}>Start</Button>}
              {step.status === 'in_progress' && <Button size="sm" icon={<Check className="size-3.5" />} loading={busy(step)} onClick={() => move.mutate({ step, status: 'completed' })}>Complete</Button>}
              {step.status === 'completed' && <Button size="sm" variant="ghost" icon={<RotateCcw className="size-3.5" />} loading={busy(step)} onClick={() => move.mutate({ step, status: 'in_progress' })}>Reopen</Button>}
              {step.status === 'in_progress' && <Button size="sm" variant="ghost" aria-label="Move back to pending" title="Move back to pending" disabled={busy(step)} onClick={() => move.mutate({ step, status: 'pending' })}><Undo2 className="size-4" /></Button>}
              <Button size="sm" variant="ghost" aria-label="Edit step" onClick={() => open(step)}><Pencil className="size-4" /></Button>
              <ConfirmButton title="Delete step" message={`Remove "${step.title}" from the plan?`} confirmLabel="Delete" danger onConfirm={async () => { await trackerApi.deleteMilestone(step.id); refresh() }}
                trigger={(openConfirm) => <Button size="sm" variant="ghost" aria-label="Delete step" onClick={openConfirm}><Trash2 className="size-4" /></Button>} />
            </>
          )} />
        )}
      </div>

      <Modal open={editing !== null} onClose={() => setEditing(null)} size="lg" title={editing === 'new' ? 'Add a step' : 'Edit step'} description="The client sees every step and its deadline in their portal."
        footer={<><Button variant="secondary" onClick={() => setEditing(null)}>Cancel</Button><Button loading={save.isPending} onClick={() => save.mutate()}>Save step</Button></>}>
        <div className="space-y-4">
          {error && <Alert>{error}</Alert>}
          <Field label="Step" htmlFor="m-title" error={errors.title}><Input id="m-title" autoFocus value={form.title} invalid={!!errors.title} onChange={(e) => set('title', e.target.value)} placeholder="e.g. Client sign-off" /></Field>
          <div className="grid gap-4 sm:grid-cols-3">
            <Field label="Stage" htmlFor="m-stage" hint="Starting this step moves the project to it" error={errors.stage}>
              <Select id="m-stage" value={form.stage} onChange={(e) => set('stage', e.target.value)}>
                <option value="">No stage</option>
                {stages.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
              </Select>
            </Field>
            <Field label="Planned start" htmlFor="m-start" error={errors.planned_start}><DatePicker id="m-start" value={form.planned_start} onChange={(e) => set('planned_start', e.target.value)} /></Field>
            <Field label="Deadline" htmlFor="m-due" error={errors.due_date}><DatePicker id="m-due" value={form.due_date} invalid={!!errors.due_date} onChange={(e) => set('due_date', e.target.value)} /></Field>
          </div>
          <Field label="What happens in this step" htmlFor="m-desc" error={errors.description}><Textarea id="m-desc" rows={3} value={form.description} onChange={(e) => set('description', e.target.value)} /></Field>
        </div>
      </Modal>
    </Card>
  )
}
