import { CalendarClock, FileText, Sparkles, TriangleAlert } from 'lucide-react'
import { CountUp, GrowBar } from '@/components/landing/motion'
import { STATUS_META } from '@/components/tracker/shared'
import type { TrackingStatus } from '@/types/tracker'

const ROWS: { name: string; client: string; status: TrackingStatus; progress: number }[] = [
  { name: 'Customer Portal v2', client: 'Northwind', status: 'on_time', progress: 72 },
  { name: 'Mobile Banking App', client: 'Globex', status: 'slightly_delayed', progress: 48 },
  { name: 'ERP Integration', client: 'Initech', status: 'delayed', progress: 31 },
  { name: 'Marketing Website', client: 'Umbrella', status: 'completed', progress: 100 },
]

const CHART = 'M0 70 C 30 66, 50 52, 80 50 S 130 38, 160 30 S 215 22, 240 8'

/** An illustrative tracker dashboard with live-feeling motion. The figures are sample data, not customer data. */
export function HeroMockup() {
  return (
    <div className="relative mx-auto max-w-5xl">
      {/* Floating callouts */}
      <div className="absolute -top-9 left-2 z-10 hidden motion-safe:animate-float sm:block lg:-left-14">
        <div className="card flex items-center gap-3 px-4 py-3 shadow-pop motion-safe:animate-pop-in" style={{ animationDelay: '900ms' }}>
          <span className="flex size-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"><Sparkles className="size-4" /></span>
          <div className="text-left">
            <div className="text-[11px] font-semibold text-ink-500">AI estimate · next step</div>
            <div className="text-sm font-bold text-ink-900">~6.5 hours</div>
          </div>
        </div>
      </div>
      <div className="absolute top-[42%] -right-3 z-10 hidden motion-safe:animate-float-slow sm:block lg:-right-16">
        <div className="card flex items-center gap-3 px-4 py-3 shadow-pop motion-safe:animate-pop-in" style={{ animationDelay: '1200ms' }}>
          <span className="flex size-9 items-center justify-center rounded-xl bg-amber-50 text-amber-600"><TriangleAlert className="size-4" /></span>
          <div className="text-left">
            <div className="text-[11px] font-semibold text-ink-500">Client dependency</div>
            <div className="text-sm font-bold text-ink-900">3 days pending</div>
          </div>
        </div>
      </div>
      <div className="absolute -right-1 -bottom-6 z-10 hidden motion-safe:animate-float sm:block lg:right-6">
        <div className="card flex items-center gap-3 px-4 py-3 shadow-pop motion-safe:animate-pop-in" style={{ animationDelay: '1500ms' }}>
          <span className="flex size-9 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><FileText className="size-4" /></span>
          <div className="text-left">
            <div className="text-[11px] font-semibold text-ink-500">Report ready</div>
            <div className="text-sm font-bold text-ink-900">PDF · Excel</div>
          </div>
        </div>
      </div>

      {/* Window */}
      <div className="rounded-[22px] border border-line bg-surface/70 p-2 shadow-pop backdrop-blur sm:p-3">
        <div className="overflow-hidden rounded-2xl border border-line bg-canvas text-left">
          <div className="flex items-center gap-2 border-b border-line bg-surface px-4 py-3">
            <span className="size-2.5 rounded-full bg-rose-400" /><span className="size-2.5 rounded-full bg-amber-400" /><span className="size-2.5 rounded-full bg-emerald-400" />
            <span className="mx-auto hidden rounded-lg bg-canvas px-10 py-1 text-[11px] font-medium text-ink-400 sm:block">yourWorkspace / projects</span>
          </div>

          <div className="p-4 sm:p-6">
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
              {[
                { k: 'Projects', v: 24, s: '' },
                { k: 'On time', v: 17, s: '' },
                { k: 'Avg. progress', v: 63, s: '%' },
                { k: 'Delayed', v: 4, s: '' },
              ].map(({ k, v, s }) => (
                <div key={k} className="rounded-xl border border-line bg-surface p-3.5 sm:p-4">
                  <div className="text-[11px] font-semibold text-ink-500 sm:text-xs">{k}</div>
                  <CountUp value={v} suffix={s} className="mt-1 block text-xl font-bold text-ink-900 sm:text-2xl" />
                </div>
              ))}
            </div>

            <div className="mt-3 grid gap-3 lg:grid-cols-[1.4fr_1fr]">
              <div className="rounded-xl border border-line bg-surface p-4">
                <div className="mb-3 text-xs font-bold text-ink-700">Project progress</div>
                <ul className="space-y-3.5">
                  {ROWS.map((r, i) => {
                    const meta = STATUS_META[r.status]
                    return (
                      <li key={r.name}>
                        <div className="mb-1.5 flex items-center justify-between gap-3">
                          <div className="min-w-0">
                            <span className="truncate text-[13px] font-semibold text-ink-900">{r.name}</span>
                            <span className="ml-2 hidden text-[11px] text-ink-400 sm:inline">{r.client}</span>
                          </div>
                          <span className="flex shrink-0 items-center gap-2">
                            <span className={`rounded-full px-2 py-0.5 text-[10.5px] font-semibold ring-1 ring-inset ${meta.badge}`}>{meta.label}</span>
                            <span className="tabular w-9 text-right text-xs font-bold text-ink-700">{r.progress}%</span>
                          </span>
                        </div>
                        <GrowBar percent={r.progress} color={meta.bar} delay={300 + i * 160} />
                      </li>
                    )
                  })}
                </ul>
              </div>

              <div className="rounded-xl border border-line bg-surface p-4">
                <div className="flex items-center justify-between">
                  <span className="text-xs font-bold text-ink-700">Progress trend</span>
                  <span className="flex items-center gap-1.5 text-[11px] font-semibold text-emerald-600">
                    <span className="size-2 rounded-full bg-emerald-500 motion-safe:animate-pulse-ring" /> Live
                  </span>
                </div>
                <svg viewBox="0 0 240 80" className="mt-3 h-28 w-full overflow-visible" aria-hidden>
                  <defs>
                    <linearGradient id="trendFill" x1="0" x2="0" y1="0" y2="1">
                      <stop offset="0%" stopColor="#d6121b" stopOpacity="0.25" />
                      <stop offset="100%" stopColor="#d6121b" stopOpacity="0" />
                    </linearGradient>
                  </defs>
                  {[20, 40, 60].map((y) => <line key={y} x1="0" x2="240" y1={y} y2={y} stroke="currentColor" className="text-line" strokeDasharray="3 4" />)}
                  <path d={`${CHART} L 240 80 L 0 80 Z`} fill="url(#trendFill)" className="motion-safe:animate-pop-in" style={{ animationDelay: '1400ms' }} />
                  <path d={CHART} fill="none" stroke="#d6121b" strokeWidth="2.5" strokeLinecap="round" pathLength={1} strokeDasharray={1} className="motion-safe:animate-draw" />
                  <circle cx="240" cy="8" r="4" fill="#d6121b" className="motion-safe:animate-pulse-ring" />
                </svg>
                <div className="mt-2 flex items-center gap-2 text-[11px] font-medium text-ink-500">
                  <CalendarClock className="size-3.5 text-brand-600" /> Next meeting: Thu 11:00
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
