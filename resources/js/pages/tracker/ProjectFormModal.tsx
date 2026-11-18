import { useEffect, useState, type FormEvent } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useQuery } from '@tanstack/react-query'
import { Alert, Button, DatePicker, Field, Input, Modal, Select } from '@/components/ui'
import { Textarea } from '@/components/ui/extra'
import { useToast } from '@/context/ToastContext'
import { errorMessage, fieldErrors } from '@/lib/api'
import { clientsApi, trackerApi } from '@/lib/endpoints'
import { todayIso, workingDayNumber } from '@/lib/utils'
import type { OnboardingAnalysis, OnboardingStep, TrackedProject } from '@/types/tracker'
import { AnalysisReview, DocumentUpload } from './DocumentOnboarding'

const blank = { name: '', client_id: '', client_name: '', description: '', start_date: '', due_date: '', total_working_days: '' }

/** Project onboarding form (create) — also used to edit an existing project. */
export function ProjectFormModal({ open, onClose, project, onSaved }: { open: boolean; onClose: () => void; project?: TrackedProject; onSaved?: (p: TrackedProject) => void }) {
  const toast = useToast()
  const queryClient = useQueryClient()
  const [form, setForm] = useState(blank)
  const { data: clients } = useQuery({ queryKey: ['clients', ''], queryFn: () => clientsApi.list(), enabled: open })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [error, setError] = useState<string | null>(null)
  const [analysis, setAnalysis] = useState<OnboardingAnalysis | null>(null)
  const [steps, setSteps] = useState<OnboardingStep[]>([])

  useEffect(() => {
    if (!open) return
    setErrors({})
    setError(null)
    setAnalysis(null)
    setSteps([])
    setForm(project ? {
      name: project.name, client_id: project.client_id?.toString() ?? '', client_name: project.client_name ?? '', description: project.description ?? '',
      start_date: project.start_date ?? '', due_date: project.due_date ?? '', total_working_days: project.total_working_days?.toString() ?? '',
    } : blank)
  }, [open, project])

  /** With an AI plan, the end date always follows the start date and the working days. */
  const set = (key: keyof typeof blank, value: string) =>
    setForm((f) => {
      const next = { ...f, [key]: value }
      if (analysis && (key === 'start_date' || key === 'total_working_days') && next.start_date && Number(next.total_working_days) > 0) {
        next.due_date = workingDayNumber(next.start_date, Number(next.total_working_days))
      }
      return next
    })

  /** Fills the form from what the AI read. Everything stays editable. */
  function applyAnalysis(result: OnboardingAnalysis) {
    const { extracted, estimate } = result
    const start = form.start_date || todayIso()
    const days = estimate.working_days
    const matched = (clients ?? []).find((c) => c.name.trim().toLowerCase() === extracted.client_name.trim().toLowerCase() && extracted.client_name !== '')
    const sections = [
      extracted.summary,
      extracted.scope.length ? `Scope:\n${extracted.scope.map((s) => `- ${s}`).join('\n')}` : '',
      extracted.deliverables.length ? `Deliverables:\n${extracted.deliverables.map((s) => `- ${s}`).join('\n')}` : '',
      extracted.technologies.length ? `Technologies: ${extracted.technologies.join(', ')}` : '',
    ].filter(Boolean)

    setAnalysis(result)
    setSteps(estimate.steps)
    setForm((f) => ({
      ...f,
      name: f.name || extracted.project_name,
      client_id: f.client_id || (matched ? String(matched.id) : ''),
      client_name: f.client_id || matched ? '' : f.client_name || extracted.client_name,
      description: sections.join('\n\n').slice(0, 5000),
      start_date: start,
      total_working_days: String(days),
      due_date: workingDayNumber(start, days),
    }))
    toast.success('Document read. Review the details below before you add the project.')
  }

  /** Editing the steps changes the plan length, and with it the end date. */
  function changeSteps(next: OnboardingStep[]) {
    setSteps(next)
    const days = next.reduce((n, s) => n + (Number.isFinite(s.working_days) ? Math.max(0, s.working_days) : 0), 0)
    if (days > 0) {
      setForm((f) => ({ ...f, total_working_days: String(days), due_date: f.start_date ? workingDayNumber(f.start_date, days) : f.due_date }))
    }
  }

  const save = useMutation({
    mutationFn: () => {
      const body = {
        ...form,
        client_id: form.client_id ? Number(form.client_id) : null,
        total_working_days: form.total_working_days ? Number(form.total_working_days) : null,
        ...(!project && analysis ? { analysis_id: analysis.id, steps: steps.filter((s) => s.title.trim()).map((s) => ({ ...s, working_days: Math.max(1, Math.round(s.working_days || 1)) })) } : {}),
      }
      return project ? trackerApi.updateProject(project.id, body) : trackerApi.createProject(body)
    },
    onSuccess: (saved) => {
      queryClient.invalidateQueries({ queryKey: ['tracker'] })
      toast.success(project ? 'Project updated.' : 'Project added.')
      onSaved?.(saved)
      onClose()
    },
    onError: (err) => {
      setErrors(fieldErrors(err))
      if (!Object.keys(fieldErrors(err)).length) setError(errorMessage(err))
    },
  })

  function submit(e: FormEvent) {
    e.preventDefault()
    save.mutate()
  }

  return (
    <Modal open={open} onClose={onClose} size="lg" title={project ? 'Edit project' : 'Onboard a new project'} description={project ? 'Basic details. You can log activities and meetings once it is created.' : 'Fill in the basics, or upload a requirements document and let AI draft the plan. You review everything before it is created.'}
      footer={<><Button variant="secondary" onClick={onClose}>Cancel</Button><Button loading={save.isPending} onClick={() => save.mutate()}>{project ? 'Save changes' : 'Add project'}</Button></>}>
      <form onSubmit={submit} className="space-y-4" noValidate>
        {error && <Alert>{error}</Alert>}
        {!project && (analysis ? (
          <AnalysisReview analysis={analysis} steps={steps} onStepsChange={changeSteps} start={form.start_date} totalDays={Number(form.total_working_days) || 0}
            onDiscard={() => { setAnalysis(null); setSteps([]) }} />
        ) : <DocumentUpload onAnalysed={applyAnalysis} />)}
        <Field label="Project name" htmlFor="p-name" error={errors.name}>
          <Input id="p-name" autoFocus value={form.name} invalid={!!errors.name} onChange={(e) => set('name', e.target.value)} />
        </Field>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Client" htmlFor="p-client-id" error={errors.client_id} hint={clients && !clients.length ? 'No clients yet. Add them on the Clients page.' : 'Needed to send reminders by email or WhatsApp'}>
            <Select id="p-client-id" value={form.client_id} onChange={(e) => set('client_id', e.target.value)}>
              <option value="">No client selected</option>
              {(clients ?? []).map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
            </Select>
          </Field>
          <Field label="Client name (if not in the list)" htmlFor="p-client" error={errors.client_name}>
            <Input id="p-client" value={form.client_name} disabled={!!form.client_id} onChange={(e) => set('client_name', e.target.value)} />
          </Field>
        </div>
        <Field label="Details" htmlFor="p-desc" error={errors.description}>
          <Textarea id="p-desc" rows={3} value={form.description} onChange={(e) => set('description', e.target.value)} />
        </Field>
        <div className="grid gap-4 sm:grid-cols-3">
          <Field label="Start date" htmlFor="p-start" error={errors.start_date}>
            <DatePicker id="p-start" value={form.start_date} invalid={!!errors.start_date} onChange={(e) => set('start_date', e.target.value)} />
          </Field>
          <Field label="Expected completion" htmlFor="p-due" error={errors.due_date}>
            <DatePicker id="p-due" value={form.due_date} invalid={!!errors.due_date} onChange={(e) => set('due_date', e.target.value)} />
          </Field>
          <Field label="Total working days" htmlFor="p-days" error={errors.total_working_days}>
            <Input id="p-days" type="number" min={1} value={form.total_working_days} onChange={(e) => set('total_working_days', e.target.value)} />
          </Field>
        </div>
      </form>
    </Modal>
  )
}
