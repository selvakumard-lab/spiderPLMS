import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { MessageCircle, Sparkles } from 'lucide-react'
import { Alert, Badge, Button, Card, CardHeader, Skeleton } from '@/components/ui'
import { DraftModal, MessageEditorModal } from '@/components/tracker/messages'
import { useToast } from '@/context/ToastContext'
import { errorMessage } from '@/lib/api'
import { messagesApi } from '@/lib/endpoints'
import { cn, formatDateTime } from '@/lib/utils'
import type { ChatEvaluation, ClientMessage } from '@/types/tracker'

const SENTIMENT: Record<ChatEvaluation['sentiment'], string> = {
  positive: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
  neutral: 'bg-slate-100 text-slate-600 ring-slate-200',
  negative: 'bg-orange-50 text-orange-700 ring-orange-200',
  urgent: 'bg-rose-50 text-rose-700 ring-rose-200',
}

/** The project's WhatsApp conversation with the client, DeepSeek's evaluation of it, and quick drafting. */
export function ClientChatSection({ projectId, clientName }: { projectId: number; clientName: string | null }) {
  const toast = useToast()
  const queryClient = useQueryClient()
  const [drafting, setDrafting] = useState(false)
  const [reviewing, setReviewing] = useState<ClientMessage | null>(null)
  const { data, isLoading } = useQuery({ queryKey: ['messages', 'chat', projectId], queryFn: () => messagesApi.chat(projectId), refetchInterval: 30_000 })

  const evaluate = useMutation({
    mutationFn: () => messagesApi.evaluateChat(projectId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['messages', 'chat', projectId] })
      toast.success('Chat evaluated.')
    },
    onError: (err) => toast.error(errorMessage(err)),
  })

  const draftReply = useMutation({
    mutationFn: (body: string) => messagesApi.draft(projectId, { channel: 'whatsapp', reason: 'custom', body }),
    onSuccess: (message) => {
      queryClient.invalidateQueries({ queryKey: ['messages'] })
      setReviewing(message)
    },
    onError: (err) => toast.error(errorMessage(err)),
  })

  const evaluation = data?.evaluation

  return (
    <Card>
      <CardHeader title="Client communication" subtitle={clientName ? `WhatsApp chat with ${clientName}` : 'Choose a client for this project to message them'}
        action={<div className="flex gap-2">
          <Button size="sm" variant="secondary" icon={<Sparkles className="size-3.5" />} loading={evaluate.isPending} disabled={!data?.messages.length} onClick={() => evaluate.mutate()}>Evaluate chat</Button>
          <Button size="sm" icon={<MessageCircle className="size-3.5" />} onClick={() => setDrafting(true)}>Draft message</Button>
        </div>} />
      <div className="space-y-4 p-5 sm:p-6">
        {isLoading ? <Skeleton className="h-32" /> : (
          <>
            {data && !data.configured && <Alert tone="info">WhatsApp chat is not available for this workspace yet: it needs the Enterprise plan and a linked phone (WhatsApp page). Email drafts work now.</Alert>}

            {evaluation && (
              <div className="rounded-xl border border-line bg-canvas p-4">
                <div className="flex flex-wrap items-center gap-2">
                  <span className="text-[13px] font-bold text-ink-900">AI evaluation</span>
                  <Badge className={cn('capitalize', SENTIMENT[evaluation.sentiment])}>{evaluation.sentiment}</Badge>
                  <span className="text-xs text-ink-400">{evaluation.messages_considered} messages · {formatDateTime(evaluation.created_at)}{evaluation.source === 'fallback' ? ' · basic summary' : ''}</span>
                </div>
                <p className="mt-2 text-[13.5px] leading-relaxed text-ink-700">{evaluation.summary}</p>
                {evaluation.pending_items.length > 0 && (
                  <ul className="mt-2 list-disc space-y-0.5 pl-5 text-[13px] text-ink-700">{evaluation.pending_items.map((i) => <li key={i}>{i}</li>)}</ul>
                )}
                {evaluation.suggested_reply && (
                  <div className="mt-3 rounded-lg border border-line bg-surface p-3">
                    <div className="text-xs font-semibold text-ink-500">Suggested reply</div>
                    <p className="mt-1 text-[13.5px] text-ink-900">{evaluation.suggested_reply}</p>
                    <Button className="mt-2" size="sm" variant="secondary" loading={draftReply.isPending} onClick={() => draftReply.mutate(evaluation.suggested_reply!)}>Use as draft</Button>
                  </div>
                )}
              </div>
            )}

            {!data?.messages.length ? (
              <p className="py-6 text-center text-[13.5px] text-ink-500">No WhatsApp messages on this project yet.</p>
            ) : (
              <ul className="scrollbar-thin max-h-96 space-y-2.5 overflow-y-auto pr-1" aria-label="WhatsApp conversation">
                {data.messages.map((m) => (
                  <li key={m.id} className={cn('flex', m.direction === 'out' ? 'justify-end' : 'justify-start')}>
                    <div className={cn('max-w-[85%] rounded-2xl px-3.5 py-2.5 text-[13.5px]', m.direction === 'out' ? 'rounded-br-md bg-brand-600 text-white' : 'rounded-bl-md border border-line bg-surface text-ink-900')}>
                      <p className="whitespace-pre-wrap">{m.body}</p>
                      <div className={cn('mt-1 text-[11px]', m.direction === 'out' ? 'text-white/75' : 'text-ink-400')}>
                        {formatDateTime(m.sent_at ?? m.created_at)}{m.status === 'failed' ? ' · failed' : ''}
                      </div>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </>
        )}
      </div>

      <DraftModal open={drafting} onClose={() => setDrafting(false)} projectId={projectId} onCreated={(m) => setReviewing(m)} />
      <MessageEditorModal message={reviewing} onClose={() => { setReviewing(null); queryClient.invalidateQueries({ queryKey: ['messages', 'chat', projectId] }) }} />
    </Card>
  )
}
