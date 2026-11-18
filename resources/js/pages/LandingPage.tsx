import { useEffect, useState } from 'react'
import { Link } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import {
  ArrowRight, Bot, BriefcaseBusiness, CalendarClock, Check, ClipboardList, FileSpreadsheet, GitPullRequestArrow, Globe, HandHelping, KeyRound, MapPin, Megaphone, Menu,
  Rocket, ShieldCheck, UserRound, Users, UsersRound, X,
} from 'lucide-react'
import { useConfig } from '@/context/ConfigContext'
import { publicApi } from '@/lib/endpoints'
import { useVisitorCountry } from '@/lib/geo'
import { cn, formatMoney } from '@/lib/utils'
import { Logo } from '@/components/Logo'
import { CountryChip } from '@/components/ui'
import { ThemeToggle } from '@/components/ui/extra'
import { BackToTop, CountUp, GrowBar, Reveal, RotatingWord, ScrollProgress, SpotlightCard, TiltFrame, useScrollSpy } from '@/components/landing/motion'
import { HeroMockup } from '@/components/landing/HeroMockup'
import { ProductTour } from '@/components/landing/ProductTour'
import { StatusSimulator } from '@/components/landing/StatusSimulator'
import { Faq, type FaqItem } from '@/components/landing/Faq'
import { STATUS_META } from '@/components/tracker/shared'
import type { TrackingStatus } from '@/types/tracker'

const YEAR = new Date().getFullYear()

const NAV = [
  { id: 'features', label: 'Features' },
  { id: 'tour', label: 'Product tour' },
  { id: 'how', label: 'How it works' },
  { id: 'status', label: 'Try it' },
  { id: 'pricing', label: 'Pricing' },
  { id: 'faq', label: 'FAQ' },
]

const ROLES = [
  { icon: BriefcaseBusiness, role: 'Delivery managers', text: 'See every project and its verdict on one dashboard, and know who to call before a deadline slips.', points: ['Portfolio status at a glance', 'Internal vs client-side delay', 'Reminders for overdue work'] },
  { icon: UsersRound, role: 'Team members', text: 'Log the day in a minute. No lifecycle ceremony, no status meetings to prepare for.', points: ['Daily activity and hours', 'Meeting minutes and files', 'AI estimate for the next step'] },
  { icon: UserRound, role: 'Clients', text: 'A read-only portal and shareable reports so clients see progress without asking for it.', points: ['Client portal sign-in', 'PDF and Excel reports', 'Visible approved changes'] },
]

const FAQS: FaqItem[] = [
  { q: 'How does the tracker decide a project is late?', a: 'It compares the progress you log against the working days that have passed in the plan. More than 5 points behind is slightly delayed, more than 15 is delayed, and more than 30 (or more than 10 days overdue) is significantly delayed. You can try the same rules in the simulator above.' },
  { q: 'How is client delay separated from our own delay?', a: 'Each client dependency records when you asked and when it was answered, so the days you spent waiting are counted on their own. Approved change requests move the deadline by the days they add, so you are not penalised for scope the client added.' },
  { q: 'Do my team and clients need passwords?', a: 'No. Everyone signs in with a one-time code sent to their email. Clients get their own portal access to the projects they are part of.' },
  { q: 'Is our data separate from other workspaces?', a: 'Yes. Every workspace is isolated at the data layer, and automated isolation tests cover it. Your link looks like /yourWorkspace.' },
  { q: 'What does the AI do, and can it message clients by itself?', a: 'It estimates the next step, summarises where a project stands, and drafts client emails and WhatsApp messages. Nothing is sent until a person reviews it.' },
  { q: 'How does billing work?', a: 'Pick a plan, pay securely with Razorpay, and your workspace is created straight away. Prices are shown in your local currency where we support it, with monthly and yearly billing.' },
]

const CAPABILITIES = ['Daily activity log', 'Meeting minutes & files', 'Change requests', 'Client dependencies', 'AI delay estimates', 'PDF & Excel reports', 'Email one-time-code login', 'Isolated workspaces', 'Secure Razorpay billing']

const FEATURES = [
  { icon: ClipboardList, title: 'Daily activity tracking', text: 'Log what was done, hours spent and progress every day. Status and progress update themselves.' },
  { icon: CalendarClock, title: 'Meetings with attachments', text: 'Keep minutes, decisions and files next to the project they belong to.' },
  { icon: GitPullRequestArrow, title: 'Change requests', text: 'Record extra client requirements, approve or reject them, and see the days and hours they add.' },
  { icon: HandHelping, title: 'Client dependencies', text: 'Track what you are waiting on from the client and how many days it has delayed delivery.' },
  { icon: Bot, title: 'AI delay estimates', text: 'Get a realistic estimate for the next step and a plain-language summary of where the project stands.' },
  { icon: FileSpreadsheet, title: 'Reports in one click', text: 'Progress and performance reports, ready to download as PDF or Excel for your client.' },
  { icon: KeyRound, title: 'Passwordless sign-in', text: 'Everyone signs in with a one-time code sent to their email. Nothing to remember or leak.' },
  { icon: ShieldCheck, title: 'Your data stays yours', text: 'Every workspace is isolated at the data layer and covered by automated isolation tests.' },
]

const STEPS = [
  { icon: Rocket, title: 'Create your workspace', text: 'Pick a plan, pay securely with Razorpay and get your own link, like /acmeDigital.' },
  { icon: ClipboardList, title: 'Add projects', text: 'Set dates and working days. The tracker works out where the project should be today.' },
  { icon: Users, title: 'Log the work', text: 'Record daily activities, meetings, change requests and client dependencies as they happen.' },
  { icon: Bot, title: 'Read the verdict', text: 'See status, delays, internal versus client-side causes, and an AI estimate, then share the report.' },
]

const STATUS_DEMO: { status: TrackingStatus; progress: number; note: string }[] = [
  { status: 'on_time', progress: 78, note: 'Progress matches the plan.' },
  { status: 'slightly_delayed', progress: 58, note: 'A little behind. Worth watching.' },
  { status: 'delayed', progress: 40, note: 'Clearly behind schedule.' },
  { status: 'significantly_delayed', progress: 22, note: 'Needs intervention now.' },
  { status: 'completed', progress: 100, note: 'Delivered and signed off.' },
]

export default function LandingPage() {
  const [yearly, setYearly] = useState(false)
  const [menu, setMenu] = useState(false)
  const [scrolled, setScrolled] = useState(false)
  const { config } = useConfig()
  const visitor = useVisitorCountry()
  const activeSection = useScrollSpy(NAV.map((n) => n.id))

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 8)
    onScroll()
    window.addEventListener('scroll', onScroll, { passive: true })
    return () => window.removeEventListener('scroll', onScroll)
  }, [])

  // Pricing page for ONE country: the visitor's (detected or chosen) country, else the platform default.
  const { data: pricing, isLoading } = useQuery({
    queryKey: ['pricing', visitor.code],
    queryFn: () => publicApi.pricing(visitor.code),
    enabled: !visitor.detecting,
    retry: 1,
  })

  const country = pricing?.country
  const currency = pricing?.currency?.code ?? config.app.default_currency
  const canRegister = config.app.registration_open

  return (
    <div className="min-h-dvh overflow-x-clip bg-surface">
      <ScrollProgress />
      <BackToTop />
      <a href="#features" className="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-lg focus:bg-brand-600 focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-white">Skip to content</a>
      {config.app.maintenance_message && (
        <div className="flex items-center justify-center gap-2 bg-amber-50 px-4 py-2 text-center text-[13px] font-semibold text-amber-800"><Megaphone className="size-4 shrink-0" /> {config.app.maintenance_message}</div>
      )}

      <header className={cn('sticky top-0 z-30 border-b backdrop-blur-md transition-all duration-300', scrolled ? 'border-line bg-white/85 shadow-card' : 'border-transparent bg-white/40')}>
        <div className="mx-auto flex h-16 max-w-7xl items-center gap-8 px-4 sm:px-6 lg:px-8">
          <Logo />
          <nav className="hidden items-center gap-1 text-[13.5px] font-semibold text-ink-700 md:flex">
            {NAV.map((n) => (
              <a key={n.id} href={`#${n.id}`} aria-current={activeSection === n.id ? 'true' : undefined} className={cn('group relative rounded-lg px-3 py-2 hover:text-brand-600', activeSection === n.id && 'text-brand-600')}>
                {n.label}
                <span className={cn('absolute inset-x-3 bottom-1 h-0.5 origin-left scale-x-0 rounded-full bg-brand-600 transition-transform duration-300 group-hover:scale-x-100', activeSection === n.id && 'scale-x-100')} />
              </a>
            ))}
          </nav>
          <div className="ml-auto flex items-center gap-1">
            <ThemeToggle />
            <div className="hidden items-center gap-2 md:flex">
              <Link to="/superAdmin" className="inline-flex items-center gap-1.5 rounded-xl border border-line px-3.5 py-2 text-[13.5px] font-semibold text-ink-700 transition hover:border-brand-300 hover:text-brand-600"><ShieldCheck className="size-4" /> Owner login</Link>
              <Link to="/login" className="rounded-xl px-4 py-2 text-[13.5px] font-semibold text-ink-700 transition hover:bg-slate-100">Sign in</Link>
              {canRegister && <Link to="/register" className="rounded-xl bg-brand-600 px-4 py-2.5 text-[13.5px] font-semibold text-white shadow-sm shadow-brand-600/25 transition hover:-translate-y-0.5 hover:bg-brand-700">Get started</Link>}
            </div>
            <button className="rounded-lg p-2 md:hidden" onClick={() => setMenu((v) => !v)} aria-label="Menu" aria-expanded={menu}>
              {menu ? <X className="size-5" /> : <Menu className="size-5" />}
            </button>
          </div>
        </div>
        {menu && (
          <div className="animate-fade-in border-t border-line bg-surface px-4 pt-2 pb-4 md:hidden">
            {NAV.map((n) => (
              <a key={n.id} href={`#${n.id}`} onClick={() => setMenu(false)} className="block rounded-lg px-3 py-2.5 text-sm font-semibold text-ink-700 hover:bg-slate-50">{n.label}</a>
            ))}
            <div className="mt-2 grid grid-cols-2 gap-2">
              <Link to="/superAdmin" className="col-span-2 inline-flex items-center justify-center gap-1.5 rounded-xl border border-line py-2.5 text-sm font-semibold text-ink-700"><ShieldCheck className="size-4" /> Owner login</Link>
              <Link to="/login" className="rounded-xl border border-line py-2.5 text-center text-sm font-semibold">Sign in</Link>
              {canRegister && <Link to="/register" className="rounded-xl bg-brand-600 py-2.5 text-center text-sm font-semibold text-white">Get started</Link>}
            </div>
          </div>
        )}
      </header>

      {/* Hero */}
      <section className="relative">
        <div className="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden>
          <div className="absolute -top-48 left-[8%] size-[520px] rounded-full bg-brand-200/70 blur-3xl motion-safe:animate-drift" />
          <div className="absolute top-10 right-[4%] size-[440px] rounded-full bg-orange-200/60 blur-3xl motion-safe:animate-drift [animation-delay:-6s] [animation-direction:alternate-reverse]" />
          <div className="absolute inset-0 bg-[radial-gradient(circle_at_1px_1px,rgb(100_116_139/0.16)_1px,transparent_0)] [background-size:28px_28px] [mask-image:linear-gradient(to_bottom,black,transparent_75%)]" />
        </div>

        <div className="relative mx-auto max-w-7xl px-4 pt-16 pb-24 text-center sm:px-6 sm:pt-24 lg:px-8 lg:pt-28">
          <Reveal>
            <span className="inline-flex items-center gap-2 rounded-full border border-brand-200 bg-surface/80 px-3.5 py-1.5 text-xs font-semibold text-brand-700 shadow-card backdrop-blur">
              <span className="relative flex size-2"><span className="absolute inline-flex size-full rounded-full bg-brand-500 motion-safe:animate-ping" /><span className="relative inline-flex size-2 rounded-full bg-brand-600" /></span>
              <Globe className="size-3.5" /> Project tracking for delivery teams
            </span>
          </Reveal>
          <Reveal delay={90}>
            <h1 className="mx-auto mt-6 max-w-4xl text-[40px] leading-[1.08] font-extrabold tracking-tight text-ink-900 sm:text-6xl lg:text-[68px]">
              Always know where your project stands on{' '}
              <RotatingWord
                words={['progress', 'delays', 'change requests', 'client inputs']}
                className="bg-gradient-to-r from-brand-600 via-orange-500 to-brand-600 bg-[length:200%_auto] bg-clip-text pb-1 text-transparent"
              />
            </h1>
          </Reveal>
          <Reveal delay={180}>
            <p className="mx-auto mt-6 max-w-2xl text-base leading-relaxed text-ink-500 sm:text-lg">
              {config.app.name} tracks daily activity, meetings, change requests and client dependencies, then tells you whether you are on time, why you are late, and what to expect next.
            </p>
          </Reveal>
          <Reveal delay={270}>
            <div className="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
              {canRegister && (
                <Link to="/register" className="group relative inline-flex h-12 w-full items-center justify-center gap-2 overflow-hidden rounded-xl bg-brand-600 px-6 text-[15px] font-semibold text-white shadow-lg shadow-brand-600/25 transition hover:-translate-y-0.5 hover:bg-brand-700 sm:w-auto">
                  <span className="pointer-events-none absolute inset-y-0 left-0 w-1/4 bg-white/25 motion-safe:animate-shine" aria-hidden />
                  <span className="relative">Create your workspace</span> <ArrowRight className="relative size-4 transition-transform group-hover:translate-x-1" />
                </Link>
              )}
              <a href="#status" className="inline-flex h-12 w-full items-center justify-center rounded-xl border border-line bg-surface px-6 text-[15px] font-semibold text-ink-900 shadow-card transition hover:-translate-y-0.5 hover:bg-slate-50 sm:w-auto">
                Try the status simulator
              </a>
            </div>
            <p className="mt-4 text-[13px] text-ink-500">Already have a workspace? <Link to="/login" className="font-semibold text-brand-600 hover:underline">Sign in</Link></p>
            <ul className="mt-6 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-[12.5px] font-semibold text-ink-500">
              {['Passwordless sign-in', 'Isolated workspaces', 'Light & dark mode', 'PDF & Excel reports'].map((t) => (
                <li key={t} className="flex items-center gap-1.5"><Check className="size-3.5 text-emerald-500" strokeWidth={3} /> {t}</li>
              ))}
            </ul>
          </Reveal>

          <Reveal delay={360} className="mt-14 sm:mt-18">
            <TiltFrame><HeroMockup /></TiltFrame>
          </Reveal>
        </div>
      </section>

      {/* Capability marquee */}
      <section className="border-y border-line bg-canvas py-5" aria-label="Capabilities">
        <div className="relative overflow-hidden [mask-image:linear-gradient(to_right,transparent,black_10%,black_90%,transparent)]">
          <div className="flex w-max gap-10 motion-safe:animate-marquee">
            {[...CAPABILITIES, ...CAPABILITIES].map((c, i) => (
              <span key={i} className="flex items-center gap-10 text-[13px] font-semibold whitespace-nowrap text-ink-500">
                {c} <span className="size-1.5 rounded-full bg-brand-400" />
              </span>
            ))}
          </div>
        </div>
      </section>

      {/* Numbers */}
      <section className="py-16 sm:py-20">
        <div className="mx-auto grid max-w-5xl grid-cols-2 gap-8 px-4 text-center sm:px-6 lg:grid-cols-4 lg:px-8">
          {[
            { v: 5, s: '', l: 'Automatic status levels' },
            { v: 4, s: '', l: 'Things tracked per project' },
            { v: 2, s: '', l: 'Report types, PDF and Excel' },
            { v: 100, s: '%', l: 'Workspace data isolation' },
          ].map((n, i) => (
            <Reveal key={n.l} delay={i * 90}>
              <CountUp value={n.v} suffix={n.s} className="bg-gradient-to-b from-ink-900 to-brand-600 bg-clip-text text-5xl font-extrabold tracking-tight text-transparent" />
              <div className="mt-2 text-[13px] font-medium text-ink-500">{n.l}</div>
            </Reveal>
          ))}
        </div>
      </section>

      {/* Features */}
      <section id="features" className="scroll-mt-16 bg-canvas py-20 sm:py-24">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <Reveal><SectionTitle eyebrow="Everything you need" title="One tracker for the whole delivery story" text="No lifecycle ceremony. Just the facts about your projects, kept up to date by the people doing the work." /></Reveal>
          <div className="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            {FEATURES.map(({ icon: Icon, title, text }, i) => (
              <Reveal key={title} delay={(i % 4) * 80}>
                <SpotlightCard className="h-full">
                  <div className="flex size-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600 transition duration-300 group-hover:scale-110"><Icon className="size-5" /></div>
                  <h3 className="mt-4 text-[15px] font-bold">{title}</h3>
                  <p className="mt-1.5 text-[13.5px] leading-relaxed text-ink-500">{text}</p>
                </SpotlightCard>
              </Reveal>
            ))}
          </div>
        </div>
      </section>

      {/* Product tour */}
      <section id="tour" className="scroll-mt-16 py-20 sm:py-24">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <Reveal><SectionTitle eyebrow="Product tour" title="See a project from the inside" text="Click through the four things your team does every week. It plays on its own until you take over." /></Reveal>
          <Reveal delay={100} className="mt-12"><ProductTour /></Reveal>
        </div>
      </section>

      {/* Built for each role */}
      <section className="border-y border-line bg-canvas py-20 sm:py-24">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <Reveal><SectionTitle eyebrow="Built for the whole team" title="One source of truth, three points of view" text="Managers, makers and clients each get what they need, from the same facts." /></Reveal>
          <div className="mt-12 grid gap-5 md:grid-cols-3">
            {ROLES.map(({ icon: Icon, role, text, points }, i) => (
              <Reveal key={role} delay={i * 110}>
                <SpotlightCard className="h-full">
                  <div className="flex size-11 items-center justify-center rounded-xl bg-brand-600 text-white shadow-lg shadow-brand-600/25 transition duration-300 group-hover:rotate-6"><Icon className="size-5" /></div>
                  <h3 className="mt-4 text-lg font-bold">{role}</h3>
                  <p className="mt-1.5 text-[13.5px] leading-relaxed text-ink-500">{text}</p>
                  <ul className="mt-4 space-y-2 border-t border-line pt-4">
                    {points.map((p) => <li key={p} className="flex items-center gap-2 text-[13px] font-medium text-ink-700"><Check className="size-4 shrink-0 text-brand-600" strokeWidth={2.5} /> {p}</li>)}
                  </ul>
                </SpotlightCard>
              </Reveal>
            ))}
          </div>
        </div>
      </section>

      {/* How it works */}
      <section id="how" className="scroll-mt-16 py-20 sm:py-24">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <Reveal><SectionTitle eyebrow="How it works" title="From sign-up to a client-ready report" text="Four steps. Most teams are tracking their first project within minutes." /></Reveal>
          <ol className="relative mt-14 grid gap-10 lg:grid-cols-4 lg:gap-6">
            <div className="pointer-events-none absolute top-6 right-[12%] left-[12%] hidden h-0.5 bg-gradient-to-r from-brand-200 via-brand-500 to-brand-200 lg:block" aria-hidden />
            {STEPS.map(({ icon: Icon, title, text }, i) => (
              <Reveal as="li" key={title} delay={i * 140} className="relative text-center">
                <span className="relative mx-auto flex size-12 items-center justify-center rounded-2xl bg-brand-600 text-white shadow-lg shadow-brand-600/30 motion-safe:animate-pulse-ring [animation-delay:var(--d)]">
                  <Icon className="size-5" />
                  <span className="absolute -top-2 -right-2 flex size-6 items-center justify-center rounded-full border-2 border-surface bg-ink-900 text-[11px] font-bold text-white">{i + 1}</span>
                </span>
                <h3 className="mt-5 text-[15px] font-bold">{title}</h3>
                <p className="mx-auto mt-1.5 max-w-[240px] text-[13.5px] leading-relaxed text-ink-500">{text}</p>
              </Reveal>
            ))}
          </ol>
        </div>
      </section>

      {/* Live simulator */}
      <section id="status" className="scroll-mt-16 border-t border-line bg-canvas pt-20 sm:pt-24">
        <div className="mx-auto max-w-6xl px-4 pb-20 sm:px-6 sm:pb-24 lg:px-8">
          <Reveal><SectionTitle eyebrow="Try it yourself" title="Drag the sliders. Watch the verdict change." text="This runs the same status rules as the live tracker, so you can see exactly what a project like yours would show." /></Reveal>
          <Reveal delay={100} className="mt-12"><StatusSimulator /></Reveal>
        </div>
      </section>

      {/* Status */}
      <section className="border-y border-line py-20 sm:py-24">
        <div className="mx-auto grid max-w-7xl items-center gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:gap-16 lg:px-8">
          <Reveal>
            <div className="text-xs font-bold tracking-wider text-brand-600 uppercase">Automatic status</div>
            <h2 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">Not just a percentage. A verdict you can act on.</h2>
            <p className="mt-4 text-[15px] leading-relaxed text-ink-500">
              The tracker compares real progress against the schedule, separates delay caused by your team from delay caused by waiting on the client, and tells you how much extra work change requests have added.
            </p>
            <ul className="mt-6 space-y-3">
              {['Internal versus client-side delay, in days', 'Extra days and hours from approved change requests', 'AI estimate for the next step'].map((t) => (
                <li key={t} className="flex items-start gap-2.5 text-[14px] font-medium text-ink-700"><Check className="mt-0.5 size-4 shrink-0 text-brand-600" strokeWidth={2.5} /> {t}</li>
              ))}
            </ul>
          </Reveal>
          <Reveal delay={120}>
            <div className="card divide-y divide-line">
              {STATUS_DEMO.map((s, i) => {
                const meta = STATUS_META[s.status]
                return (
                  <div key={s.status} className="p-4 sm:p-5">
                    <div className="mb-2.5 flex items-center justify-between gap-3">
                      <span className={`rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset ${meta.badge}`}>{meta.label}</span>
                      <span className="text-xs text-ink-500">{s.note}</span>
                    </div>
                    <GrowBar percent={s.progress} color={meta.bar} delay={i * 140} />
                  </div>
                )
              })}
            </div>
          </Reveal>
        </div>
      </section>

      {/* Pricing: shown for ONE country only */}
      <section id="pricing" className="scroll-mt-16 py-20 sm:py-24">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <Reveal><SectionTitle eyebrow="Pricing" title="Simple plans that scale with you" text="Pay securely with Razorpay and your workspace is ready immediately." /></Reveal>

          <div className="mt-8 flex flex-col items-center gap-4">
            <div className="flex flex-wrap items-center justify-center gap-2.5 rounded-2xl border border-line bg-surface px-4 py-2.5 shadow-card" aria-live="polite">
              <MapPin className="size-4 text-brand-600" />
              {isLoading || visitor.detecting ? (
                <span className="text-[13px] font-semibold text-ink-500">Detecting your country…</span>
              ) : (
                <>
                  <span className="text-[13px] font-semibold text-ink-900">
                    <CountryChip code={country?.code} className="mr-1.5" /> Pricing for {country?.name ?? 'your region'} · <span className="text-brand-600">{currency}</span>
                  </span>
                  <span className="text-xs text-ink-400">
                    {pricing?.is_fallback && pricing.requested ? `(not available in ${pricing.requested} yet, showing our default)` : visitor.source === 'detected' ? '(detected from your location)' : '(our default pricing)'}
                  </span>
                </>
              )}
            </div>

            <div className="inline-flex rounded-xl border border-line bg-surface p-1 shadow-card">
              {[false, true].map((y) => (
                <button key={String(y)} onClick={() => setYearly(y)} className={cn('rounded-lg px-4 py-2 text-[13px] font-semibold transition', yearly === y ? 'bg-brand-600 text-white shadow-sm' : 'text-ink-500 hover:text-ink-900')}>
                  {y ? 'Yearly · 2 months free' : 'Monthly'}
                </button>
              ))}
            </div>
          </div>

          <div className="mx-auto mt-10 grid max-w-5xl gap-5 lg:grid-cols-3">
            {(pricing?.plans ?? []).map((plan, i) => {
              const featured = plan.code === 'growth'
              const monthly = yearly ? plan.price_yearly / 12 : plan.price_monthly
              return (
                <Reveal key={plan.code} delay={i * 110}>
                  <div className={cn('relative flex h-full flex-col rounded-2xl border bg-surface p-6 transition duration-300 hover:-translate-y-1.5 sm:p-7', featured ? 'border-brand-600 shadow-pop ring-4 ring-brand-100' : 'border-line shadow-card hover:shadow-pop')}>
                    {featured && <span className="absolute -top-3 left-6 rounded-full bg-brand-600 px-3 py-1 text-[11px] font-bold text-white">Most popular</span>}
                    <h3 className="text-lg font-bold">{plan.name}</h3>
                    <p className="mt-1 min-h-10 text-[13px] text-ink-500">{plan.description}</p>
                    <div className="mt-5 flex items-baseline gap-1">
                      <span className="text-4xl font-extrabold tracking-tight">{formatMoney(monthly, plan.currency, true)}</span>
                      <span className="text-[13px] font-medium text-ink-500">/ month</span>
                    </div>
                    <p className="mt-1 text-xs text-ink-400">{yearly ? `Billed ${formatMoney(plan.price_yearly, plan.currency, true)} yearly` : 'Billed monthly'}</p>
                    <Link to={`/register?plan=${plan.code}`} className={cn('mt-6 inline-flex h-11 items-center justify-center rounded-xl text-sm font-semibold transition', featured ? 'bg-brand-600 text-white hover:bg-brand-700' : 'border border-line text-ink-900 hover:bg-slate-50')}>
                      Get started
                    </Link>
                    <ul className="mt-6 space-y-3 border-t border-line pt-6">
                      {plan.features.map((f) => (
                        <li key={f} className="flex items-start gap-2.5 text-[13.5px] text-ink-700">
                          <Check className="mt-0.5 size-4 shrink-0 text-brand-600" strokeWidth={2.5} /> {f}
                        </li>
                      ))}
                    </ul>
                  </div>
                </Reveal>
              )
            })}
          </div>
        </div>
      </section>

      {/* FAQ */}
      <section id="faq" className="scroll-mt-16 border-t border-line bg-canvas py-20 sm:py-24">
        <div className="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
          <Reveal><SectionTitle eyebrow="FAQ" title="Questions, answered" text="Still unsure? Write to us and a person will reply." /></Reveal>
          <Reveal delay={100} className="mt-10"><Faq items={FAQS} /></Reveal>
        </div>
      </section>

      {/* Closing call to action */}
      {canRegister && (
        <section className="px-4 py-20 sm:px-6 lg:px-8">
          <Reveal>
            <div className="relative mx-auto max-w-5xl overflow-hidden rounded-3xl bg-gradient-to-br from-brand-700 via-brand-600 to-orange-500 bg-[length:200%_200%] px-6 py-14 text-center shadow-pop motion-safe:animate-gradient sm:px-12 sm:py-16">
              <div className="pointer-events-none absolute -top-24 -right-16 size-72 rounded-full bg-white/10 blur-2xl motion-safe:animate-drift" aria-hidden />
              <h2 className="relative text-3xl font-extrabold tracking-tight text-white sm:text-4xl">Stop guessing. Start tracking.</h2>
              <p className="relative mx-auto mt-3 max-w-xl text-[15px] text-white/85">Create your workspace in a couple of minutes and see where every project really stands.</p>
              <Link to="/register" className="group relative mt-8 inline-flex h-12 items-center gap-2 rounded-xl bg-white px-7 text-[15px] font-bold text-brand-700 shadow-lg transition hover:-translate-y-0.5">
                Create your workspace <ArrowRight className="size-4 transition-transform group-hover:translate-x-1" />
              </Link>
            </div>
          </Reveal>
        </section>
      )}

      <footer className="border-t border-line bg-surface">
        <div className="mx-auto grid max-w-7xl gap-10 px-4 py-12 sm:px-6 md:grid-cols-[1.4fr_1fr_1fr] lg:px-8">
          <div>
            <Logo />
            <p className="mt-4 max-w-xs text-[13px] leading-relaxed text-ink-500">Project tracking that tells delivery teams whether each client project is on time, why it is late, and what comes next.</p>
          </div>
          <div>
            <div className="text-xs font-bold tracking-wider text-ink-900 uppercase">Product</div>
            <ul className="mt-4 space-y-2.5 text-[13.5px] text-ink-500">
              {NAV.map((n) => <li key={n.id}><a className="transition hover:text-brand-600" href={`#${n.id}`}>{n.label}</a></li>)}
            </ul>
          </div>
          <div>
            <div className="text-xs font-bold tracking-wider text-ink-900 uppercase">Account</div>
            <ul className="mt-4 space-y-2.5 text-[13.5px] text-ink-500">
              <li><Link className="transition hover:text-brand-600" to="/login">Sign in</Link></li>
              <li><Link className="transition hover:text-brand-600" to="/superAdmin">Owner login</Link></li>
              {canRegister && <li><Link className="transition hover:text-brand-600" to="/register">Create a workspace</Link></li>}
              <li><a className="transition hover:text-brand-600" href={`mailto:${config.app.support_email}`}>{config.app.support_email}</a></li>
            </ul>
          </div>
        </div>
        <div className="border-t border-line">
          <p className="mx-auto max-w-7xl px-4 py-5 text-xs text-ink-500 sm:px-6 lg:px-8">© {YEAR} {config.app.name}. All rights reserved.</p>
        </div>
      </footer>
    </div>
  )
}

function SectionTitle({ eyebrow, title, text }: { eyebrow: string; title: string; text: string }) {
  return (
    <div className="mx-auto max-w-2xl text-center">
      <div className="text-xs font-bold tracking-wider text-brand-600 uppercase">{eyebrow}</div>
      <h2 className="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">{title}</h2>
      <p className="mt-3 text-[15px] text-ink-500">{text}</p>
    </div>
  )
}
