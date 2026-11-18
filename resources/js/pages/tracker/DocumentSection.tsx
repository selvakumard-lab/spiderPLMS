import { useRef, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Download, Eye, EyeOff, FileText, Plus, Trash2, Upload } from 'lucide-react'
import { Alert, Badge, Button, Card, CardHeader, EmptyState, Field, Input, Modal } from '@/components/ui'
import { ConfirmButton, Switch, Textarea } from '@/components/ui/extra'
import { useToast } from '@/context/ToastContext'
import { errorMessage, fieldErrors } from '@/lib/api'
import { trackerApi } from '@/lib/endpoints'
import { formatBytes, formatDate, saveBlob } from '@/lib/utils'
import type { ProjectDocument } from '@/types/tracker'

/** Documents for the project. Ones shared with the client appear in their portal and trigger a notification. */
export function DocumentSection({ projectId, documents, hasPortal }: { projectId: number; documents: ProjectDocument[]; hasPortal: boolean }) {
  const toast = useToast()
  const queryClient = useQueryClient()
  const fileInput = useRef<HTMLInputElement>(null)
  const [open, setOpen] = useState(false)
  const [form, setForm] = useState({ title: '', note: '', share: true })
  const [file, setFile] = useState<File | null>(null)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [error, setError] = useState<string | null>(null)

  const refresh = () => queryClient.invalidateQueries({ queryKey: ['tracker'] })

  function show() {
    setForm({ title: '', note: '', share: true })
    setFile(null)
    setErrors({})
    setError(null)
    setOpen(true)
  }

  const upload = useMutation({
    mutationFn: () => {
      const body = new FormData()
      body.append('title', form.title)
      body.append('note', form.note)
      body.append('visible_to_client', form.share ? '1' : '0')
      if (file) body.append('file', file)
      return trackerApi.addDocument(projectId, body)
    },
    onSuccess: () => {
      toast.success(form.share && hasPortal ? 'Document uploaded and the client has been notified.' : 'Document uploaded.')
      setOpen(false)
      refresh()
    },
    onError: (err) => {
      setErrors(fieldErrors(err))
      if (!Object.keys(fieldErrors(err)).length) setError(errorMessage(err))
    },
  })

  async function download(d: ProjectDocument) {
    try {
      saveBlob(await trackerApi.downloadDocument(d.id), d.name)
    } catch (err) {
      toast.error(errorMessage(err, 'Could not download the file.'))
    }
  }

  return (
    <Card>
      <CardHeader title="Documents" subtitle={hasPortal ? 'Shared documents appear in the client portal' : 'Specs, designs and reports for this project'} action={<Button size="sm" icon={<Plus className="size-4" />} onClick={show}>Upload document</Button>} />
      <div className="p-5 sm:p-6">
        {documents.length === 0 ? (
          <EmptyState icon={<FileText className="size-6" />} title="No documents yet" description="Upload specs, designs or reports. Share them with the client to notify them." />
        ) : (
          <ul className="divide-y divide-line">
            {documents.map((d) => (
              <li key={d.id} className="flex items-center gap-3 py-3 first:pt-0 last:pb-0">
                <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-ink-700"><FileText className="size-[18px]" /></span>
                <div className="min-w-0 flex-1">
                  <div className="flex items-center gap-2 text-[14px] font-semibold text-ink-900"><span className="truncate">{d.title}</span>{d.from_client && <Badge className="bg-violet-50 text-violet-700 ring-violet-200">From client</Badge>}
                    {d.visible_to_client ? <Eye className="size-3.5 shrink-0 text-emerald-600" aria-label="Shared with client" /> : <EyeOff className="size-3.5 shrink-0 text-ink-400" aria-label="Internal only" />}
                  </div>
                  <div className="truncate text-[12.5px] text-ink-500">{d.name} · {formatBytes(d.size)} · {formatDate(d.created_at)} · {d.visible_to_client ? 'Shared with client' : 'Internal only'}</div>
                  {d.note && <div className="mt-0.5 text-[13px] text-ink-700">{d.note}</div>}
                </div>
                <Button size="sm" variant="ghost" icon={<Download className="size-4" />} onClick={() => download(d)} aria-label={`Download ${d.title}`} />
                <ConfirmButton danger title="Remove document" message={`Remove "${d.title}"? The client will no longer be able to download it.`} confirmLabel="Remove"
                  onConfirm={async () => { await trackerApi.deleteDocument(d.id); refresh() }}
                  trigger={(o) => <Button size="sm" variant="ghost" icon={<Trash2 className="size-4" />} onClick={o} aria-label={`Remove ${d.title}`} />} />
              </li>
            ))}
          </ul>
        )}
      </div>

      <Modal open={open} onClose={() => setOpen(false)} title="Upload document" description="Up to 20 MB. Executable and script files are not allowed."
        footer={<><Button variant="secondary" onClick={() => setOpen(false)}>Cancel</Button><Button loading={upload.isPending} onClick={() => upload.mutate()}>Upload</Button></>}>
        <div className="space-y-4">
          {error && <Alert>{error}</Alert>}
          <Field label="Title" htmlFor="doc-title" error={errors.title}>
            <Input id="doc-title" autoFocus value={form.title} invalid={!!errors.title} onChange={(e) => setForm({ ...form, title: e.target.value })} placeholder="Homepage mockup v2" />
          </Field>
          <Field label="File" htmlFor="doc-file" error={errors.file}>
            <input ref={fileInput} id="doc-file" type="file" className="hidden" onChange={(e) => setFile(e.target.files?.[0] ?? null)} />
            <Button type="button" variant="secondary" icon={<Upload className="size-4" />} onClick={() => fileInput.current?.click()}>{file ? `${file.name} (${formatBytes(file.size)})` : 'Choose a file'}</Button>
          </Field>
          <Field label="Note for the client (optional)" htmlFor="doc-note" error={errors.note}>
            <Textarea id="doc-note" rows={3} value={form.note} onChange={(e) => setForm({ ...form, note: e.target.value })} />
          </Field>
          <Switch checked={form.share} onChange={(share) => setForm({ ...form, share })} label={hasPortal ? 'Share with the client and send them a notification' : 'Share with the client (they have no portal access yet)'} />
        </div>
      </Modal>
    </Card>
  )
}
