import { useRef } from 'react'
import { useMutation } from '@tanstack/react-query'
import { FileText, Plus, Sparkles, Trash2, Upload, X } from 'lucide-react'
import { Alert, Badge, Button, Input } from '@/components/ui'
import { useToast } from '@/context/ToastContext'
import { errorMessage } from '@/lib/api'
import { trackerApi } from '@/lib/endpoints'
import { cn, formatDate, sequenceWorkingDays } from '@/lib/utils'
import type { OnboardingAnalysis, OnboardingStep } from '@/types/tracker'

const COMPLEXITY: Record<OnboardingAnalysis['estimate']['complexity'], string> = {
  low: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
  medium: 'bg-amber-50 text-amber-700 ring-amber-200',
  high: 'bg-rose-50 text-rose-700 ring-rose-200',
}

/** Upload box for a requirement document. The AI reads it and the parent form is filled in for review. */
export function DocumentUpload({ onAnalysed }: { onAnalysed: (analysis: OnboardingAnalysis) => void }) {
  const toast = useToast()
  const input = useRef<HTMLInputElement>(null)
  const analyse = useMutation({
    mutationFn: (file: File) => trackerApi.analyseDocument(file),
    onSuccess: onAnalysed,
    onError: (err) => toast.error(errorMessage(err)),
  })

  return (
    <div className="rounded-xl border border-dashed border-brand-300 bg-brand-50/40 p-4">
      <div className="flex flex-wrap items-center gap-3">
        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-100 text-brand-700"><Sparkles className="size-5" /></span>
        <div className="min-w-0 flex-1">
          <div className="text-[13.5px] font-bold text-ink-900">Have a requirements document?</div>
          <p className="text-[12.5px] text-ink-500">Upload a PDF, DOCX or text file. AI fills in this form, estimates the working days and drafts the steps for you to review.</p>
        </div>
        <input
          ref={input}
          type="file"
          accept=".pdf,.docx,.txt,.md"
          className="sr-only"
          aria-label="Project document"
          onChange={(e) => {
            const file = e.target.files?.[0]
            e.target.value = ''
            if (file) analyse.mutate(file)
          }}
        />
        <Button variant="secondary" size="sm" icon={<Upload className="size-3.5" />} loading={analyse.isPending} onClick={() => input.current?.click()}>
          {analyse.isPending ? 'Reading document…' : 'Upload document'}
        </Button>
      </div>
      {analyse.isPending && <p className="mt-3 text-[12.5px] text-ink-500">This can take up to a minute for a long document.</p>}
    </div>
  )
}

/** What the AI found, plus the proposed steps to edit before the project is created. */
export function AnalysisReview({
  analysis, steps, onStepsChange, onDiscard, start, totalDays,
}: {
  analysis: OnboardingAnalysis
  steps: OnboardingStep[]
  onStepsChange: (steps: OnboardingStep[]) => void
  onDiscard: () => void
  start: string
  totalDays: number
}) {
  const { extracted, estimate } = analysis
  const sum = steps.reduce((n, s) => n + (Number.isFinite(s.working_days) ? s.working_days : 0), 0)
  const dates = start && sum === totalDays ? sequenceWorkingDays(start, steps.map((s) => s.working_days)) : null

  const update = (i: number, patch: Partial<OnboardingStep>) => onStepsChange(steps.map((s, idx) => (idx === i ? { ...s, ...patch } : s)))

  return (
    <div className="space-y-4 rounded-xl border border-line bg-canvas p-4">
      <div className="flex flex-wrap items-center gap-2">
        <FileText className="size-4 text-ink-400" />
        <span className="min-w-0 flex-1 truncate text-[13px] font-semibold text-ink-900">{analysis.file}</span>
        <Badge className={analysis.source === 'deepseek' ? 'bg-brand-50 text-brand-700 ring-brand-200' : 'bg-slate-100 text-slate-600 ring-slate-200'}>
          {analysis.source === 'deepseek' ? 'AI reading' : 'Basic reading'}
        </Badge>
        <Badge className={cn('capitalize', COMPLEXITY[estimate.complexity])}>{estimate.complexity} complexity</Badge>
        <button type="button" onClick={onDiscard} className="rounded-lg p-1.5 text-ink-400 hover:bg-slate-100 hover:text-ink-700" aria-label="Discard the AI suggestions"><X className="size-4" /></button>
      </div>

      <Alert tone="info">
        This is an initial estimate, not a promised date{analysis.source === 'fallback' ? ', and the AI service was not available, so the document was read with simple rules. Check every field' : ''}.
        {' '}It counts Monday to Friday and does not know your holidays or team availability.
      </Alert>

      <dl className="grid gap-x-6 gap-y-3 text-[13px] sm:grid-cols-2">
        <Fact label="Project type" value={extracted.project_type || 'Not stated'} />
        <Fact label="Confidence" value={`${analysis.confidence}%`} />
        <ListFact label="Technologies" items={extracted.technologies} chips />
        <ListFact label="Modules" items={extracted.modules.map((m) => m.name)} chips />
        <ListFact label="Deliverables" items={extracted.deliverables} />
        <ListFact label="Risks and assumptions" items={[...estimate.risks, ...estimate.assumptions]} />
      </dl>

      <div>
        <div className="mb-2 flex items-baseline justify-between gap-3">
          <h4 className="text-[13px] font-bold text-ink-900">Proposed steps</h4>
          <span className="text-[12px] text-ink-500">{sum} working days in steps · project plan {totalDays || '–'}</span>
        </div>
        <ol className="space-y-2">
          {steps.map((step, i) => (
            <li key={i} className="grid grid-cols-[1.75rem_minmax(0,1fr)_5.5rem_2rem] items-center gap-2">
              <span className="text-center text-[12px] font-bold text-ink-400">{i + 1}</span>
              <div className="min-w-0">
                <Input value={step.title} onChange={(e) => update(i, { title: e.target.value })} aria-label={`Step ${i + 1} title`} className="h-9" />
                {dates && <div className="mt-0.5 pl-1 text-[11.5px] text-ink-500">{formatDate(dates[i].from, { day: 'numeric', month: 'short' })} to {formatDate(dates[i].to, { day: 'numeric', month: 'short' })}</div>}
              </div>
              <Input type="number" min={1} max={1000} value={step.working_days || ''} onChange={(e) => update(i, { working_days: Number(e.target.value) })} aria-label={`Step ${i + 1} working days`} className="h-9" />
              <button type="button" disabled={steps.length <= 1} onClick={() => onStepsChange(steps.filter((_, idx) => idx !== i))} className="rounded-lg p-1.5 text-ink-400 hover:bg-slate-100 hover:text-rose-600 disabled:opacity-40" aria-label={`Remove step ${i + 1}`}>
                <Trash2 className="size-4" />
              </button>
            </li>
          ))}
        </ol>
        <div className="mt-2 flex flex-wrap items-center justify-between gap-2">
          <Button type="button" size="sm" variant="secondary" icon={<Plus className="size-3.5" />} disabled={steps.length >= 12} onClick={() => onStepsChange([...steps, { title: '', description: '', stage: null, working_days: 1, deliverables: [] }])}>Add step</Button>
          {sum !== totalDays && totalDays > 0 && <span className="text-[12px] text-ink-500">Steps will be scaled to fit the project's dates when you save.</span>}
        </div>
      </div>
    </div>
  )
}

function Fact({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <dt className="text-[11px] font-bold tracking-wider text-ink-400 uppercase">{label}</dt>
      <dd className="mt-0.5 text-ink-900">{value}</dd>
    </div>
  )
}

function ListFact({ label, items, chips = false }: { label: string; items: string[]; chips?: boolean }) {
  return (
    <div className="min-w-0">
      <dt className="text-[11px] font-bold tracking-wider text-ink-400 uppercase">{label}</dt>
      <dd className="mt-0.5 text-ink-700">
        {items.length === 0 ? <span className="text-ink-400">Not stated</span> : chips ? (
          <span className="flex flex-wrap gap-1.5">{items.map((i) => <span key={i} className="rounded-md bg-surface px-2 py-0.5 text-[12px] ring-1 ring-line">{i}</span>)}</span>
        ) : (
          <ul className="list-disc space-y-0.5 pl-4">{items.slice(0, 5).map((i) => <li key={i}>{i}</li>)}{items.length > 5 && <li className="list-none text-ink-400">+{items.length - 5} more</li>}</ul>
        )}
      </dd>
    </div>
  )
}
