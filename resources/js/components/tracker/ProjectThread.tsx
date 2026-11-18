import { useEffect, useRef, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { MessageCircle, Send } from 'lucide-react'
import { Button, Card, CardHeader, EmptyState, Skeleton } from '@/components/ui'
import { Textarea } from '@/components/ui/extra'
import { useToast } from '@/context/ToastContext'
import { errorMessage, fieldErrors } from '@/lib/api'
import { cn, formatDateTime, initials } from '@/lib/utils'
import type { ChatMessage } from '@/types/tracker'

interface Props {
  /** `team` when the team member is writing, `client` in the portal. Your own messages sit on the right. */
  side: 'team' | 'client'
  queryKey: readonly unknown[]
  load: () => Promise<ChatMessage[]>
  send: (body: string) => Promise<ChatMessage>
  title?: string
  subtitle: string
  emptyText: string
  /** Set when messages cannot be sent yet, for example no client is linked to the project. */
  disabledReason?: string
}

/** The two-way chat thread between a project's team and its client. Refreshes itself every few seconds. */
export function ProjectThread({ side, queryKey, load, send, title = 'Conversation', subtitle, emptyText, disabledReason }: Props) {
  const toast = useToast()
  const queryClient = useQueryClient()
  const listRef = useRef<HTMLDivElement>(null)
  const [body, setBody] = useState('')
  const [error, setError] = useState<string | undefined>()

  const { data: messages, isLoading } = useQuery({ queryKey, queryFn: load, refetchInterval: 8_000, refetchIntervalInBackground: false })

  const post = useMutation({
    mutationFn: (text: string) => send(text),
    onSuccess: () => {
      setBody('')
      setError(undefined)
      queryClient.invalidateQueries({ queryKey })
    },
    onError: (err) => {
      const message = fieldErrors(err).body ?? errorMessage(err, 'Could not send your message.')
      setError(message)
      toast.error(message)
    },
  })

  // Keep the newest message in view without scrolling the whole page.
  const count = messages?.length ?? 0
  useEffect(() => {
    const list = listRef.current
    if (list) list.scrollTop = list.scrollHeight
  }, [count])

  function submit() {
    const text = body.trim()
    if (!text) return setError('Write a message first.')
    if (post.isPending) return
    post.mutate(text)
  }

  return (
    <Card>
      <CardHeader title={title} subtitle={subtitle} />
      <div ref={listRef} className="max-h-[26rem] min-h-40 space-y-4 overflow-y-auto bg-canvas/60 p-4 sm:p-5" aria-live="polite" aria-label="Messages">
        {isLoading ? (
          <div className="space-y-3"><Skeleton className="h-12 w-2/3" /><Skeleton className="ml-auto h-12 w-1/2" /></div>
        ) : count === 0 ? (
          <EmptyState icon={<MessageCircle className="size-6" />} title="No messages yet" description={emptyText} />
        ) : (
          messages!.map((m) => {
            const mine = m.sender === side
            return (
              <div key={m.id} className={cn('flex items-end gap-2.5 motion-safe:animate-pop-in', mine && 'flex-row-reverse')}>
                <span className={cn('flex size-8 shrink-0 items-center justify-center rounded-full text-[11px] font-bold', m.sender === 'team' ? 'bg-brand-600 text-white' : 'bg-violet-100 text-violet-700')}>{initials(m.sender_name ?? '?')}</span>
                <div className={cn('max-w-[82%] min-w-0 sm:max-w-[70%]', mine && 'text-right')}>
                  <div className="mb-1 text-[11.5px] font-semibold text-ink-500">
                    {mine ? 'You' : m.sender_name ?? (m.sender === 'team' ? 'Team' : 'Client')} · <span className="font-medium">{formatDateTime(m.created_at)}</span>
                  </div>
                  <p className={cn('inline-block rounded-2xl px-3.5 py-2.5 text-left text-[13.5px] leading-relaxed break-words whitespace-pre-line', mine ? 'rounded-br-md bg-brand-600 text-white' : 'rounded-bl-md border border-line bg-surface text-ink-900')}>{m.body}</p>
                </div>
              </div>
            )
          })
        )}
      </div>

      <div className="border-t border-line p-4 sm:p-5">
        {disabledReason ? (
          <p className="text-[13px] text-ink-500">{disabledReason}</p>
        ) : (
          <>
            <label htmlFor={`thread-${side}`} className="sr-only">Write a message</label>
            <Textarea
              id={`thread-${side}`} rows={2} value={body} maxLength={2000} placeholder="Write a message. Enter to send, Shift+Enter for a new line."
              aria-invalid={!!error}
              onChange={(e) => { setBody(e.target.value); setError(undefined) }}
              onKeyDown={(e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); submit() } }}
            />
            <div className="mt-2.5 flex items-center justify-between gap-3">
              <span className={cn('text-xs', error ? 'font-semibold text-rose-600' : 'text-ink-400')}>{error ?? `${body.length}/2000`}</span>
              <Button size="sm" icon={<Send className="size-4" />} loading={post.isPending} onClick={submit}>Send</Button>
            </div>
          </>
        )}
      </div>
    </Card>
  )
}
