import { Check } from 'lucide-react'
import { cn } from '@/lib/utils'

/** The project's stages in order, with the current one highlighted. With `onMove` the team can click a stage to move the project there. */
export function StageTracker({ stages, current, onMove, busy }: { stages: { value: string; label: string }[]; current: string; onMove?: (stage: string) => void; busy?: boolean }) {
  const index = Math.max(0, stages.findIndex((s) => s.value === current))

  return (
    <ol className="flex w-full items-start overflow-x-auto pb-1" aria-label="Project stages">
      {stages.map((s, i) => {
        const done = i < index
        const active = i === index
        const inner = (
          <>
            <span className={cn('flex size-8 items-center justify-center rounded-full text-[12px] font-bold ring-4 ring-surface transition-colors', done ? 'bg-emerald-600 text-white' : active ? 'bg-brand-600 text-white' : 'bg-slate-200 text-ink-500')}>
              {done ? <Check className="size-4" /> : i + 1}
            </span>
            <span className={cn('mt-1.5 text-center text-[12px] leading-tight font-semibold', active ? 'text-ink-900' : 'text-ink-500')}>{s.label}</span>
          </>
        )
        return (
          <li key={s.value} className="relative flex min-w-[84px] flex-1 flex-col items-center" aria-current={active ? 'step' : undefined}>
            {i > 0 && <span className={cn('absolute top-4 right-1/2 h-0.5 w-full -translate-y-1/2', i <= index ? 'bg-emerald-500' : 'bg-slate-200')} aria-hidden />}
            {onMove && !active ? (
              <button type="button" disabled={busy} onClick={() => onMove(s.value)} className="relative flex flex-col items-center rounded-lg px-1 hover:opacity-80 disabled:opacity-50" title={`Move to ${s.label}`}>{inner}</button>
            ) : (
              <span className="relative flex flex-col items-center px-1">{inner}</span>
            )}
          </li>
        )
      })}
    </ol>
  )
}
