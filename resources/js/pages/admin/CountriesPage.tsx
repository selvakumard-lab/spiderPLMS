import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Globe, Plus, Search, Trash2 } from 'lucide-react'
import { useToast } from '@/context/ToastContext'
import { errorMessage, fieldErrors } from '@/lib/api'
import { adminApi } from '@/lib/endpoints'
import { Alert, Badge, Button, Card, CountryChip, EmptyState, Field, Input, Modal, PageHeader, Select, Skeleton } from '@/components/ui'
import { ConfirmButton, Switch } from '@/components/ui/extra'

export default function CountriesPage() {
  const toast = useToast()
  const queryClient = useQueryClient()
  const [search, setSearch] = useState('')
  const [adding, setAdding] = useState(false)
  const { data, isLoading, error } = useQuery({ queryKey: ['admin', 'countries'], queryFn: adminApi.countries })
  const { data: currencies } = useQuery({ queryKey: ['admin', 'currencies'], queryFn: adminApi.currencies })

  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ['admin'] })
    queryClient.invalidateQueries({ queryKey: ['public-config'] })
    queryClient.invalidateQueries({ queryKey: ['pricing'] })
  }
  const update = useMutation({ mutationFn: ({ id, body }: { id: number; body: Record<string, unknown> }) => adminApi.updateCountry(id, body), onSuccess: refresh, onError: (e) => toast.error(errorMessage(e)) })
  const remove = useMutation({ mutationFn: (id: number) => adminApi.deleteCountry(id), onSuccess: () => { refresh(); toast.success('Country deleted.') } })

  const rows = (data ?? []).filter((c) => `${c.name} ${c.code} ${c.currency_code}`.toLowerCase().includes(search.toLowerCase()))
  const enabled = data?.filter((c) => c.is_active).length ?? 0

  return (
    <>
      <PageHeader eyebrow="Platform" title="Countries" description={`${enabled} of ${data?.length ?? 0} countries enabled. Visitors from an enabled country see pricing in that country's currency; other visitors see the default country.`}
        actions={<Button icon={<Plus className="size-4" />} onClick={() => setAdding(true)}>Add country</Button>} />
      <div className="relative mb-5 sm:max-w-sm">
        <Search className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-ink-400" />
        <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search countries…" className="pl-10" />
      </div>
      {error && <Alert>{errorMessage(error)}</Alert>}
      <Card className="overflow-hidden">
        {isLoading ? <div className="space-y-2 p-4">{Array.from({ length: 6 }).map((_, i) => <Skeleton key={i} className="h-12" />)}</div> : rows.length === 0 ? <EmptyState icon={<Globe className="size-6" />} title="No countries match" /> : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[760px] text-left text-[13px]">
              <thead className="border-b border-line bg-slate-50/70 text-xs font-bold tracking-wide text-ink-500 uppercase"><tr><th className="px-6 py-3">Country</th><th className="px-4 py-3">Currency</th><th className="px-4 py-3">Dial code</th><th className="px-4 py-3 text-right">Tenants</th><th className="px-4 py-3 text-center">Enabled</th><th className="px-6 py-3" /></tr></thead>
              <tbody className="divide-y divide-line">
                {rows.map((c) => (
                  <tr key={c.id}>
                    <td className="px-6 py-3"><div className="flex items-center gap-3"><CountryChip code={c.code} className="h-6 min-w-[34px] text-[11px]" /><div><div className="font-bold">{c.name}</div><div className="text-xs text-ink-500">{c.code}{c.timezone ? ` · ${c.timezone}` : ''}</div></div>{c.is_default && <Badge className="bg-brand-50 text-brand-700 ring-brand-200">Default</Badge>}</div></td>
                    <td className="px-4 py-3">
                      <Select aria-label={`Currency for ${c.name}`} value={c.currency_code} onChange={(e) => update.mutate({ id: c.id!, body: { currency_code: e.target.value } })} className="h-9 w-28 rounded-lg py-0 text-[13px]">
                        {currencies?.map((cur) => <option key={cur.code} value={cur.code}>{cur.code}</option>)}
                      </Select>
                    </td>
                    <td className="px-4 py-3 text-ink-500">{c.dial_code ?? '—'}</td>
                    <td className="tabular px-4 py-3 text-right font-semibold">{c.tenants_count ?? 0}</td>
                    <td className="px-4 py-3 text-center"><Switch checked={!!c.is_active} label={`Enable ${c.name}`} disabled={c.is_default} onChange={(v) => update.mutate({ id: c.id!, body: { is_active: v } })} /></td>
                    <td className="px-6 py-3 text-right">
                      {!c.is_default && <ConfirmButton danger title="Delete country" message={`Delete ${c.name}? Countries used by tenants must be disabled instead.`} confirmLabel="Delete" onConfirm={() => remove.mutateAsync(c.id!).catch((e) => { throw new Error(errorMessage(e)) })} trigger={(o) => <button onClick={o} className="rounded-lg p-2 text-ink-400 hover:bg-rose-50 hover:text-rose-600" aria-label={`Delete ${c.name}`}><Trash2 className="size-4" /></button>} />}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>
      {adding && <AddCountryModal currencies={currencies?.map((c) => c.code) ?? []} onClose={() => setAdding(false)} onSaved={() => { setAdding(false); refresh(); toast.success('Country added.') }} />}
    </>
  )
}

function AddCountryModal({ currencies, onClose, onSaved }: { currencies: string[]; onClose: () => void; onSaved: () => void }) {
  const [form, setForm] = useState({ code: '', name: '', currency_code: currencies[0] ?? 'USD', dial_code: '', timezone: '' })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const save = useMutation({
    mutationFn: () => adminApi.createCountry({ ...form, dial_code: form.dial_code || null, timezone: form.timezone || null, is_active: true }),
    onSuccess: onSaved,
    onError: (e) => setErrors(fieldErrors(e)),
  })
  return (
    <Modal open onClose={onClose} title="Add country" footer={<><Button variant="secondary" onClick={onClose}>Cancel</Button><Button loading={save.isPending} onClick={() => { setErrors({}); save.mutate() }}>Add country</Button></>}>
      <div className="grid gap-5 sm:grid-cols-2">
        <Field label="ISO code (2 letters)" htmlFor="ac-code" error={errors.code}><Input id="ac-code" maxLength={2} value={form.code} onChange={(e) => setForm({ ...form, code: e.target.value.toUpperCase() })} placeholder="QA" autoFocus /></Field>
        <Field label="Name" htmlFor="ac-name" error={errors.name}><Input id="ac-name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="Qatar" /></Field>
        <Field label="Currency" htmlFor="ac-cur" error={errors.currency_code} hint="Add currencies first under Currencies."><Select id="ac-cur" value={form.currency_code} onChange={(e) => setForm({ ...form, currency_code: e.target.value })}>{currencies.map((c) => <option key={c}>{c}</option>)}</Select></Field>
        <Field label="Dial code" htmlFor="ac-dial"><Input id="ac-dial" value={form.dial_code} onChange={(e) => setForm({ ...form, dial_code: e.target.value })} placeholder="+974" /></Field>
        <div className="sm:col-span-2"><Field label="Time zone (IANA)" htmlFor="ac-tz" error={errors.timezone}><Input id="ac-tz" value={form.timezone} onChange={(e) => setForm({ ...form, timezone: e.target.value })} placeholder="Asia/Qatar" /></Field></div>
      </div>
    </Modal>
  )
}
