import { useEffect, useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { KeyRound, Mail, MessageCircle, Pencil, Phone, Plus, Search, Trash2, Users } from 'lucide-react'
import { Alert, Badge, Button, Card, EmptyState, Field, Input, Modal, PageHeader, Skeleton } from '@/components/ui'
import { ConfirmButton, Textarea } from '@/components/ui/extra'
import { useAuth } from '@/context/AuthContext'
import { useToast } from '@/context/ToastContext'
import { errorMessage, fieldErrors } from '@/lib/api'
import { clientsApi } from '@/lib/endpoints'
import type { Client } from '@/types/tracker'

const blank = { name: '', contact_person: '', email: '', phone: '', whatsapp_number: '', notes: '' }

/** Client master data: who you deliver to and how to reach them. Projects pick a client from this list. */
export default function ClientsPage() {
  const { tenant } = useAuth()
  const toast = useToast()
  const queryClient = useQueryClient()
  const [search, setSearch] = useState('')
  const [editing, setEditing] = useState<Client | 'new' | null>(null)
  const { data, isLoading } = useQuery({ queryKey: ['clients', search], queryFn: () => clientsApi.list(search) })

  const remove = useMutation({
    mutationFn: (id: number) => clientsApi.remove(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['clients'] })
      toast.success('Client deleted.')
    },
    onError: (err) => toast.error(errorMessage(err)),
  })

  const portal = useMutation({
    mutationFn: ({ client, grant }: { client: Client; grant: boolean }) => (grant ? clientsApi.grantPortalAccess(client.id) : clientsApi.revokePortalAccess(client.id)),
    onSuccess: (client, { grant }) => {
      queryClient.invalidateQueries({ queryKey: ['clients'] })
      toast.success(grant ? `${client.name} can now sign in at /${tenant?.slug} with ${client.email}.` : 'Portal access removed.')
    },
    onError: (err) => toast.error(fieldErrors(err).email ?? errorMessage(err)),
  })

  return (
    <>
      <PageHeader eyebrow={tenant?.name} title="Clients" description="The people and companies you deliver to. Add an email and WhatsApp number to send reminders."
        actions={<Button icon={<Plus className="size-4" />} onClick={() => setEditing('new')}>Add client</Button>} />

      <Card>
        <div className="border-b border-line p-4 sm:p-5">
          <div className="relative max-w-md">
            <Search className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-ink-400" />
            <Input className="pl-10" placeholder="Search clients" value={search} onChange={(e) => setSearch(e.target.value)} aria-label="Search clients" />
          </div>
        </div>
        <div className="p-4 sm:p-5">
          {isLoading ? (
            <div className="space-y-3">{[0, 1, 2].map((i) => <Skeleton key={i} className="h-16" />)}</div>
          ) : !data?.length ? (
            <EmptyState icon={<Users className="size-6" />} title={search ? 'No matching clients' : 'No clients yet'} description={search ? 'Try a different search.' : 'Add your first client, then choose it when you onboard a project.'}
              action={!search ? <Button onClick={() => setEditing('new')}>Add client</Button> : undefined} />
          ) : (
            <ul className="divide-y divide-line">
              {data.map((c) => (
                <li key={c.id} className="flex flex-col gap-3 py-4 first:pt-0 last:pb-0 md:flex-row md:items-center md:gap-6">
                  <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2 text-[15px] font-bold text-ink-900">{c.name}
                      {c.has_portal_access && <Badge className="bg-emerald-50 text-emerald-700 ring-emerald-200"><KeyRound className="size-3" /> Portal access</Badge>}
                    </div>
                    <div className="mt-0.5 text-[13px] text-ink-500">{c.contact_person || 'No contact person'} · {c.projects_count ?? 0} project{c.projects_count === 1 ? '' : 's'}</div>
                  </div>
                  <div className="space-y-1 text-[13px] text-ink-500 md:w-72">
                    <div className="flex items-center gap-2"><Mail className="size-3.5 shrink-0" /><span className="truncate">{c.email || '–'}</span></div>
                    <div className="flex items-center gap-2"><MessageCircle className="size-3.5 shrink-0" />{c.whatsapp_number ? `+${c.whatsapp_number}` : '–'}</div>
                    {c.phone && <div className="flex items-center gap-2"><Phone className="size-3.5 shrink-0" />{c.phone}</div>}
                  </div>
                  <div className="flex flex-wrap gap-2">
                    {c.has_portal_access ? (
                      <ConfirmButton danger title="Remove portal access" message={`${c.name} will no longer be able to sign in to see their projects.`} confirmLabel="Remove access" onConfirm={() => portal.mutateAsync({ client: c, grant: false })}
                        trigger={(open) => <Button size="sm" variant="secondary" icon={<KeyRound className="size-3.5" />} onClick={open}>Remove access</Button>} />
                    ) : (
                      <Button size="sm" variant="secondary" icon={<KeyRound className="size-3.5" />} loading={portal.isPending && portal.variables?.client.id === c.id} onClick={() => portal.mutate({ client: c, grant: true })}>Give portal access</Button>
                    )}
                    <Button size="sm" variant="secondary" icon={<Pencil className="size-3.5" />} onClick={() => setEditing(c)}>Edit</Button>
                    <ConfirmButton danger title="Delete client" message={`Delete ${c.name}? Their projects stay, but are no longer linked to a client.`} confirmLabel="Delete" onConfirm={() => remove.mutateAsync(c.id)}
                      trigger={(open) => <Button size="sm" variant="ghost" icon={<Trash2 className="size-3.5" />} onClick={open} aria-label={`Delete ${c.name}`} />} />
                  </div>
                </li>
              ))}
            </ul>
          )}
        </div>
      </Card>

      <ClientFormModal client={editing} onClose={() => setEditing(null)} />
    </>
  )
}

function ClientFormModal({ client, onClose }: { client: Client | 'new' | null; onClose: () => void }) {
  const toast = useToast()
  const queryClient = useQueryClient()
  const [form, setForm] = useState(blank)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [error, setError] = useState<string | null>(null)
  const existing = client && client !== 'new' ? client : null

  useEffect(() => {
    if (!client) return
    setErrors({})
    setError(null)
    setForm(existing ? {
      name: existing.name, contact_person: existing.contact_person ?? '', email: existing.email ?? '', phone: existing.phone ?? '',
      whatsapp_number: existing.whatsapp_number ? `+${existing.whatsapp_number}` : '', notes: existing.notes ?? '',
    } : blank)
  }, [client, existing])

  const set = (key: keyof typeof blank, value: string) => setForm((f) => ({ ...f, [key]: value }))

  const save = useMutation({
    mutationFn: () => (existing ? clientsApi.update(existing.id, form) : clientsApi.create(form)),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['clients'] })
      toast.success(existing ? 'Client updated.' : 'Client added.')
      onClose()
    },
    onError: (err) => {
      setErrors(fieldErrors(err))
      if (!Object.keys(fieldErrors(err)).length) setError(errorMessage(err))
    },
  })

  function submit(e: FormEvent) {
    e.preventDefault()
    save.mutate()
  }

  return (
    <Modal open={!!client} onClose={onClose} size="lg" title={existing ? 'Edit client' : 'Add client'} description="Contact details are used only to send the reminders you approve."
      footer={<><Button variant="secondary" onClick={onClose}>Cancel</Button><Button loading={save.isPending} onClick={() => save.mutate()}>{existing ? 'Save changes' : 'Add client'}</Button></>}>
      <form onSubmit={submit} className="space-y-4" noValidate>
        {error && <Alert>{error}</Alert>}
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Company or client name" htmlFor="c-name" error={errors.name}>
            <Input id="c-name" autoFocus value={form.name} invalid={!!errors.name} onChange={(e) => set('name', e.target.value)} />
          </Field>
          <Field label="Contact person" htmlFor="c-person" error={errors.contact_person}>
            <Input id="c-person" value={form.contact_person} onChange={(e) => set('contact_person', e.target.value)} />
          </Field>
          <Field label="Email" htmlFor="c-email" error={errors.email}>
            <Input id="c-email" type="email" value={form.email} invalid={!!errors.email} onChange={(e) => set('email', e.target.value)} placeholder="name@client.com" />
          </Field>
          <Field label="WhatsApp number" htmlFor="c-wa" error={errors.whatsapp_number} hint="With country code, for example +91 98765 43210">
            <Input id="c-wa" type="tel" value={form.whatsapp_number} invalid={!!errors.whatsapp_number} onChange={(e) => set('whatsapp_number', e.target.value)} />
          </Field>
          <Field label="Phone" htmlFor="c-phone" error={errors.phone}>
            <Input id="c-phone" type="tel" value={form.phone} onChange={(e) => set('phone', e.target.value)} />
          </Field>
        </div>
        <Field label="Notes" htmlFor="c-notes" error={errors.notes}>
          <Textarea id="c-notes" rows={3} value={form.notes} onChange={(e) => set('notes', e.target.value)} />
        </Field>
      </form>
    </Modal>
  )
}
