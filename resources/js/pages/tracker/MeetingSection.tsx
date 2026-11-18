import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { ExternalLink, Paperclip, Pencil, Plus, Trash2, Users, X } from 'lucide-react'
import { Alert, Button, Card, CardHeader, DatePicker, EmptyState, Field, Input, Modal } from '@/components/ui'
import { ConfirmButton, Textarea } from '@/components/ui/extra'
import { todayISO } from '@/components/tracker/shared'
import { useToast } from '@/context/ToastContext'
import { errorMessage, fieldErrors } from '@/lib/api'
import { trackerApi } from '@/lib/endpoints'
import { formatBytes, formatDate, saveBlob } from '@/lib/utils'
import type { Meeting } from '@/types/tracker'

const blank = () => ({ meeting_date: todayISO(), title: '', details: '', discussion_points: '', links: '' })

/** Client meetings with notes, minutes, files, images and links. */
export function MeetingSection({ projectId, meetings }: { projectId: number; meetings: Meeting[] }) {
  const toast = useToast()
  const queryClient = useQueryClient()
  const [editing, setEditing] = useState<Meeting | 'new' | null>(null)
  const [form, setForm] = useState(blank())
  const [files, setFiles] = useState<File[]>([])
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [error, setError] = useState<string | null>(null)

  const refresh = () => queryClient.invalidateQueries({ queryKey: ['tracker'] })
  const set = (key: keyof ReturnType<typeof blank>, value: string) => setForm((f) => ({ ...f, [key]: value }))

  function open(target: Meeting | 'new') {
    setErrors({})
    setError(null)
    setFiles([])
    setForm(target === 'new' ? blank() : { meeting_date: target.meeting_date, title: target.title, details: target.details ?? '', discussion_points: target.discussion_points ?? '', links: target.links.join('\n') })
    setEditing(target)
  }

  const save = useMutation({
    mutationFn: () => {
      const body = new FormData()
      body.append('meeting_date', form.meeting_date)
      body.append('title', form.title)
      body.append('details', form.details)
      body.append('discussion_points', form.discussion_points)
      form.links.split('\n').map((l) => l.trim()).filter(Boolean).forEach((l) => body.append('links[]', l))
      files.forEach((f) => body.append('files[]', f))
      return editing === 'new' ? trackerApi.addMeeting(projectId, body) : trackerApi.updateMeeting((editing as Meeting).id, body)
    },
    onSuccess: () => {
      toast.success('Meeting saved.')
      setEditing(null)
      refresh()
    },
    onError: (err) => {
      setErrors(fieldErrors(err))
      if (!Object.keys(fieldErrors(err)).length) setError(errorMessage(err))
    },
  })

  async function download(id: number, name: string) {
    try {
      saveBlob(await trackerApi.downloadAttachment(id), name)
    } catch (err) {
      toast.error(errorMessage(err, 'Could not download the file.'))
    }
  }

  return (
    <Card>
      <CardHeader title="Client meetings" subtitle="Dates, discussion points, minutes and files" action={<Button size="sm" icon={<Plus className="size-4" />} onClick={() => open('new')}>Add meeting</Button>} />
      <div className="p-5 sm:p-6">
        {meetings.length === 0 ? (
          <EmptyState icon={<Users className="size-6" />} title="No meetings recorded" description="Record a client meeting and attach the minutes." />
        ) : (
          <ul className="space-y-4">
            {meetings.map((m) => (
              <li key={m.id} className="rounded-xl border border-line p-4">
                <div className="flex items-start justify-between gap-2">
                  <div>
                    <div className="text-xs font-semibold text-ink-500">{formatDate(m.meeting_date)}</div>
                    <h4 className="text-[15px] font-bold text-ink-900">{m.title}</h4>
                  </div>
                  <div className="flex items-center">
                    <Button size="sm" variant="ghost" aria-label="Edit meeting" onClick={() => open(m)}><Pencil className="size-4" /></Button>
                    <ConfirmButton title="Delete meeting" message="This also deletes its attachments." confirmLabel="Delete" danger onConfirm={async () => { await trackerApi.deleteMeeting(m.id); refresh() }}
                      trigger={(o) => <Button size="sm" variant="ghost" aria-label="Delete meeting" onClick={o}><Trash2 className="size-4" /></Button>} />
                  </div>
                </div>
                {m.details && <p className="mt-2 text-[13.5px] whitespace-pre-line text-ink-700">{m.details}</p>}
                {m.discussion_points && (
                  <div className="mt-3"><div className="text-xs font-bold tracking-wide text-ink-500 uppercase">Discussion points</div><p className="mt-1 text-[13.5px] whitespace-pre-line text-ink-700">{m.discussion_points}</p></div>
                )}
                {(m.attachments.length > 0 || m.links.length > 0) && (
                  <div className="mt-3 flex flex-wrap gap-2">
                    {m.attachments.map((f) => (
                      <span key={f.id} className="inline-flex items-center gap-1 rounded-lg bg-slate-100 py-1 pr-1 pl-2.5 text-xs font-semibold text-ink-700">
                        <button type="button" className="inline-flex items-center gap-1 hover:text-brand-700" onClick={() => download(f.id, f.name)}>
                          <Paperclip className="size-3.5" /> {f.name} <span className="font-medium text-ink-400">({formatBytes(f.size)})</span>
                        </button>
                        <ConfirmButton title="Remove attachment" message={`Remove ${f.name}?`} confirmLabel="Remove" danger onConfirm={async () => { await trackerApi.deleteAttachment(f.id); refresh() }}
                          trigger={(o) => <button type="button" aria-label={`Remove ${f.name}`} className="rounded p-1 text-ink-400 hover:text-rose-600" onClick={o}><X className="size-3.5" /></button>} />
                      </span>
                    ))}
                    {m.links.map((l) => (
                      <a key={l} href={l} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-1 rounded-lg bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-700 hover:bg-brand-100">
                        <ExternalLink className="size-3.5" /> {l.replace(/^https?:\/\//, '').slice(0, 40)}
                      </a>
                    ))}
                  </div>
                )}
              </li>
            ))}
          </ul>
        )}
      </div>

      <Modal open={editing !== null} onClose={() => setEditing(null)} size="lg" title={editing === 'new' ? 'Add client meeting' : 'Edit meeting'}
        footer={<><Button variant="secondary" onClick={() => setEditing(null)}>Cancel</Button><Button loading={save.isPending} onClick={() => save.mutate()}>Save meeting</Button></>}>
        <div className="space-y-4">
          {error && <Alert>{error}</Alert>}
          <div className="grid gap-4 sm:grid-cols-[180px_1fr]">
            <Field label="Date" htmlFor="m-date" error={errors.meeting_date}><DatePicker id="m-date" value={form.meeting_date} onChange={(e) => set('meeting_date', e.target.value)} /></Field>
            <Field label="Title" htmlFor="m-title" error={errors.title}><Input id="m-title" autoFocus value={form.title} invalid={!!errors.title} onChange={(e) => set('title', e.target.value)} placeholder="e.g. Kick-off call" /></Field>
          </div>
          <Field label="Meeting details" htmlFor="m-details"><Textarea id="m-details" rows={3} value={form.details} onChange={(e) => set('details', e.target.value)} /></Field>
          <Field label="Discussion points" htmlFor="m-points"><Textarea id="m-points" rows={3} value={form.discussion_points} onChange={(e) => set('discussion_points', e.target.value)} /></Field>
          <Field label="Links" htmlFor="m-links" hint="One URL per line" error={errors.links ?? errors['links.0']}><Textarea id="m-links" rows={2} value={form.links} onChange={(e) => set('links', e.target.value)} placeholder="https://" /></Field>
          <Field label="Minutes, documents and images" htmlFor="m-files" hint="Up to 10 files, 20 MB each" error={errors.files ?? errors['files.0']}>
            <input id="m-files" type="file" multiple onChange={(e) => setFiles(Array.from(e.target.files ?? []))} className="block w-full text-[13px] text-ink-700 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-[13px] file:font-semibold file:text-brand-700" />
          </Field>
        </div>
      </Modal>
    </Card>
  )
}
