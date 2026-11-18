import { useDeferredValue, useEffect, useLayoutEffect, useRef, useState, type FormEvent, type KeyboardEvent } from 'react'
import { Link } from 'react-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  AlertCircle, ArrowDown, ArrowLeft, Check, CheckCheck, Clock, Lock, MessageCircle, QrCode, Search, Send, ShieldCheck, Smartphone, Sparkles, Unlink, Users,
} from 'lucide-react'
import { Alert, Avatar, Badge, Button, Card, Input, Skeleton, Spinner } from '@/components/ui'
import { ConfirmButton, Tabs } from '@/components/ui/extra'
import { useAuth } from '@/context/AuthContext'
import { useToast } from '@/context/ToastContext'
import { errorMessage } from '@/lib/api'
import { whatsappApi } from '@/lib/endpoints'
import { cn, formatDate, relativeTime } from '@/lib/utils'
import type { ClientMessage, WhatsAppConversation, WhatsAppStatus } from '@/types/tracker'

const STATUS_KEY = ['whatsapp', 'status']

const QUICK_REPLIES = [
  'Thanks for your message. We are looking into it.',
  'Could you please share the details so we can proceed?',
  'We will update you shortly.',
  'You can follow the latest progress in your client portal.',
]

const formatNumber = (digits: string | null) => (digits ? `+${digits}` : 'No WhatsApp number')
const clock = (value: string | null | undefined) => (value ? new Intl.DateTimeFormat('en', { hour: '2-digit', minute: '2-digit' }).format(new Date(value)) : '')

/** The workspace WhatsApp inbox. Enterprise plan: scan a QR code to link the workspace's own number, then chat with clients. */
export default function WhatsAppPage() {
  const { can } = useAuth()
  const [search, setSearch] = useState('')
  const deferredSearch = useDeferredValue(search.trim())

  const { data: status, isLoading } = useQuery({
    queryKey: STATUS_KEY,
    queryFn: whatsappApi.status,
    refetchInterval: (query) => (query.state.data?.state === 'open' ? 30_000 : false),
  })
  const linked = status?.allowed === true && status.state === 'open'

  const { data: conversations, isLoading: loadingConversations } = useQuery({
    queryKey: ['whatsapp', 'conversations', deferredSearch],
    queryFn: () => whatsappApi.conversations(deferredSearch),
    enabled: linked,
    refetchInterval: 8000,
    placeholderData: (previous) => previous,
  })

  return (
    <div className="space-y-5">
      <Hero status={status} canManage={can('settings.manage')} total={conversations?.data.length} unread={conversations?.unread} />

      {isLoading || !status ? (
        <Skeleton className="h-[480px]" />
      ) : !status.allowed ? (
        <UpgradeCard plan={status.plan} />
      ) : !status.configured ? (
        <Alert tone="info">WhatsApp is not set up on this server yet. Ask the platform administrator to add the Evolution API settings.</Alert>
      ) : linked ? (
        <Inbox conversations={conversations?.data ?? []} loading={loadingConversations} search={search} onSearch={setSearch} />
      ) : (
        <LinkPhone canManage={can('settings.manage')} unreachable={status.state === 'unreachable'} />
      )}
    </div>
  )
}

/* ------------------------------------------------------------------ hero */

function Hero({ status, canManage, total, unread }: { status?: WhatsAppStatus; canManage: boolean; total?: number; unread?: number }) {
  const toast = useToast()
  const queryClient = useQueryClient()
  const linked = status?.allowed === true && status.state === 'open'
  const disconnect = useMutation({
    mutationFn: whatsappApi.disconnect,
    onSuccess: () => {
      toast.success('WhatsApp number unlinked.')
      queryClient.invalidateQueries({ queryKey: STATUS_KEY })
    },
  })

  return (
    <header className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-ink-900 via-[#10231f] to-emerald-900 px-5 py-6 text-white shadow-pop sm:px-8 sm:py-8">
      <div className="pointer-events-none absolute -top-16 -right-10 size-64 animate-float-slow rounded-full bg-emerald-400/15 blur-3xl" aria-hidden />
      <div className="pointer-events-none absolute -bottom-20 left-1/3 size-56 animate-drift rounded-full bg-brand-500/15 blur-3xl" aria-hidden />

      <div className="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
        <div className="flex items-start gap-4">
          <span className="relative flex size-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-500 text-white shadow-lg shadow-emerald-900/40 sm:size-14">
            {linked && <span className="wa-ping absolute inset-0 rounded-2xl bg-emerald-400/50" aria-hidden />}
            <MessageCircle className="relative size-6 sm:size-7" />
          </span>
          <div className="min-w-0">
            <div className="text-[11px] font-bold tracking-[0.14em] text-emerald-300 uppercase">Client communication</div>
            <h1 className="mt-1 text-2xl font-bold tracking-tight sm:text-[30px]">WhatsApp</h1>
            <p className="mt-1.5 max-w-xl text-[13.5px] text-white/70">Talk to your clients from your own WhatsApp number, with every conversation saved next to their projects.</p>
          </div>
        </div>

        {status?.allowed && (
          <div className="flex flex-wrap items-stretch gap-3">
            <HeroStat
              label="Connection"
              value={linked ? 'Linked' : 'Not linked'}
              hint={linked ? formatNumber(status.number) : 'Scan the QR code to start'}
              icon={<span className="relative flex size-2.5"><span className={cn('absolute inset-0 rounded-full', linked ? 'wa-ping bg-emerald-400' : 'bg-amber-400')} /><span className={cn('relative size-2.5 rounded-full', linked ? 'bg-emerald-400' : 'bg-amber-400')} /></span>}
            />
            {linked && <HeroStat label="Conversations" value={String(total ?? '–')} hint="With a WhatsApp number" icon={<Users className="size-3.5 text-white/70" />} />}
            {linked && <HeroStat label="Unread" value={String(unread ?? '–')} hint={unread ? 'Waiting for a reply' : 'All caught up'} icon={<Sparkles className="size-3.5 text-white/70" />} highlight={!!unread} />}
            {linked && canManage && (
              <ConfirmButton
                danger
                title="Unlink this WhatsApp number?"
                message="Chat history stays in the app, but you will not receive or send messages until you scan a QR code again."
                confirmLabel="Unlink number"
                onConfirm={() => disconnect.mutateAsync().catch((err) => { throw new Error(errorMessage(err)) })}
                trigger={(open) => (
                  <button onClick={open} className="flex items-center gap-2 self-center rounded-xl bg-white/10 px-3.5 py-2.5 text-[12.5px] font-semibold text-white/90 ring-1 ring-white/15 backdrop-blur transition hover:bg-white/20">
                    <Unlink className="size-3.5" /> Unlink
                  </button>
                )}
              />
            )}
          </div>
        )}
      </div>
    </header>
  )
}

function HeroStat({ label, value, hint, icon, highlight }: { label: string; value: string; hint: string; icon: React.ReactNode; highlight?: boolean }) {
  return (
    <div className={cn('min-w-[132px] rounded-2xl px-4 py-3 ring-1 backdrop-blur transition', highlight ? 'bg-emerald-400/20 ring-emerald-300/40' : 'bg-white/10 ring-white/15')}>
      <div className="flex items-center gap-2 text-[11px] font-semibold tracking-wide text-white/65 uppercase">{icon}{label}</div>
      <div className="tabular mt-1 text-xl leading-none font-bold">{value}</div>
      <div className="mt-1 truncate text-[11.5px] text-white/60">{hint}</div>
    </div>
  )
}

/* ------------------------------------------------------------------ plan gate */

function UpgradeCard({ plan }: { plan: string | null }) {
  const perks = [
    { icon: <QrCode className="size-5" />, title: 'Scan and connect', text: 'Link your own number by scanning a QR code. No extra app to install.' },
    { icon: <MessageCircle className="size-5" />, title: 'Chats saved on projects', text: 'Every message is stored on the client and project it belongs to.' },
    { icon: <Sparkles className="size-5" />, title: 'AI summary and reply', text: 'See how the client feels, what is pending and get a suggested reply.' },
  ]

  return (
    <Card className="wa-rise overflow-hidden">
      <div className="flex flex-col items-center px-6 pt-12 pb-8 text-center">
        <span className="mb-5 flex size-16 animate-float items-center justify-center rounded-2xl bg-brand-50 text-brand-600 ring-8 ring-brand-50/60"><Lock className="size-7" /></span>
        <h2 className="text-xl font-bold tracking-tight text-ink-900 sm:text-2xl">WhatsApp chat is part of the Enterprise plan</h2>
        <p className="mt-2 max-w-md text-[13.5px] text-ink-500">{plan ? `Your ${plan} plan does not include it.` : 'Your plan does not include it.'} Upgrade to chat with every client from your own WhatsApp number, right inside Spider PLMS.</p>
        <Link to="/app/settings" className="mt-6 inline-flex h-11 items-center rounded-xl bg-brand-600 px-5 text-sm font-semibold text-white shadow-lg shadow-brand-600/25 transition hover:-translate-y-0.5 hover:bg-brand-700">Contact us to upgrade</Link>
      </div>
      <ul className="grid gap-4 border-t border-line bg-canvas p-5 sm:grid-cols-3 sm:p-6">
        {perks.map((perk, i) => (
          <li key={perk.title} className="wa-rise rounded-2xl border border-line bg-surface p-4" style={{ animationDelay: `${120 + i * 90}ms` }}>
            <span className="flex size-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">{perk.icon}</span>
            <div className="mt-3 text-[13.5px] font-bold text-ink-900">{perk.title}</div>
            <p className="mt-1 text-[12.5px] leading-relaxed text-ink-500">{perk.text}</p>
          </li>
        ))}
      </ul>
    </Card>
  )
}

/* ------------------------------------------------------------------ link a phone */

const QR_LIFETIME = 40

/** Shows the QR code and waits for the phone to link. The QR is refreshed before it expires. */
function LinkPhone({ canManage, unreachable }: { canManage: boolean; unreachable: boolean }) {
  const toast = useToast()
  const queryClient = useQueryClient()
  const [qr, setQr] = useState<string | null>(null)
  const [secondsLeft, setSecondsLeft] = useState(QR_LIFETIME)
  const [linked, setLinked] = useState(false)

  const connect = useMutation({
    mutationFn: whatsappApi.connect,
    onSuccess: (result) => {
      setQr(result.qr)
      setSecondsLeft(QR_LIFETIME)
      if (result.state === 'open') setLinked(true)
    },
    onError: (err) => toast.error(errorMessage(err)),
  })

  useEffect(() => {
    if (!qr || linked) return
    const watch = window.setInterval(async () => {
      const latest = await whatsappApi.status().catch(() => null)
      if (latest?.state === 'open') setLinked(true)
    }, 3000)
    const tick = window.setInterval(() => setSecondsLeft((s) => Math.max(0, s - 1)), 1000)
    return () => {
      window.clearInterval(watch)
      window.clearInterval(tick)
    }
  }, [qr, linked])

  // Refresh the code when its time is up.
  useEffect(() => {
    if (qr && !linked && secondsLeft === 0 && !connect.isPending) connect.mutate()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [secondsLeft])

  // Let the success state show for a moment before the inbox takes over.
  useEffect(() => {
    if (!linked) return
    const done = window.setTimeout(() => {
      toast.success('WhatsApp linked.')
      queryClient.invalidateQueries({ queryKey: STATUS_KEY })
    }, 1500)
    return () => window.clearTimeout(done)
  }, [linked, queryClient, toast])

  const steps = [
    { icon: <Smartphone className="size-4" />, text: <>Open <b>WhatsApp</b> on your phone.</> },
    { icon: <Users className="size-4" />, text: <>Go to <b>Settings → Linked devices</b>, then tap <b>Link a device</b>.</> },
    { icon: <QrCode className="size-4" />, text: <>Point your phone at the QR code.</> },
  ]

  return (
    <Card className="wa-rise overflow-hidden">
      <div className="grid lg:grid-cols-[1.1fr_1fr]">
        <div className="p-6 sm:p-9">
          <Badge className="bg-emerald-50 text-emerald-700 ring-emerald-200">Step 1 of 1 · Link your number</Badge>
          <h2 className="mt-4 text-2xl font-bold tracking-tight text-ink-900">Connect WhatsApp in under a minute</h2>
          <p className="mt-2 max-w-md text-[13.5px] leading-relaxed text-ink-500">Messages are sent from, and received on, the number you link. Your phone stays the owner of the account and you can unlink at any time.</p>

          <ol className="mt-7 space-y-3">
            {steps.map((step, i) => (
              <li key={i} className="wa-rise flex items-center gap-3.5 rounded-2xl border border-line bg-canvas px-4 py-3 text-[13.5px] text-ink-700" style={{ animationDelay: `${150 + i * 110}ms` }}>
                <span className="flex size-9 shrink-0 items-center justify-center rounded-xl bg-surface text-brand-600 shadow-card ring-1 ring-line">{step.icon}</span>
                <span>{step.text}</span>
                <span className="ml-auto text-[11px] font-bold text-ink-400">{i + 1}</span>
              </li>
            ))}
          </ol>

          <p className="mt-6 flex items-center gap-2 text-[12.5px] text-ink-500"><ShieldCheck className="size-4 text-emerald-600" /> Your messages stay in your workspace and are never shared with other workspaces.</p>
          {unreachable && <div className="mt-5"><Alert>The WhatsApp server could not be reached right now. Try again shortly.</Alert></div>}
        </div>

        <div className="relative flex flex-col items-center justify-center gap-5 overflow-hidden border-t border-line bg-gradient-to-b from-emerald-50/70 to-canvas p-6 sm:p-9 lg:border-t-0 lg:border-l">
          {linked ? (
            <div className="flex flex-col items-center text-center animate-pop-in">
              <span className="flex size-24 items-center justify-center rounded-full bg-emerald-500 text-white shadow-lg shadow-emerald-500/30"><Check className="size-12" strokeWidth={3} /></span>
              <div className="mt-5 text-xl font-bold text-ink-900">Phone linked</div>
              <p className="mt-1 text-[13px] text-ink-500">Opening your inbox…</p>
            </div>
          ) : qr ? (
            <>
              <div className="relative">
                <span className="pointer-events-none absolute -inset-3 rounded-[28px] bg-emerald-400/20 blur-xl" aria-hidden />
                <div className="relative overflow-hidden rounded-3xl border border-line bg-white p-4 shadow-pop">
                  <img src={qr} alt="WhatsApp QR code. Scan it from Linked devices." className="size-56 sm:size-64" />
                  <span className="wa-scan-line pointer-events-none absolute inset-x-3 h-0.5 rounded-full bg-emerald-500/80 shadow-[0_0_14px_3px] shadow-emerald-400/60" aria-hidden />
                  {(['top-2 left-2 border-t-2 border-l-2 rounded-tl-xl', 'top-2 right-2 border-t-2 border-r-2 rounded-tr-xl', 'bottom-2 left-2 border-b-2 border-l-2 rounded-bl-xl', 'bottom-2 right-2 border-b-2 border-r-2 rounded-br-xl'] as const).map((c) => (
                    <span key={c} className={cn('pointer-events-none absolute size-6 border-emerald-500', c)} aria-hidden />
                  ))}
                </div>
              </div>
              <div className="w-full max-w-[260px]">
                <div className="flex items-center justify-between text-[12px] text-ink-500">
                  <span className="flex items-center gap-1.5"><Spinner className="size-3" /> Waiting for your phone</span>
                  <span className="tabular">New code in {secondsLeft}s</span>
                </div>
                <div className="mt-2 h-1 overflow-hidden rounded-full bg-slate-200/80"><div className="h-full rounded-full bg-emerald-500 transition-[width] duration-1000 ease-linear" style={{ width: `${(secondsLeft / QR_LIFETIME) * 100}%` }} /></div>
              </div>
            </>
          ) : (
            <>
              <div className="flex size-52 items-center justify-center rounded-3xl border-2 border-dashed border-emerald-200 bg-surface text-emerald-300"><QrCode className="size-20" strokeWidth={1.2} /></div>
              {canManage ? (
                <Button size="lg" className="!bg-emerald-600 shadow-lg shadow-emerald-600/25 hover:!bg-emerald-700" icon={<QrCode className="size-4" />} loading={connect.isPending} onClick={() => connect.mutate()}>Generate QR code</Button>
              ) : (
                <p className="max-w-xs text-center text-[13px] text-ink-500">Ask an organization admin to link the workspace's WhatsApp number.</p>
              )}
            </>
          )}
        </div>
      </div>
    </Card>
  )
}

/* ------------------------------------------------------------------ inbox */

function Inbox({ conversations, loading, search, onSearch }: { conversations: WhatsAppConversation[]; loading: boolean; search: string; onSearch: (v: string) => void }) {
  const [selected, setSelected] = useState<number | null>(null)
  const [tab, setTab] = useState<'all' | 'unread'>('all')

  const unreadCount = conversations.filter((c) => c.unread > 0).length
  const visible = tab === 'unread' ? conversations.filter((c) => c.unread > 0) : conversations
  const current = conversations.find((c) => c.client_id === selected) ?? null

  return (
    <Card className="wa-rise overflow-hidden">
      <div className="grid h-[calc(100dvh-20rem)] min-h-[560px] lg:grid-cols-[360px_1fr]">
        <section className={cn('flex min-h-0 flex-col bg-surface lg:border-r lg:border-line', selected !== null && 'hidden lg:flex')} aria-label="Conversations">
          <div className="space-y-3 px-4 pt-4">
            <div className="relative">
              <Search className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-ink-400" />
              <Input value={search} onChange={(e) => onSearch(e.target.value)} placeholder="Search clients or numbers" aria-label="Search conversations" className="h-11 rounded-xl bg-canvas pl-10" />
            </div>
            <Tabs
              className="!mx-0 !px-0"
              value={tab}
              onChange={setTab}
              tabs={[{ value: 'all', label: 'All', count: conversations.length }, { value: 'unread', label: 'Unread', count: unreadCount }]}
            />
          </div>

          <ul className="scrollbar-thin min-h-0 flex-1 overflow-y-auto">
            {loading ? (
              Array.from({ length: 6 }).map((_, i) => <li key={i} className="flex items-center gap-3 px-4 py-3.5"><Skeleton className="size-11 rounded-full" /><div className="flex-1 space-y-2"><Skeleton className="h-3.5 w-2/3" /><Skeleton className="h-3 w-full" /></div></li>)
            ) : visible.length === 0 ? (
              <li className="px-6 py-16 text-center">
                <span className="mx-auto flex size-12 items-center justify-center rounded-2xl bg-canvas text-ink-400"><MessageCircle className="size-6" /></span>
                <p className="mt-3 text-[13.5px] font-semibold text-ink-700">{search ? 'No client matches your search' : tab === 'unread' ? 'No unread messages' : 'No conversations yet'}</p>
                <p className="mt-1 text-[12.5px] text-ink-500">{search || tab === 'unread' ? 'Try another search or filter.' : 'Add a WhatsApp number to a client and they will appear here.'}</p>
              </li>
            ) : (
              visible.map((c, i) => <ConversationRow key={c.client_id} conversation={c} index={i} active={c.client_id === selected} onOpen={() => setSelected(c.client_id)} />)
            )}
          </ul>
        </section>

        <section className={cn('flex min-h-0 flex-col', selected === null && 'hidden lg:flex')} aria-label="Chat">
          {current ? (
            <ChatThread key={current.client_id} conversation={current} onBack={() => setSelected(null)} />
          ) : (
            <div className="wa-pattern flex flex-1 items-center justify-center p-6">
              <div className="wa-rise max-w-xs text-center">
                <span className="mx-auto flex size-16 items-center justify-center rounded-3xl bg-surface text-emerald-600 shadow-card ring-1 ring-line"><MessageCircle className="size-8" /></span>
                <h3 className="mt-4 text-[15px] font-bold text-ink-900">Select a conversation</h3>
                <p className="mt-1 text-[13px] text-ink-500">Pick a client on the left to read the chat and reply from your own number.</p>
              </div>
            </div>
          )}
        </section>
      </div>
    </Card>
  )
}

function ConversationRow({ conversation: c, active, index, onOpen }: { conversation: WhatsAppConversation; active: boolean; index: number; onOpen: () => void }) {
  const last = c.last_message

  return (
    <li className="wa-row-in" style={{ animationDelay: `${Math.min(index, 9) * 35}ms` }}>
      <button
        onClick={onOpen}
        aria-current={active}
        className={cn('group relative flex w-full items-center gap-3.5 px-4 py-3.5 text-left transition-colors hover:bg-canvas', active && 'bg-brand-50/60 hover:bg-brand-50/60')}
      >
        <span className={cn('absolute inset-y-2 left-0 w-1 rounded-r-full bg-brand-600 transition-transform duration-200', active ? 'scale-y-100' : 'scale-y-0')} aria-hidden />
        <Avatar name={c.name} size="lg" className="!size-11 transition-transform duration-200 group-hover:scale-105" />
        <span className="min-w-0 flex-1">
          <span className="flex items-baseline justify-between gap-2">
            <span className={cn('truncate text-[14px] text-ink-900', c.unread > 0 ? 'font-bold' : 'font-semibold')}>{c.name}</span>
            {last?.at && <span className={cn('shrink-0 text-[11px]', c.unread > 0 ? 'font-bold text-emerald-600' : 'text-ink-400')}>{relativeTime(last.at)}</span>}
          </span>
          <span className="mt-0.5 flex items-center justify-between gap-2">
            <span className={cn('flex min-w-0 items-center gap-1 text-[12.5px]', c.unread > 0 ? 'font-medium text-ink-700' : 'text-ink-500')}>
              {last?.direction === 'out' && (last.status === 'failed' ? <AlertCircle className="size-3.5 shrink-0 text-rose-500" /> : <CheckCheck className="size-3.5 shrink-0 text-emerald-500" />)}
              <span className="truncate">{last ? last.body : formatNumber(c.number)}</span>
            </span>
            {c.unread > 0 && <span key={c.unread} className="wa-badge flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-emerald-600 px-1.5 text-[11px] font-bold text-white">{c.unread}</span>}
          </span>
        </span>
      </button>
    </li>
  )
}

interface PendingMessage { key: number; body: string }

function ChatThread({ conversation, onBack }: { conversation: WhatsAppConversation; onBack: () => void }) {
  const toast = useToast()
  const queryClient = useQueryClient()
  const [draft, setDraft] = useState('')
  const [pending, setPending] = useState<PendingMessage[]>([])
  const [atBottom, setAtBottom] = useState(true)
  const scroller = useRef<HTMLDivElement>(null)
  const bottom = useRef<HTMLDivElement>(null)
  const field = useRef<HTMLTextAreaElement>(null)
  const seen = useRef<Set<number> | null>(null)
  const key = ['whatsapp', 'thread', conversation.client_id]

  const { data, isLoading } = useQuery({ queryKey: key, queryFn: () => whatsappApi.thread(conversation.client_id), refetchInterval: 5000 })
  const messages = data?.data ?? []

  // Messages that arrive after the first load slide in; the ones already there do not.
  const fresh = seen.current === null ? null : new Set(messages.filter((m) => !seen.current!.has(m.id)).map((m) => m.id))
  useEffect(() => {
    if (data) seen.current = new Set(data.data.map((m) => m.id))
  }, [data])

  // Opening a thread marks it read on the server, so refresh the list's unread badges.
  useEffect(() => {
    if (data) queryClient.invalidateQueries({ queryKey: ['whatsapp', 'conversations'] })
  }, [data?.data.length, queryClient]) // eslint-disable-line react-hooks/exhaustive-deps

  const toBottom = (smooth = true) => bottom.current?.scrollIntoView({ block: 'end', behavior: smooth ? 'smooth' : 'auto' })

  useLayoutEffect(() => {
    if (atBottom) toBottom(seen.current !== null)
  }, [messages.length, pending.length]) // eslint-disable-line react-hooks/exhaustive-deps

  const send = useMutation({
    mutationFn: (body: string) => whatsappApi.send(conversation.client_id, body),
    onError: (err) => toast.error(errorMessage(err)),
  })

  const submit = (e?: FormEvent) => {
    e?.preventDefault()
    const body = draft.trim()
    if (!body || !conversation.number) return
    const item = { key: Date.now(), body }
    setPending((p) => [...p, item])
    setDraft('')
    setAtBottom(true)
    if (field.current) field.current.style.height = ''
    send.mutate(body, {
      onSettled: async () => {
        await queryClient.invalidateQueries({ queryKey: ['whatsapp'] })
        setPending((p) => p.filter((x) => x.key !== item.key))
      },
    })
  }

  const onKey = (e: KeyboardEvent<HTMLTextAreaElement>) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault()
      submit()
    }
  }

  const grow = (el: HTMLTextAreaElement) => {
    el.style.height = 'auto'
    el.style.height = `${Math.min(el.scrollHeight, 140)}px`
  }

  let lastDay = ''

  return (
    <>
      <header className="flex items-center gap-3 border-b border-line bg-surface px-4 py-3 sm:px-5">
        <button onClick={onBack} className="-ml-1 rounded-lg p-1.5 text-ink-500 transition hover:bg-slate-100 lg:hidden" aria-label="Back to conversations"><ArrowLeft className="size-5" /></button>
        <Avatar name={conversation.name} size="lg" className="!size-10" />
        <div className="min-w-0 flex-1">
          <div className="truncate text-[14.5px] font-bold text-ink-900">{conversation.name}</div>
          <div className="flex items-center gap-1.5 truncate text-[12px] text-ink-500">
            <span className="size-1.5 shrink-0 rounded-full bg-emerald-500" aria-hidden />
            {conversation.contact_person ? `${conversation.contact_person} · ` : ''}{formatNumber(conversation.number)}
          </div>
        </div>
      </header>

      <div className="relative min-h-0 flex-1">
        <div
          ref={scroller}
          onScroll={(e) => {
            const el = e.currentTarget
            setAtBottom(el.scrollHeight - el.scrollTop - el.clientHeight < 80)
          }}
          className="wa-pattern scrollbar-thin h-full space-y-1.5 overflow-y-auto px-3.5 py-5 sm:px-8"
          aria-live="polite"
          aria-label={`Chat with ${conversation.name}`}
        >
          {isLoading ? (
            <div className="space-y-3">{[0, 1, 2, 3].map((i) => <Skeleton key={i} className={cn('h-10 rounded-2xl', i % 2 ? 'ml-auto' : '')} />)}</div>
          ) : messages.length === 0 && pending.length === 0 ? (
            <div className="wa-rise mx-auto my-16 max-w-xs text-center">
              <span className="mx-auto flex size-14 items-center justify-center rounded-2xl bg-surface text-emerald-600 shadow-card ring-1 ring-line"><MessageCircle className="size-7" /></span>
              <p className="mt-3 text-[14px] font-bold text-ink-900">Start the conversation</p>
              <p className="mt-1 text-[12.5px] text-ink-500">No messages with {conversation.name} yet. Say hello below.</p>
            </div>
          ) : (
            messages.map((m) => {
              const day = formatDate(m.sent_at ?? m.created_at)
              const showDay = day !== lastDay
              lastDay = day
              return (
                <div key={m.id}>
                  {showDay && <div className="my-4 flex justify-center"><span className="rounded-full bg-surface/90 px-3.5 py-1 text-[11px] font-bold tracking-wide text-ink-500 shadow-card ring-1 ring-line backdrop-blur">{day}</span></div>}
                  <Bubble message={m} animate={fresh?.has(m.id) ?? false} />
                </div>
              )
            })
          )}

          {pending.map((p) => (
            <div key={p.key} className="wa-msg-in flex justify-end">
              <div className="max-w-[85%] rounded-2xl rounded-br-md bg-emerald-600/85 px-3.5 py-2 text-[13.5px] text-white shadow-card sm:max-w-[68%]">
                <p className="break-words whitespace-pre-wrap">{p.body}</p>
                <div className="mt-0.5 flex items-center justify-end gap-1 text-[10.5px] text-white/75"><Clock className="size-3 animate-pulse" /> Sending</div>
              </div>
            </div>
          ))}
          <div ref={bottom} />
        </div>

        {!atBottom && (
          <button onClick={() => toBottom()} className="wa-msg-in absolute right-5 bottom-4 flex size-10 items-center justify-center rounded-full bg-surface text-ink-700 shadow-pop ring-1 ring-line transition hover:-translate-y-0.5" aria-label="Jump to the latest message">
            <ArrowDown className="size-5" />
          </button>
        )}
      </div>

      <div className="border-t border-line bg-surface px-3 pt-2.5 pb-3 sm:px-5 sm:pb-4">
        {conversation.number && (
          <div className="scrollbar-thin -mx-1 mb-2.5 flex gap-2 overflow-x-auto px-1 pb-0.5" aria-label="Quick replies">
            {QUICK_REPLIES.map((text) => (
              <button
                key={text}
                type="button"
                onClick={() => { setDraft((d) => (d ? `${d} ${text}` : text)); field.current?.focus() }}
                className="shrink-0 rounded-full border border-line bg-canvas px-3 py-1.5 text-[12px] font-medium text-ink-700 transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-800"
              >
                {text}
              </button>
            ))}
          </div>
        )}
        <form onSubmit={submit} className="flex items-end gap-2.5">
          <div className="relative flex-1">
            <textarea
              ref={field}
              rows={1}
              value={draft}
              onChange={(e) => { setDraft(e.target.value); grow(e.target) }}
              onKeyDown={onKey}
              maxLength={4000}
              disabled={!conversation.number}
              placeholder={conversation.number ? 'Type a message' : 'Add a WhatsApp number to this client to message them'}
              aria-label="Message"
              className="max-h-36 min-h-11 w-full resize-none rounded-2xl border border-line bg-canvas px-4 py-2.5 text-[14px] text-ink-900 transition placeholder:text-ink-400 focus:border-emerald-400 focus:bg-surface focus:ring-4 focus:ring-emerald-500/10 focus:outline-none disabled:opacity-60"
            />
            {draft.length > 3500 && <span className="tabular absolute right-3 bottom-1.5 text-[10.5px] text-ink-400">{draft.length}/4000</span>}
          </div>
          <button
            type="submit"
            disabled={!draft.trim() || !conversation.number}
            aria-label="Send message"
            className="flex size-11 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white shadow-lg shadow-emerald-600/25 transition duration-150 hover:scale-105 hover:bg-emerald-700 active:scale-95 disabled:scale-100 disabled:bg-slate-300 disabled:shadow-none"
          >
            <Send className="size-[18px] translate-x-px" />
          </button>
        </form>
        <p className="mt-1.5 hidden text-[11px] text-ink-400 sm:block">Enter to send · Shift+Enter for a new line</p>
      </div>
    </>
  )
}

function Bubble({ message: m, animate }: { message: ClientMessage; animate: boolean }) {
  const out = m.direction === 'out'

  return (
    <div className={cn('flex', out ? 'justify-end' : 'justify-start', animate && 'wa-msg-in')}>
      <div className={cn(
        'max-w-[85%] rounded-2xl px-3.5 py-2 text-[13.5px] leading-relaxed shadow-card sm:max-w-[68%]',
        out ? 'rounded-br-md bg-emerald-600 text-white' : 'rounded-bl-md border border-line bg-surface text-ink-900',
        out && m.status === 'failed' && 'bg-rose-600',
      )}>
        <p className="break-words whitespace-pre-wrap">{m.body}</p>
        <div className={cn('mt-0.5 flex items-center justify-end gap-1 text-[10.5px]', out ? 'text-white/75' : 'text-ink-400')}>
          {m.project_name && <span className={cn('mr-auto max-w-[60%] truncate rounded-full px-1.5 py-px text-[10px] font-semibold', out ? 'bg-white/15' : 'bg-slate-100')}>{m.project_name}</span>}
          {clock(m.sent_at ?? m.created_at)}
          {out && (m.status === 'failed' ? <AlertCircle className="size-3.5" aria-label="Failed to send" /> : m.status === 'sent' ? <CheckCheck className="size-3.5" aria-label="Sent" /> : null)}
        </div>
        {out && m.status === 'failed' && m.error && <p className="mt-1 text-[11.5px] text-rose-100">{m.error}</p>}
      </div>
    </div>
  )
}
