import { useEffect, useState } from 'react'
import { Bot, Check, FileSpreadsheet, FileText, GitPullRequestArrow, HandHelping, LayoutDashboard, ListChecks, Mail } from 'lucide-react'
import { cn } from '@/lib/utils'
import { GrowBar } from '@/components/landing/motion'
import { STATUS_META } from '@/components/tracker/shared'

const TABS = [
  { id: 'log', label: 'Daily log', icon: ListChecks, title: 'Log the work once. Everything else follows.', text: 'Hours, progress and notes go in each day. Status, deadlines and reports are calculated for you.' },
  { id: 'changes', label: 'Changes & dependencies', icon: GitPullRequestArrow, title: 'See exactly what moved the deadline.', text: 'Approved change requests add days; client waits are counted separately, so blame lands where it belongs.' },
  { id: 'ai', label: 'AI & messages', icon: Bot, title: 'AI estimates and drafted client updates.', text: 'Get an estimate for the next step, then review an AI-written email or WhatsApp message before it is sent.' },
  { id: 'reports', label: 'Reports', icon: FileSpreadsheet, title: 'Client-ready reports in one click.', text: 'Progress and performance reports as PDF or Excel, always on white and ready to share.' },
] as const

type TabId = (typeof TABS)[number]['id']

const TOUR_MS = 5500

const LOG = [
  { d: 'Mon', t: 'Built invoice export endpoint', h: 6, p: 52 },
  { d: 'Tue', t: 'Client review call, minutes saved', h: 3, p: 58 },
  { d: 'Wed', t: 'Fixed pagination on reports page', h: 7, p: 66 },
]

function Panel({ id }: { id: TabId }) {
  if (id === 'log') {
    return (
      <ul className="space-y-3">
        {LOG.map((r, i) => (
          <li key={r.d} className="rounded-xl border border-line bg-surface p-4 motion-safe:animate-pop-in" style={{ animationDelay: `${i * 110}ms` }}>
            <div className="flex items-center justify-between gap-3 text-[13px]">
              <span className="font-semibold text-ink-900"><span className="mr-2 rounded-md bg-brand-50 px-1.5 py-0.5 text-[11px] font-bold text-brand-700">{r.d}</span>{r.t}</span>
              <span className="tabular shrink-0 text-xs font-semibold text-ink-500">{r.h} h</span>
            </div>
            <div className="mt-3"><GrowBar percent={r.p} color={STATUS_META.on_time.bar} delay={150 + i * 120} /></div>
          </li>
        ))}
      </ul>
    )
  }
  if (id === 'changes') {
    return (
      <div className="space-y-3">
        {[
          { icon: GitPullRequestArrow, tone: 'bg-emerald-50 text-emerald-600', title: 'CR-07 · SSO login', meta: 'Approved · +4 days, +26 hours', chip: 'Deadline moved' },
          { icon: GitPullRequestArrow, tone: 'bg-rose-50 text-rose-600', title: 'CR-08 · Dark theme', meta: 'Rejected · no schedule impact', chip: 'No change' },
          { icon: HandHelping, tone: 'bg-amber-50 text-amber-600', title: 'Awaiting API keys from client', meta: 'Requested 5 days ago', chip: '5 days client delay' },
        ].map(({ icon: Icon, tone, title, meta, chip }, i) => (
          <div key={title} className="flex items-center gap-3 rounded-xl border border-line bg-surface p-4 motion-safe:animate-pop-in" style={{ animationDelay: `${i * 110}ms` }}>
            <span className={cn('flex size-10 shrink-0 items-center justify-center rounded-xl', tone)}><Icon className="size-[18px]" /></span>
            <div className="min-w-0 flex-1"><div className="truncate text-[13.5px] font-semibold text-ink-900">{title}</div><div className="text-xs text-ink-500">{meta}</div></div>
            <span className="hidden shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-ink-700 sm:block">{chip}</span>
          </div>
        ))}
      </div>
    )
  }
  if (id === 'ai') {
    return (
      <div className="space-y-3">
        <div className="rounded-xl border border-line bg-surface p-4 motion-safe:animate-pop-in">
          <div className="flex items-center gap-2 text-xs font-bold text-emerald-600"><Bot className="size-4" /> AI estimate · next step</div>
          <div className="mt-1 text-2xl font-extrabold text-ink-900">~6.5 hours</div>
          <p className="mt-1 text-xs text-ink-500">Sample figure shown for illustration.</p>
        </div>
        <div className="rounded-xl border border-line bg-surface p-4 motion-safe:animate-pop-in" style={{ animationDelay: '120ms' }}>
          <div className="flex items-center justify-between text-xs font-bold text-ink-700"><span className="flex items-center gap-2"><Mail className="size-4 text-brand-600" /> Draft client update</span><span className="rounded-full bg-amber-50 px-2 py-0.5 text-[10.5px] text-amber-700">Needs your review</span></div>
          <p className="mt-2 text-[13px] leading-relaxed text-ink-500">Hi team, a quick update: the reports module is 66% complete and on track for Friday. We still need the staging API keys to finish integration testing…</p>
        </div>
      </div>
    )
  }
  return (
    <div className="grid gap-3 sm:grid-cols-2">
      {[
        { icon: FileText, name: 'Progress report', fmt: 'PDF' },
        { icon: FileSpreadsheet, name: 'Progress report', fmt: 'Excel' },
        { icon: FileText, name: 'Performance & delay report', fmt: 'PDF' },
        { icon: FileSpreadsheet, name: 'Performance & delay report', fmt: 'Excel' },
      ].map(({ icon: Icon, name, fmt }, i) => (
        <div key={name + fmt} className="flex items-center gap-3 rounded-xl border border-line bg-surface p-4 motion-safe:animate-pop-in" style={{ animationDelay: `${i * 90}ms` }}>
          <span className="flex size-10 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><Icon className="size-[18px]" /></span>
          <div className="min-w-0"><div className="truncate text-[13px] font-semibold text-ink-900">{name}</div><div className="text-xs text-ink-500">{fmt} · ready</div></div>
          <Check className="ml-auto size-4 text-emerald-500" strokeWidth={3} />
        </div>
      ))}
    </div>
  )
}

/** Tabbed product walkthrough that auto-advances until the visitor takes over. */
export function ProductTour() {
  const [active, setActive] = useState<TabId>('log')
  const [manual, setManual] = useState(false)

  useEffect(() => {
    if (manual || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return
    const id = window.setInterval(() => {
      setActive((cur) => TABS[(TABS.findIndex((t) => t.id === cur) + 1) % TABS.length].id)
    }, TOUR_MS)
    return () => window.clearInterval(id)
  }, [manual])

  const tab = TABS.find((t) => t.id === active) ?? TABS[0]

  return (
    <div className="grid items-stretch gap-6 lg:grid-cols-[320px_1fr]">
      <div role="tablist" aria-label="Product tour" className="flex gap-2 overflow-x-auto pb-1 lg:flex-col lg:overflow-visible">
        {TABS.map(({ id, label, icon: Icon }) => {
          const on = id === active
          return (
            <button
              key={id} type="button" role="tab" aria-selected={on}
              onClick={() => { setManual(true); setActive(id) }}
              className={cn('group relative flex shrink-0 items-center gap-3 overflow-hidden rounded-xl border px-4 py-3.5 text-left text-[14px] font-semibold transition', on ? 'border-brand-600 bg-surface text-ink-900 shadow-pop' : 'border-line bg-surface/60 text-ink-500 hover:border-brand-300 hover:text-ink-900')}
            >
              <span className={cn('flex size-9 items-center justify-center rounded-lg transition', on ? 'bg-brand-600 text-white' : 'bg-slate-100 text-ink-500 group-hover:text-brand-600')}><Icon className="size-4" /></span>
              {label}
              {on && !manual && <span key={id} className="absolute inset-x-0 bottom-0 h-0.5 origin-left bg-brand-600 motion-safe:animate-tour-progress" />}
            </button>
          )
        })}
      </div>

      <div className="card relative overflow-hidden bg-canvas p-5 sm:p-7">
        <div className="mb-5 flex items-center gap-2 text-xs font-bold text-ink-400"><LayoutDashboard className="size-4" /> yourWorkspace / project</div>
        <div key={tab.id} role="tabpanel" className="motion-safe:animate-fade-in">
          <h3 className="text-xl font-bold tracking-tight sm:text-2xl">{tab.title}</h3>
          <p className="mt-2 mb-6 max-w-xl text-[14px] leading-relaxed text-ink-500">{tab.text}</p>
          <Panel id={tab.id} />
        </div>
      </div>
    </div>
  )
}
