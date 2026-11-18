import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Check, Hourglass, Pencil, Plus, Trash2 } from 'lucide-react'
import { Alert, Badge, Button, Card, CardHeader, DatePicker, EmptyState, Field, Input, Modal } from '@/components/ui'
import { ConfirmButton, Textarea } from '@/components/ui/extra'
import { todayISO } from '@/components/tracker/shared'
import { useToast } from '@/context/ToastContext'
import { errorMessage, fieldErrors } from '@/lib/api'
import { trackerApi } from '@/lib/endpoints'
import { formatDate } from '@/lib/utils'
import type { ClientDependency } from '@/types/tracker'

const blank = () => ({ title: '', description: '', requested_on: todayISO(), expected_on: '', received_on: '' })

/** What we are waiting on from the client, so client-side delay is visible. */
export function DependencySection({ projectId, items }: { projectId: number; items: ClientDependency[] }) {
  const toast = useToast()
  const queryClient = useQueryClient()
  const [editing, setEditing] = useState<ClientDependency | 'new' | null>(null)
  const [form, setForm] = useState(blank())
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [error, setError] = useState<string | null>(null)

  const refresh = () => queryClient.invalidateQueries({ queryKey: ['tracker'] })
  const set = (key: keyof ReturnType<typeof blank>, value: string) => setForm((f) => ({ ...f, [key]: value }))

  function open(target: ClientDependency | 'new') {
    setErrors({})
    setError(null)
    setForm(target === 'new' ? blank() : { title: target.title, description: target.description ?? '', requested_on: target.requested_on, expected_on: target.expected_on ?? '', received_on: target.received_on ?? '' })
    setEditing(target)
  }

  const save = useMutation({
    mutationFn: () => {
      const body = { ...form, expected_on: form.expected_on || null, received_on: form.received_on || null }
      return editing === 'new' ? trackerApi.addDependency(projectId, body) : trackerApi.updateDependency((editing as ClientDependency).id, body)
    },
    onSuccess: () => {
      toast.success('Saved.')
      setEditing(null)
      refresh()
    },
    onError: (err) => {
      setErrors(fieldErrors(err))
      if (!Object.keys(fieldErrors(err)).length) setError(errorMessage(err))
    },
  })

  const markReceived = useMutation({
    mutationFn: (id: number) => trackerApi.updateDependency(id, { received_on: todayISO() }),
    onSuccess: refresh,
    onError: (err) => toast.error(errorMessage(err)),
  })

  return (
    <Card>
      <CardHeader title="Client dependencies" subtitle="Information, documents or approvals we are waiting for" action={<Button size="sm" icon={<Plus className="size-4" />} onClick={() => open('new')}>Add item</Button>} />
      <div className="p-5 sm:p-6">
        {items.length === 0 ? (
          <EmptyState icon={<Hourglass className="size-6" />} title="Nothing pending from the client" description="Add anything you need from the client so any delay can be attributed correctly." />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[720px] text-left text-[13px]">
              <thead>
                <tr className="border-b border-line text-xs font-bold tracking-wide text-ink-500 uppercase">
                  <th className="py-2 pr-3">Required from client</th><th className="px-3 py-2">Requested</th><th className="px-3 py-2">Expected</th><th className="px-3 py-2">Received</th><th className="px-3 py-2">Days delayed</th><th className="px-3 py-2">Status</th><th />
                </tr>
              </thead>
              <tbody className="divide-y divide-line">
                {items.map((d) => (
                  <tr key={d.id}>
                    <td className="py-3 pr-3"><div className="font-bold text-ink-900">{d.title}</div>{d.description && <div className="text-ink-500">{d.description}</div>}</td>
                    <td className="px-3 py-3">{formatDate(d.requested_on)}</td>
                    <td className="px-3 py-3">{formatDate(d.expected_on)}</td>
                    <td className="px-3 py-3">{formatDate(d.received_on)}</td>
                    <td className={`px-3 py-3 font-bold ${d.days_delayed > 0 ? 'text-rose-600' : 'text-ink-900'}`}>{d.days_delayed}</td>
                    <td className="px-3 py-3"><Badge className={d.is_pending ? 'bg-amber-50 text-amber-700 ring-amber-200' : 'bg-emerald-50 text-emerald-700 ring-emerald-200'}>{d.is_pending ? 'Pending' : 'Received'}</Badge></td>
                    <td className="py-3 pl-3">
                      <div className="flex justify-end">
                        {d.is_pending && <Button size="sm" variant="ghost" aria-label="Mark received" title="Mark received today" onClick={() => markReceived.mutate(d.id)}><Check className="size-4" /></Button>}
                        <Button size="sm" variant="ghost" aria-label="Edit item" onClick={() => open(d)}><Pencil className="size-4" /></Button>
                        <ConfirmButton title="Delete item" message="Remove this dependency?" confirmLabel="Delete" danger onConfirm={async () => { await trackerApi.deleteDependency(d.id); refresh() }}
                          trigger={(o) => <Button size="sm" variant="ghost" aria-label="Delete item" onClick={o}><Trash2 className="size-4" /></Button>} />
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      <Modal open={editing !== null} onClose={() => setEditing(null)} title={editing === 'new' ? 'Add client dependency' : 'Edit client dependency'}
        footer={<><Button variant="secondary" onClick={() => setEditing(null)}>Cancel</Button><Button loading={save.isPending} onClick={() => save.mutate()}>Save</Button></>}>
        <div className="space-y-4">
          {error && <Alert>{error}</Alert>}
          <Field label="What is needed from the client" htmlFor="d-title" error={errors.title}><Input id="d-title" autoFocus value={form.title} invalid={!!errors.title} onChange={(e) => set('title', e.target.value)} placeholder="e.g. Brand logo files" /></Field>
          <Field label="Notes" htmlFor="d-desc"><Textarea id="d-desc" rows={2} value={form.description} onChange={(e) => set('description', e.target.value)} /></Field>
          <div className="grid gap-4 sm:grid-cols-3">
            <Field label="Requested on" htmlFor="d-req" error={errors.requested_on}><DatePicker id="d-req" value={form.requested_on} onChange={(e) => set('requested_on', e.target.value)} /></Field>
            <Field label="Expected by" htmlFor="d-exp" error={errors.expected_on}><DatePicker id="d-exp" value={form.expected_on} onChange={(e) => set('expected_on', e.target.value)} /></Field>
            <Field label="Received on" htmlFor="d-rec" error={errors.received_on}><DatePicker id="d-rec" value={form.received_on} onChange={(e) => set('received_on', e.target.value)} /></Field>
          </div>
        </div>
      </Modal>
    </Card>
  )
}
