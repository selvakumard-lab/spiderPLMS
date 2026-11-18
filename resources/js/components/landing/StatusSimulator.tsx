import { useState } from 'react'
import { CalendarClock, Gauge, RotateCcw } from 'lucide-react'
import { STATUS_META } from '@/components/tracker/shared'
import type { TrackingStatus } from '@/types/tracker'

/** Mirrors the thresholds in App\Services\ProjectTrackerService so the demo tells the truth. */
function verdict(plannedDays: number, elapsedDays: number, progress: number): { status: TrackingStatus; gap: number; behindDays: number; note: string } {
  const elapsed = Math.min(plannedDays, elapsedDays)
  const gap = (elapsed / plannedDays) * 100 - progress
  const overdue = Math.max(0, elapsedDays - plannedDays)

  let status: TrackingStatus = 'on_time'
  if (progress >= 100) status = 'completed'
  else if (overdue > 10 || gap > 30) status = 'significantly_delayed'
  else if (overdue > 0 || gap > 15) status = 'delayed'
  else if (gap > 5) status = 'slightly_delayed'

  const behindDays = status === 'completed' ? 0 : Math.round(Math.max(overdue, (Math.max(0, gap) / 100) * plannedDays))
  const notes: Record<TrackingStatus, string> = {
    on_time: 'Delivery is tracking to plan. Nothing to chase.',
    slightly_delayed: 'Slightly behind. Worth a look before it grows.',
    delayed: 'Clearly behind plan. Talk to the team and the client.',
    significantly_delayed: 'Needs intervention now. Escalate and re-plan.',
    completed: 'Delivered. The report is ready to share.',
  }
  return { status, gap: Math.round(gap), behindDays, note: notes[status] }
}

const DEFAULTS = { planned: 40, elapsed: 24, progress: 48 }

const PRESETS = [
  { label: 'Healthy', planned: 40, elapsed: 20, progress: 55 },
  { label: 'Drifting', planned: 40, elapsed: 24, progress: 48 },
  { label: 'In trouble', planned: 40, elapsed: 32, progress: 38 },
  { label: 'Overdue', planned: 40, elapsed: 52, progress: 82 },
  { label: 'Delivered', planned: 40, elapsed: 38, progress: 100 },
]

function Slider({ label, value, min, max, unit, onChange }: { label: string; value: number; min: number; max: number; unit: string; onChange: (n: number) => void }) {
  const pct = ((value - min) / (max - min)) * 100
  return (
    <label className="block">
      <span className="mb-2 flex items-center justify-between text-[13px] font-semibold text-ink-700">
        {label}
        <span className="tabular rounded-md bg-brand-50 px-2 py-0.5 text-xs font-bold text-brand-700">{value}{unit}</span>
      </span>
      <input
        type="range" min={min} max={max} value={value} onChange={(e) => onChange(Number(e.target.value))}
        className="h-2 w-full cursor-pointer appearance-none rounded-full accent-brand-600"
        style={{ background: `linear-gradient(to right, var(--color-brand-600) ${pct}%, var(--color-slate-200) ${pct}%)` }}
      />
    </label>
  )
}

/** Interactive demo: drag the sliders and watch the tracker's verdict change. */
export function StatusSimulator() {
  const [planned, setPlanned] = useState(DEFAULTS.planned)
  const [elapsed, setElapsed] = useState(DEFAULTS.elapsed)
  const [progress, setProgress] = useState(DEFAULTS.progress)

  const v = verdict(planned, elapsed, progress)
  const meta = STATUS_META[v.status]
  const expected = Math.min(100, Math.round((Math.min(planned, elapsed) / planned) * 100))

  function apply(p: { planned: number; elapsed: number; progress: number }) {
    setPlanned(p.planned)
    setElapsed(p.elapsed)
    setProgress(p.progress)
  }

  return (
    <div className="card grid overflow-hidden lg:grid-cols-[1fr_1.05fr]">
      <div className="space-y-6 p-6 sm:p-8">
        <div className="flex flex-wrap items-center gap-2">
          {PRESETS.map((p) => (
            <button key={p.label} type="button" onClick={() => apply(p)} className="rounded-full border border-line px-3 py-1 text-xs font-semibold text-ink-700 transition hover:border-brand-300 hover:text-brand-600">
              {p.label}
            </button>
          ))}
          <button type="button" onClick={() => apply(DEFAULTS)} className="ml-auto inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-semibold text-ink-500 hover:text-ink-900">
            <RotateCcw className="size-3.5" /> Reset
          </button>
        </div>
        <Slider label="Planned working days" value={planned} min={10} max={120} unit=" d" onChange={setPlanned} />
        <Slider label="Working days elapsed" value={elapsed} min={0} max={150} unit=" d" onChange={setElapsed} />
        <Slider label="Actual progress" value={progress} min={0} max={100} unit="%" onChange={setProgress} />
      </div>

      <div className="relative border-t border-line bg-canvas p-6 sm:p-8 lg:border-t-0 lg:border-l">
        <div className="flex items-center gap-2 text-xs font-bold tracking-wider text-ink-500 uppercase"><Gauge className="size-4 text-brand-600" /> The tracker says</div>
        <div key={v.status} className="mt-4 motion-safe:animate-pop-in">
          <span className={`inline-block rounded-full px-4 py-1.5 text-sm font-bold ring-1 ring-inset ${meta.badge}`}>{meta.label}</span>
          <p className="mt-3 text-[15px] font-medium text-ink-700">{v.note}</p>
        </div>

        <div className="mt-7">
          <div className="mb-1.5 flex justify-between text-xs font-semibold text-ink-500"><span>Actual {progress}%</span><span>Expected by today {expected}%</span></div>
          <div className="relative h-3 rounded-full bg-slate-200">
            <div className="h-full rounded-full transition-all duration-500 ease-out" style={{ width: `${progress}%`, backgroundColor: meta.bar }} />
            <div className="absolute -top-1 h-5 w-0.5 rounded bg-ink-900 transition-all duration-500" style={{ left: `${expected}%` }} title="Where the plan says you should be" />
          </div>
        </div>

        <dl className="mt-7 grid grid-cols-2 gap-3">
          <div className="rounded-xl border border-line bg-surface p-4">
            <dt className="text-xs font-semibold text-ink-500">Behind plan by</dt>
            <dd className="tabular mt-1 text-2xl font-extrabold text-ink-900">{v.behindDays}<span className="ml-1 text-sm font-semibold text-ink-500">days</span></dd>
          </div>
          <div className="rounded-xl border border-line bg-surface p-4">
            <dt className="text-xs font-semibold text-ink-500">Schedule gap</dt>
            <dd className="tabular mt-1 text-2xl font-extrabold text-ink-900">{Math.max(0, v.gap)}<span className="ml-1 text-sm font-semibold text-ink-500">pts</span></dd>
          </div>
        </dl>
        <p className="mt-4 flex items-start gap-2 text-xs text-ink-500"><CalendarClock className="mt-0.5 size-3.5 shrink-0" /> Same rules as the live tracker: over 5 points behind is slightly delayed, over 15 delayed, over 30 significantly delayed.</p>
      </div>
    </div>
  )
}
