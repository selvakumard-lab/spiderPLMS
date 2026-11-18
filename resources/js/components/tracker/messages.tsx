import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Mail, MessageCircle, Sparkles } from 'lucide-react'
import { Alert, Badge, Button, Field, Input, Modal, Select } from '@/components/ui'
import { Textarea } from '@/components/ui/extra'
import { useToast } from '@/context/ToastContext'
import { errorMessage } from '@/lib/api'
import { messagesApi, trackerApi } from '@/lib/endpoints'
import type { ClientMessage, MessageChannel, MessageReason, MessageStatus } from '@/types/tracker'

export const REASON_LABELS: Record<MessageReason, string> = {
  dependency_overdue: 'Overdue client inputs',
  status_alert: 'Delay alert',
  progress_update: 'Progress update',
  project_update: 'Project change',
  custom: 'Custom message',
}

const STATUS_BADGE: Record<MessageStatus, string> = {
  draft: 'bg-amber-50 text-amber-700 ring-amber-200',
  sent: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
  failed: 'bg-rose-50 text-rose-700 ring-rose-200',
  received: 'bg-slate-100 text-slate-600 ring-slate-200',
}

export function MessageStatusBadge({ status }: { status: MessageStatus }) {
  return <Badge className={`capitalize ${STATUS_BADGE[status]}`}>{status}</Badge>
}

export function ChannelIcon({ channel, className = 'size-4' }: { channel: MessageChannel; className?: string }) {
  return channel === 'email' ? <Mail className={className} /> : <MessageCircle className={className} />
}

/** Ask DeepSeek to write a draft. The draft is never sent from here: a person reviews it first. */
export function DraftModal({ open, onClose, projectId, onCreated }: { open: boolean; onClose: () => void; projectId?: number; onCreated: (m: ClientMessage) => void }) {
  const toast = useToast()
  const queryClient = useQueryClient()
  const [project, setProject] = useState(projectId ? String(projectId) : '')
  const [channel, setChannel] = useState<MessageChannel>('email')
  const [reason, setReason] = useState<MessageReason>('progress_update')
  const [instruction, setInstruction] = useState('')
  const { data: projects } = useQuery({ queryKey: ['tracker', 'projects', '', ''], queryFn: () => trackerApi.projects({}), enabled: open && !projectId })

  useEffect(() => {
    if (open) {
      setProject(projectId ? String(projectId) : '')
      setInstruction('')
    }
  }, [open, projectId])

  const create = useMutation({
    mutationFn: () => messagesApi.draft(Number(project), { channel, reason, instruction: instruction || undefined }),
    onSuccess: (message) => {
      queryClient.invalidateQueries({ queryKey: ['messages'] })
      toast.success(message.ai_generated ? 'Draft written by AI. Review it before sending.' : 'Draft created. Review it before sending.')
      onCreated(message)
      onClose()
    },
    onError: (err) => toast.error(errorMessage(err)),
  })

  return (
    <Modal open={open} onClose={onClose} title="Draft a client message" description="DeepSeek writes it from the project's real status and your knowledge base. You review it before anything is sent."
      footer={<><Button variant="secondary" onClick={onClose}>Cancel</Button><Button icon={<Sparkles className="size-4" />} loading={create.isPending} disabled={!project} onClick={() => create.mutate()}>Write draft</Button></>}>
      <div className="space-y-4">
        {!projectId && (
          <Field label="Project" htmlFor="d-project">
            <Select id="d-project" value={project} onChange={(e) => setProject(e.target.value)}>
              <option value="">Choose a project</option>
              {(projects ?? []).map((p) => <option key={p.id} value={p.id}>{p.name}{p.client_name ? ` (${p.client_name})` : ''}</option>)}
            </Select>
          </Field>
        )}
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Channel" htmlFor="d-channel">
            <Select id="d-channel" value={channel} onChange={(e) => setChannel(e.target.value as MessageChannel)}>
              <option value="email">Email</option>
              <option value="whatsapp">WhatsApp</option>
            </Select>
          </Field>
          <Field label="What is it about?" htmlFor="d-reason">
            <Select id="d-reason" value={reason} onChange={(e) => setReason(e.target.value as MessageReason)}>
              {(Object.keys(REASON_LABELS) as MessageReason[]).map((r) => <option key={r} value={r}>{REASON_LABELS[r]}</option>)}
            </Select>
          </Field>
        </div>
        <Field label="Anything specific to mention? (optional)" htmlFor="d-instruction">
          <Textarea id="d-instruction" rows={3} value={instruction} onChange={(e) => setInstruction(e.target.value)} placeholder="For example: ask them to confirm the logo files by Friday." />
        </Field>
      </div>
    </Modal>
  )
}

/** Review and edit a draft, then send it. */
export function MessageEditorModal({ message, onClose }: { message: ClientMessage | null; onClose: () => void }) {
  const toast = useToast()
  const queryClient = useQueryClient()
  const [subject, setSubject] = useState('')
  const [body, setBody] = useState('')
  const [to, setTo] = useState('')
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!message) return
    setSubject(message.subject ?? '')
    setBody(message.body)
    setTo(message.to_address ?? '')
    setError(message.status === 'failed' ? message.error : null)
  }, [message])

  const refresh = () => queryClient.invalidateQueries({ queryKey: ['messages'] })
  const payload = () => ({ subject: message?.channel === 'email' ? subject : null, body, to_address: to || null })

  const save = useMutation({
    mutationFn: () => messagesApi.update(message!.id, payload()),
    onSuccess: () => {
      refresh()
      toast.success('Draft saved.')
      onClose()
    },
    onError: (err) => setError(errorMessage(err)),
  })

  const send = useMutation({
    mutationFn: async () => {
      await messagesApi.update(message!.id, payload())
      return messagesApi.send(message!.id)
    },
    onSuccess: () => {
      refresh()
      toast.success('Message sent.')
      onClose()
    },
    onError: (err) => {
      refresh()
      setError(errorMessage(err))
    },
  })

  if (!message) return null
  const isEmail = message.channel === 'email'
  const locked = message.status === 'sent'

  return (
    <Modal open onClose={onClose} size="lg" title={`${isEmail ? 'Email' : 'WhatsApp message'} to ${message.client_name ?? 'client'}`}
      description={message.project_name ? `${REASON_LABELS[message.reason ?? 'custom']} · ${message.project_name}` : undefined}
      footer={<>
        <Button variant="secondary" onClick={onClose}>Close</Button>
        {!locked && <Button variant="secondary" loading={save.isPending} onClick={() => save.mutate()}>Save draft</Button>}
        {!locked && <Button loading={send.isPending} disabled={!body.trim() || !to.trim()} onClick={() => send.mutate()}>Send now</Button>}
      </>}>
      <div className="space-y-4">
        {message.ai_generated && !locked && <Alert tone="info">Written by DeepSeek from the project data. Please check the facts before sending.</Alert>}
        {error && <Alert>{error}</Alert>}
        <Field label={isEmail ? 'To (email)' : 'To (WhatsApp number)'} htmlFor="m-to">
          <Input id="m-to" readOnly={locked} value={to} onChange={(e) => setTo(e.target.value)} placeholder={isEmail ? 'client@company.com' : '919876543210'} />
        </Field>
        {isEmail && (
          <Field label="Subject" htmlFor="m-subject">
            <Input id="m-subject" readOnly={locked} value={subject} onChange={(e) => setSubject(e.target.value)} />
          </Field>
        )}
        <Field label="Message" htmlFor="m-body">
          <Textarea id="m-body" readOnly={locked} rows={isEmail ? 10 : 6} value={body} onChange={(e) => setBody(e.target.value)} />
        </Field>
      </div>
    </Modal>
  )
}
