import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link } from 'react-router'
import { Bot, MessageCircle, Save } from 'lucide-react'
import { Alert, Badge, Button, Card, CardHeader, Field, Input, PageHeader, Select, Skeleton } from '@/components/ui'
import { Switch, Textarea } from '@/components/ui/extra'
import { useAuth } from '@/context/AuthContext'
import { useToast } from '@/context/ToastContext'
import { errorMessage } from '@/lib/api'
import { workspaceApi } from '@/lib/endpoints'
import type { WorkspaceSettings } from '@/types/tracker'

const MAX_KNOWLEDGE = 8000

const STARTER = `About us
We are [company name], a software delivery team that builds [what you build] for clients in [industries].

How we communicate
- Be polite, clear and short. Use the client's first name.
- Always mention the project name and what we need from them.
- Never promise a delivery date that is not in the project data.

Our process
- Client inputs (content, logos, access, approvals) are needed before dependent work can start. Delays on their side move the delivery date.
- Extra requirements are handled as change requests and may change the timeline and cost.
- We work Monday to Friday, 10:00 to 18:00 IST.

Escalation
For urgent matters the client can reach [name] at [phone or email].`

type Form = WorkspaceSettings['data']

/** Workspace settings. The knowledge base text is read by DeepSeek whenever it writes a client message or evaluates a chat. */
const WA_STATE: Record<string, string> = {
  open: 'Connected',
  connecting: 'Waiting for the phone',
  close: 'Phone disconnected',
  unconfigured: 'Not set up',
  unreachable: 'Server unreachable',
}

export default function WorkspaceSettingsPage() {
  const { tenant } = useAuth()
  const toast = useToast()
  const queryClient = useQueryClient()
  const { data, isLoading } = useQuery({ queryKey: ['workspace-settings'], queryFn: workspaceApi.settings })
  const [form, setForm] = useState<Form | null>(null)

  useEffect(() => {
    if (data) setForm(data.data)
  }, [data])

  const save = useMutation({
    mutationFn: () => workspaceApi.updateSettings(form!),
    onSuccess: (saved) => {
      queryClient.setQueryData(['workspace-settings'], saved)
      toast.success('Settings saved.')
    },
    onError: (err) => toast.error(errorMessage(err)),
  })

  if (isLoading || !data || !form) return <div className="space-y-4"><Skeleton className="h-24" /><Skeleton className="h-80" /></div>

  const readOnly = !data.can_edit
  const set = <K extends keyof Form>(key: K, value: Form[K]) => setForm((f) => (f ? { ...f, [key]: value } : f))
  const dirty = JSON.stringify(form) !== JSON.stringify(data.data)

  return (
    <>
      <PageHeader eyebrow={tenant?.name} title="Settings" description="Teach the AI about your company and choose how client messages are written and prepared."
        actions={!readOnly && <Button icon={<Save className="size-4" />} loading={save.isPending} disabled={!dirty} onClick={() => save.mutate()}>Save changes</Button>} />

      {readOnly && <div className="mb-5"><Alert tone="info">Only an organization admin can change these settings.</Alert></div>}

      <div className="grid gap-6 xl:grid-cols-3">
        <div className="space-y-6 xl:col-span-2">
          <Card>
            <CardHeader title="Knowledge base" subtitle="Plain text DeepSeek reads before it writes any client email or WhatsApp message" />
            <div className="space-y-3 p-5 sm:p-6">
              <Textarea rows={16} maxLength={MAX_KNOWLEDGE} disabled={readOnly} value={form.knowledge_base} onChange={(e) => set('knowledge_base', e.target.value)}
                aria-label="Knowledge base" placeholder="Describe your company, how you talk to clients, your process and anything the AI must never promise." />
              <div className="flex flex-wrap items-center justify-between gap-3 text-[12.5px] text-ink-500">
                <span className="tabular">{form.knowledge_base.length} / {MAX_KNOWLEDGE} characters</span>
                {!readOnly && !form.knowledge_base.trim() && <Button size="sm" variant="secondary" onClick={() => set('knowledge_base', STARTER)}>Insert a starter template</Button>}
              </div>
              <ul className="list-disc space-y-1 pl-5 text-[13px] text-ink-500">
                <li>Who you are and what you build.</li>
                <li>How you want to sound: greeting, length, language.</li>
                <li>Your process: client inputs, change requests, working hours.</li>
                <li>What the AI must never promise, and who to contact for urgent matters.</li>
              </ul>
            </div>
          </Card>

          <Card>
            <CardHeader title="Message style" subtitle="Applies to AI-written emails and WhatsApp messages" />
            <div className="grid gap-4 p-5 sm:grid-cols-2 sm:p-6">
              <Field label="Tone" htmlFor="s-tone">
                <Select id="s-tone" disabled={readOnly} value={form.tone} onChange={(e) => set('tone', e.target.value as Form['tone'])}>
                  <option value="professional">Professional</option>
                  <option value="friendly">Friendly</option>
                  <option value="formal">Formal</option>
                </Select>
              </Field>
              <Field label="Sender name" htmlFor="s-sender" hint="Shown at the end of every email">
                <Input id="s-sender" disabled={readOnly} value={form.sender_name} onChange={(e) => set('sender_name', e.target.value)} />
              </Field>
              <div className="sm:col-span-2">
                <Field label="Email signature" htmlFor="s-signature" hint="For example your role, phone number and website">
                  <Textarea id="s-signature" rows={3} maxLength={500} disabled={readOnly} value={form.email_signature} onChange={(e) => set('email_signature', e.target.value)} />
                </Field>
              </div>
            </div>
          </Card>

          <Card>
            <CardHeader title="Automation" subtitle="Drafts only. A person always reviews before anything reaches a client." />
            <div className="p-5 sm:p-6">
              <Switch checked={form.auto_draft} disabled={readOnly} onChange={(v) => set('auto_draft', v)} label="Prepare reminder drafts every morning" />
              <p className="mt-2 text-[13px] text-ink-500">Each morning at 08:00 the system writes a draft when a client input is overdue or a project is delayed. Find them under Reminders & Alerts.</p>
              <div className="mt-5 border-t border-line pt-5">
                <Switch checked={form.email_updates} disabled={readOnly} onChange={(v) => set('email_updates', v)} label="Prepare an email draft when a project changes" />
                <p className="mt-2 text-[13px] text-ink-500">When a step, status, document or request reaches the client's portal, a matching email draft is written for you to review. Several changes are collected in one draft until you send it.</p>
              </div>
            </div>
          </Card>
        </div>

        <div className="space-y-6">
          <Card>
            <CardHeader title="AI (DeepSeek)" />
            <div className="space-y-2 p-5 sm:p-6">
              <div className="flex items-center gap-2.5"><span className="flex size-9 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><Bot className="size-4" /></span>
                <Badge className={data.ai_configured ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-amber-50 text-amber-700 ring-amber-200'}>{data.ai_configured ? 'Connected' : 'Not connected'}</Badge></div>
              <p className="text-[13px] text-ink-500">{data.ai_configured ? 'Messages and chat evaluations are written by DeepSeek.' : 'Without a DeepSeek key, drafts use simple built-in wording. Ask the platform admin to add the key.'}</p>
            </div>
          </Card>

          <Card>
            <CardHeader title="WhatsApp" />
            <div className="space-y-3 p-5 sm:p-6">
              <div className="flex items-center gap-2.5"><span className="flex size-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"><MessageCircle className="size-4" /></span>
                <Badge className={data.whatsapp.state === 'open' ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-amber-50 text-amber-700 ring-amber-200'}>{data.whatsapp.allowed ? (WA_STATE[data.whatsapp.state] ?? 'Not connected') : 'Not in your plan'}</Badge></div>
              <p className="text-[13px] text-ink-500">{data.whatsapp.allowed ? 'Your workspace has its own WhatsApp number. Replies from clients are matched to a client by their WhatsApp number, and DeepSeek then evaluates the chat on the project.' : 'WhatsApp chat is part of the Enterprise plan. Upgrade to link your own number and chat with clients from here.'}</p>
              {data.whatsapp.allowed && <Link to="/app/whatsapp" className="inline-block text-[13px] font-semibold text-brand-700 hover:underline">Open the WhatsApp inbox</Link>}
            </div>
          </Card>
        </div>
      </div>
    </>
  )
}
