import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { BellRing, Pencil, Send, Sparkles, Trash2 } from 'lucide-react'
import { Badge, Button, Card, EmptyState, PageHeader, Skeleton } from '@/components/ui'
import { ConfirmButton, Tabs } from '@/components/ui/extra'
import { ChannelIcon, DraftModal, MessageEditorModal, MessageStatusBadge, REASON_LABELS } from '@/components/tracker/messages'
import { useAuth } from '@/context/AuthContext'
import { useToast } from '@/context/ToastContext'
import { errorMessage } from '@/lib/api'
import { messagesApi } from '@/lib/endpoints'
import { formatDateTime } from '@/lib/utils'
import type { ClientMessage, MessageStatus } from '@/types/tracker'

type Tab = Extract<MessageStatus, 'draft' | 'sent' | 'failed'>

/** AI-written reminders and alerts for clients. Drafts wait here until a person reviews and sends them. */
export default function RemindersPage() {
  const { tenant } = useAuth()
  const toast = useToast()
  const queryClient = useQueryClient()
  const [tab, setTab] = useState<Tab>('draft')
  const [drafting, setDrafting] = useState(false)
  const [open, setOpen] = useState<ClientMessage | null>(null)
  const { data, isLoading } = useQuery({ queryKey: ['messages', 'all'], queryFn: () => messagesApi.list({}) })

  const outgoing = (data ?? []).filter((m) => m.direction === 'out')
  const counts = { draft: outgoing.filter((m) => m.status === 'draft').length, sent: outgoing.filter((m) => m.status === 'sent').length, failed: outgoing.filter((m) => m.status === 'failed').length }
  const rows = outgoing.filter((m) => m.status === tab)

  const refresh = () => queryClient.invalidateQueries({ queryKey: ['messages'] })
  const remove = useMutation({
    mutationFn: (id: number) => messagesApi.remove(id),
    onSuccess: () => {
      refresh()
      toast.success('Draft deleted.')
    },
    onError: (err) => toast.error(errorMessage(err)),
  })
  const send = useMutation({
    mutationFn: (id: number) => messagesApi.send(id),
    onSuccess: () => {
      refresh()
      toast.success('Message sent.')
    },
    onError: (err) => {
      refresh()
      toast.error(errorMessage(err))
    },
  })

  return (
    <>
      <PageHeader eyebrow={tenant?.name} title="Reminders & Alerts"
        description="DeepSeek drafts client emails and WhatsApp messages from real project data. Nothing is sent until you review it."
        actions={<Button icon={<Sparkles className="size-4" />} onClick={() => setDrafting(true)}>Draft with AI</Button>} />

      <Card>
        <div className="px-4 sm:px-6">
          <Tabs<Tab> value={tab} onChange={setTab} tabs={[
            { value: 'draft', label: 'Drafts to review', count: counts.draft },
            { value: 'sent', label: 'Sent', count: counts.sent },
            { value: 'failed', label: 'Failed', count: counts.failed },
          ]} />
        </div>

        <div className="p-4 sm:p-6">
          {isLoading ? (
            <div className="space-y-3">{[0, 1, 2].map((i) => <Skeleton key={i} className="h-20" />)}</div>
          ) : !rows.length ? (
            <EmptyState icon={<BellRing className="size-6" />}
              title={tab === 'draft' ? 'No drafts waiting' : tab === 'sent' ? 'Nothing sent yet' : 'No failed messages'}
              description={tab === 'draft' ? 'Draft a message now, or let the daily check prepare reminders for overdue client inputs and delayed projects.' : undefined}
              action={tab === 'draft' ? <Button onClick={() => setDrafting(true)}>Draft with AI</Button> : undefined} />
          ) : (
            <ul className="divide-y divide-line">
              {rows.map((m) => (
                <li key={m.id} className="flex flex-col gap-3 py-4 first:pt-0 last:pb-0 lg:flex-row lg:items-start lg:gap-5">
                  <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><ChannelIcon channel={m.channel} /></span>
                  <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                      <span className="text-[14px] font-bold text-ink-900">{m.subject || (m.channel === 'whatsapp' ? 'WhatsApp message' : 'Email')}</span>
                      <MessageStatusBadge status={m.status} />
                      {m.ai_generated && <Badge className="bg-brand-50 text-brand-700 ring-brand-200"><Sparkles className="size-3" /> AI</Badge>}
                    </div>
                    <div className="mt-0.5 text-[12.5px] text-ink-500">
                      {m.client_name ?? 'No client'} · {m.project_name ?? 'No project'} · {m.reason ? REASON_LABELS[m.reason] : 'Message'} · {formatDateTime(m.sent_at ?? m.created_at)}
                    </div>
                    <p className="mt-2 line-clamp-2 text-[13.5px] text-ink-700">{m.body}</p>
                    {m.error && <p className="mt-1.5 text-[12.5px] font-medium text-rose-600">{m.error}</p>}
                  </div>
                  <div className="flex shrink-0 gap-2">
                    {m.status !== 'sent' ? (
                      <>
                        <Button size="sm" variant="secondary" icon={<Pencil className="size-3.5" />} onClick={() => setOpen(m)}>Review</Button>
                        <Button size="sm" icon={<Send className="size-3.5" />} loading={send.isPending && send.variables === m.id} onClick={() => send.mutate(m.id)}>Send</Button>
                        <ConfirmButton danger title="Delete draft" message="Delete this draft?" confirmLabel="Delete" onConfirm={() => remove.mutateAsync(m.id)}
                          trigger={(o) => <Button size="sm" variant="ghost" icon={<Trash2 className="size-3.5" />} onClick={o} aria-label="Delete draft" />} />
                      </>
                    ) : (
                      <Button size="sm" variant="secondary" onClick={() => setOpen(m)}>View</Button>
                    )}
                  </div>
                </li>
              ))}
            </ul>
          )}
        </div>
      </Card>

      <DraftModal open={drafting} onClose={() => setDrafting(false)} onCreated={(m) => { setTab('draft'); setOpen(m) }} />
      <MessageEditorModal message={open} onClose={() => setOpen(null)} />
    </>
  )
}
