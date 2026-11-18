import { useRef, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Lightbulb, MessageSquarePlus, Upload } from 'lucide-react'
import { Alert, Button, Card, CardHeader, Field, Input, Modal } from '@/components/ui'
import { Textarea } from '@/components/ui/extra'
import { useToast } from '@/context/ToastContext'
import { errorMessage, fieldErrors } from '@/lib/api'
import { portalApi } from '@/lib/endpoints'
import { formatBytes } from '@/lib/utils'

/** The client's actions beside the chat: upload a file and raise a requirement. Each one notifies the team instantly. */
export function PortalActions({ projectId }: { projectId: number }) {
  const toast = useToast()
  const queryClient = useQueryClient()
  const fileInput = useRef<HTMLInputElement>(null)
  const [doc, setDoc] = useState({ title: '', note: '' })
  const [file, setFile] = useState<File | null>(null)
  const [docErrors, setDocErrors] = useState<Record<string, string>>({})
  const [reqOpen, setReqOpen] = useState(false)
  const [req, setReq] = useState({ title: '', description: '' })
  const [reqErrors, setReqErrors] = useState<Record<string, string>>({})

  const refresh = () => queryClient.invalidateQueries({ queryKey: ['portal', 'project'] })

  const upload = useMutation({
    mutationFn: () => {
      const body = new FormData()
      body.append('title', doc.title.trim())
      body.append('note', doc.note)
      if (file) body.append('file', file)
      return portalApi.uploadDocument(projectId, body)
    },
    onSuccess: () => {
      toast.success('Document uploaded. The team has been notified.')
      setDoc({ title: '', note: '' })
      setFile(null)
      if (fileInput.current) fileInput.current.value = ''
      setDocErrors({})
      refresh()
    },
    onError: (err) => {
      const fe = fieldErrors(err)
      setDocErrors(fe)
      if (!Object.keys(fe).length) toast.error(errorMessage(err))
    },
  })

  const raise = useMutation({
    mutationFn: () => portalApi.raiseRequirement(projectId, { title: req.title.trim(), description: req.description }),
    onSuccess: () => {
      toast.success('Requirement sent. The team will review it.')
      setReq({ title: '', description: '' })
      setReqOpen(false)
      refresh()
    },
    onError: (err) => {
      const fe = fieldErrors(err)
      setReqErrors(fe)
      if (!Object.keys(fe).length) toast.error(errorMessage(err))
    },
  })

  function submitUpload() {
    const errors: Record<string, string> = {}
    if (!doc.title.trim()) errors.title = 'Give the document a title.'
    if (!file) errors.file = 'Choose a file to upload.'
    else if (file.size > 20 * 1024 * 1024) errors.file = 'That file is larger than 20 MB.'
    setDocErrors(errors)
    if (!Object.keys(errors).length) upload.mutate()
  }

  return (
    <div className="grid gap-6 lg:grid-cols-2">
      <Card>
        <CardHeader title="Send a document" subtitle="Anything the team needs from you. Up to 20 MB." />
        <div className="space-y-4 p-5 sm:p-6">
          <Field label="Title" htmlFor="pd-title" error={docErrors.title}>
            <Input id="pd-title" value={doc.title} invalid={!!docErrors.title} onChange={(e) => setDoc({ ...doc, title: e.target.value })} placeholder="Brand guidelines" />
          </Field>
          <Field label="File" htmlFor="pd-file" error={docErrors.file}>
            <input ref={fileInput} id="pd-file" type="file" className="hidden" onChange={(e) => setFile(e.target.files?.[0] ?? null)} />
            <Button type="button" variant="secondary" icon={<Upload className="size-4" />} onClick={() => fileInput.current?.click()}>{file ? `${file.name} (${formatBytes(file.size)})` : 'Choose a file'}</Button>
          </Field>
          <Field label="Note (optional)" htmlFor="pd-note" error={docErrors.note}>
            <Textarea id="pd-note" rows={2} value={doc.note} onChange={(e) => setDoc({ ...doc, note: e.target.value })} />
          </Field>
          <Button icon={<Upload className="size-4" />} loading={upload.isPending} onClick={submitUpload}>Upload</Button>
        </div>
      </Card>

      <Card>
        <CardHeader title="Raise a requirement" subtitle="Need something added or changed? Tell the team" />
        <div className="space-y-4 p-5 sm:p-6">
          <p className="text-[13.5px] text-ink-500">The team will review it and tell you the effect on the delivery date. For quick questions, use the messages above.</p>
          <Button icon={<Lightbulb className="size-4" />} onClick={() => { setReqErrors({}); setReqOpen(true) }}>Raise a requirement</Button>
        </div>
      </Card>

      <Modal open={reqOpen} onClose={() => setReqOpen(false)} title="Raise a new requirement" description="Tell the team about a change or addition. They will review it and let you know the effect on the delivery date."
        footer={<><Button variant="secondary" onClick={() => setReqOpen(false)}>Cancel</Button>
          <Button icon={<MessageSquarePlus className="size-4" />} loading={raise.isPending} onClick={() => (req.title.trim() ? raise.mutate() : setReqErrors({ title: 'Give the requirement a short title.' }))}>Send requirement</Button></>}>
        <div className="space-y-4">
          {reqErrors.general && <Alert>{reqErrors.general}</Alert>}
          <Field label="Title" htmlFor="rq-title" error={reqErrors.title}>
            <Input id="rq-title" autoFocus value={req.title} invalid={!!reqErrors.title} onChange={(e) => setReq({ ...req, title: e.target.value })} placeholder="Add a dark mode" />
          </Field>
          <Field label="Details" htmlFor="rq-desc" error={reqErrors.description}>
            <Textarea id="rq-desc" rows={4} value={req.description} onChange={(e) => setReq({ ...req, description: e.target.value })} />
          </Field>
        </div>
      </Modal>
    </div>
  )
}
